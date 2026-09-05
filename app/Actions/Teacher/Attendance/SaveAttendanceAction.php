<?php

namespace App\Actions\Teacher\Attendance;

use App\Enums\AttendanceSessionStatus;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Section;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DashboardService;
use App\Services\NotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Saves attendance for a (date, statuses) payload.
 *
 * One attendance session per (section, date) is created/updated and each
 * student's status is upserted as an attendance_record — the individual
 * records are the source of truth and no derived percentage is stored.
 *
 * Server-side scope (spec §36): when the actor is a teacher the request may
 * only contain students of sections assigned to that teacher (explicit
 * section_teachers rows or timetable fallback). Managers may record anywhere
 * in their mosque. Absent/late marks fan out notifications to the student's
 * portal account and the guardians (spec §39).
 */
class SaveAttendanceAction
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @param  array{date: string, statuses: array<string, string>, notes?: array<string, string>}  $data
     * @return Collection<int, AttendanceSession>
     */
    public function execute(array $data, User $actor): Collection
    {
        $allowed = collect(AttendanceStatus::cases())->pluck('value')->all();
        $invalidStatuses = collect($data['statuses'])->filter(fn ($status) => ! in_array($status, $allowed, true));

        if ($invalidStatuses->isNotEmpty()) {
            throw ValidationException::withMessages([
                'statuses' => ['حالة حضور غير صالحة — القيم المسموحة: '.implode('، ', $allowed)],
            ]);
        }

        $students = Student::query()
            ->active()
            ->whereIn('id', array_keys($data['statuses']))
            ->get(['id', 'tenant_id', 'section_id', 'name', 'user_id']);

        $invalidIds = array_diff(array_keys($data['statuses']), $students->pluck('id')->all());

        if ($invalidIds !== []) {
            throw ValidationException::withMessages([
                'statuses' => ['يحتوي الطلب على طلاب غير مسجلين أو غير نشطين في هذا الجامع'],
            ]);
        }

        $allowedSectionIds = $this->allowedSectionIdsFor($actor);

        if ($allowedSectionIds !== null) {
            $foreign = $students
                ->reject(fn (Student $student) => $student->section_id !== null && in_array($student->section_id, $allowedSectionIds, true));

            if ($foreign->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'statuses' => ['يمكنك تسجيل الحضور فقط للشعب الموكلة إليك — الطلاب غير المسموحين: '.$foreign->pluck('name')->implode('، ')],
                ]);
            }
        }

        $noSection = $students->filter(fn (Student $s) => $s->section_id === null);

        if ($noSection->isNotEmpty()) {
            throw ValidationException::withMessages([
                'statuses' => ['الطلاب التاليون غير مقيدين في أي شعبة: '.$noSection->pluck('name')->implode('، ')],
            ]);
        }

        $notes = $data['notes'] ?? [];

        $sessions = $students
            ->groupBy('section_id')
            ->map(fn (Collection $group) => $this->saveSession(
                Section::find($group->first()->section_id),
                $data['date'],
                $group,
                $data['statuses'],
                $notes,
                $actor
            ))
            ->values();

        DashboardService::flush($actor->tenant_id);

        return $sessions;
    }

    /** Assigned sections for teacher actors; null = mosque-wide actors (manager). */
    private function allowedSectionIdsFor(User $actor): ?array
    {
        if (! $actor->isTeacher()) {
            return null;
        }

        $teacher = Teacher::query()
            ->where('tenant_id', $actor->tenant_id)
            ->where('user_id', $actor->id)
            ->first();

        return $teacher?->manageableSectionIds();
    }

    private function saveSession(Section $section, string $date, Collection $students, array $statuses, array $notes, User $actor): AttendanceSession
    {
        $session = AttendanceSession::query()
            ->where('section_id', $section->id)
            ->whereDate('date', $date)
            ->first();

        $created = $session === null;

        if ($created) {
            $session = AttendanceSession::create([
                'tenant_id' => $section->tenant_id,
                'section_id' => $section->id,
                'date' => $date,
                'status' => AttendanceSessionStatus::Completed,
                'created_by' => $actor->id,
            ]);
        }

        $now = now();

        $rows = $students->map(function (Student $student) use ($section, $session, $statuses, $notes, $now) {
            $status = $statuses[$student->id] ?? null;
            $studentNotes = $notes[$student->id] ?? null;

            return [
                'id' => (string) Str::uuid(),
                'tenant_id' => $section->tenant_id,
                'attendance_session_id' => $session->id,
                'student_id' => $student->id,
                'status' => $status,
                'note' => $studentNotes !== '' && $studentNotes !== null ? $studentNotes : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->values()->all();

        AttendanceRecord::upsert(
            $rows,
            ['tenant_id', 'attendance_session_id', 'student_id'],
            ['status', 'note', 'updated_at']
        );

        $this->audit->log(
            $created ? 'attendance.session_created' : 'attendance.session_updated',
            'attendance_session',
            $session->id,
            $section->tenant_id,
            after: [
                'section_id' => $section->id,
                'date' => $date,
                'records' => count($rows),
            ],
            actor: $actor
        );

        $this->notifyAbsentLate($students, $statuses, $section, $date);

        return $session;
    }

    /** Absence / lateness fan-out (spec §10/§39). */
    private function notifyAbsentLate(Collection $students, array $statuses, Section $section, string $date): void
    {
        $targets = $students->filter(
            fn (Student $student) => in_array($statuses[$student->id] ?? null, [AttendanceStatus::Absent->value, AttendanceStatus::Late->value], true)
        );

        if ($targets->isEmpty()) {
            return;
        }

        $label = [
            AttendanceStatus::Absent->value => 'غاب',
            AttendanceStatus::Late->value => 'تأخر',
        ];

        $dateLabel = Carbon::parse($date)->translatedFormat('Y-m-d');

        foreach ($targets as $student) {
            $this->notifications->notifyStudentCircle(
                $student,
                'تنبيه حضور',
                "الطالب {$student->name} {$label[$statuses[$student->id]]} يوم {$dateLabel} — شعبة {$section->name}",
                route('notifications.index', [], false)
            );
        }
    }
}
