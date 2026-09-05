<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Attendance statistics derived from attendance_records — the records are the
 * source of truth and no derived percentage is ever stored (spec §10-§12).
 *
 * Percentage rule (applied consistently everywhere):
 * - present  → counts as attended and qualifies the denominator
 * - late     → counts as attended (a separate late metric is also reported)
 * - absent   → qualifies the denominator, does not count as attended
 * - excused  → excluded from both numerator and denominator
 * - no record for a session → the session does not apply to that student
 *
 * percentage = (present + late) / (present + late + absent) * 100
 */
class AttendanceMetricService
{
    /** @return array{present:int,absent:int,late:int,excused:int,total:int,attended:int,percentage:?float} */
    public function statsForStudent(string $studentId, ?string $from = null, ?string $to = null): array
    {
        $records = AttendanceRecord::query()
            ->where('student_id', $studentId)
            ->when($from, fn ($q) => $q->whereHas('session', fn ($s) => $s->whereDate('date', '>=', $from)))
            ->when($to, fn ($q) => $q->whereHas('session', fn ($s) => $s->whereDate('date', '<=', $to)))
            ->get();

        return $this->summarise($records);
    }

    /**
     * Per-student stats for a section roster (any date range when omitted).
     *
     * @return Collection<string, array> keyed by student id
     */
    public function rosterStats(Section $section, ?string $from = null, ?string $to = null): Collection
    {
        $students = $section->students()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'gender']);

        $records = AttendanceRecord::query()
            ->whereIn('student_id', $students->pluck('id'))
            ->whereHas('session', function ($q) use ($section, $from, $to) {
                $q->where('section_id', $section->id)
                    ->when($from, fn ($s) => $s->whereDate('date', '>=', $from))
                    ->when($to, fn ($s) => $s->whereDate('date', '<=', $to));
            })
            ->get()
            ->groupBy('student_id');

        return $students->mapWithKeys(function (Student $student) use ($records) {
            $stats = $this->summarise($records->get($student->id, collect()));

            return [$student->id => ['student' => $student, ...$stats]];
        });
    }

    /**
     * The grid used by the attendance table UI (spec §11): students × sessions
     * with the status cell per session plus the per-student percentage.
     */
    public function grid(Section $section, string $from, string $to): array
    {
        $sessions = AttendanceSession::query()
            ->with('records')
            ->where('section_id', $section->id)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get();

        $students = $section->students()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'gender']);

        $rows = [];

        foreach ($students as $student) {
            $stats = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'total' => 0, 'attended' => 0, 'percentage' => null];

            $bySession = [];

            foreach ($sessions as $session) {
                $record = $session->records->firstWhere('student_id', $student->id);
                $status = $record?->status;

                if ($status) {
                    $bySession[$session->id] = $status;
                    $stats = $this->increment($stats, $status);
                } else {
                    $bySession[$session->id] = null;
                }
            }

            $stats['percentage'] = $this->percentage($stats);

            $rows[] = [
                'student' => $student,
                'cells' => $bySession,
                'stats' => $stats,
            ];
        }

        return [
            'sessions' => $sessions,
            'rows' => $rows,
        ];
    }

    /** Session-day summary used by the admin attendance day view. */
    public function sessionsWithTotals(Section $section, string $date): ?array
    {
        $session = AttendanceSession::query()
            ->with(['records', 'createdBy:id,name'])
            ->where('section_id', $section->id)
            ->whereDate('date', $date)
            ->first();

        if (! $session) {
            return null;
        }

        $stats = $this->summarise($session->records);

        return ['session' => $session, ...$stats];
    }

    /** @param  Collection<int, AttendanceRecord>  $records */
    private function summarise(Collection $records): array
    {
        $stats = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'total' => 0, 'attended' => 0, 'percentage' => null];

        foreach ($records as $record) {
            if (! $record->status instanceof AttendanceStatus) {
                continue;
            }

            $stats = $this->increment($stats, $record->status);
        }

        $stats['percentage'] = $this->percentage($stats);

        return $stats;
    }

    private function increment(array $stats, AttendanceStatus $status): array
    {
        $key = $status->value;

        $stats[$key]++;

        if ($status->qualifiesPercentage()) {
            $stats['total']++;
        }

        if ($status->countsAsAttended()) {
            $stats['attended']++;
        }

        return $stats;
    }

    private function percentage(array $stats): ?float
    {
        return $stats['total'] > 0
            ? round(($stats['attended'] / $stats['total']) * 100, 1)
            : null;
    }
}
