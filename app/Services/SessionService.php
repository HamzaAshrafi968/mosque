<?php

namespace App\Services;

use App\Enums\SessionStatus;
use App\Exceptions\ScheduleConflictException;
use App\Models\ClassSession;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * إلغاء/تأجيل/استرجاع حصة في تاريخ محدد (ClassSession).
 *
 * الحصة الأسبوعية المتكررة تبقى كما هي في `schedules`، وهذا الجدول يحفظ
 * الاستثناء ليوم واحد فقط. كل عملية تُسجَّل في سجل التدقيق وتُرسل إشعارات
 * للمعلم والطلاب (وأولياء أمورهم).
 */
class SessionService
{
    public function __construct(
        private readonly ScheduleConflictService $conflicts,
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    /** إلغاء حصة يوم محدد. */
    public function cancel(Schedule $schedule, string $date, ?string $reason, User $actor): ClassSession
    {
        $parsed = $this->guardDateMatchesSlot($schedule, $date);

        $session = $this->storeException($schedule, $parsed, [
            'status' => SessionStatus::Cancelled,
            'reason' => $reason,
            'postponed_date' => null,
            'postponed_starts_at' => null,
            'postponed_ends_at' => null,
            'changed_by' => $actor->id,
        ]);

        $this->audit->logModel('session.cancelled', $session, actor: $actor);

        $this->notify(
            $schedule,
            'إلغاء حصة',
            'تم إلغاء حصة '.$this->slotLabel($schedule).' يوم '.$parsed->toDateString()
                .($reason ? ' — السبب: '.$reason : '')
        );

        return $session;
    }

    /** تأجيل حصة يوم محدد إلى موعد جديد بعد فحص التعارضات. */
    public function postpone(
        Schedule $schedule,
        string $date,
        string $newDate,
        string $startsAt,
        string $endsAt,
        ?string $reason,
        User $actor,
    ): ClassSession {
        $parsed = $this->guardDateMatchesSlot($schedule, $date);
        $newParsed = Carbon::parse($newDate)->startOfDay();

        $existing = $this->findException($schedule, $parsed);

        $conflictMessages = [];

        foreach ($this->conflicts->postponementConflicts(
            $schedule,
            $newParsed->toDateString(),
            $startsAt,
            $endsAt,
            $existing?->id
        ) as $conflict) {
            $conflictMessages[$conflict['field']][] = $conflict['message'];
        }

        if ($conflictMessages !== []) {
            throw ScheduleConflictException::withMessages($conflictMessages);
        }

        $session = $this->storeException($schedule, $parsed, [
            'status' => SessionStatus::Postponed,
            'reason' => $reason,
            'postponed_date' => $newParsed->toDateString(),
            'postponed_starts_at' => $startsAt,
            'postponed_ends_at' => $endsAt,
            'changed_by' => $actor->id,
        ]);

        $this->audit->logModel('session.postponed', $session, actor: $actor);

        $this->notify(
            $schedule,
            'تأجيل حصة',
            'تم تأجيل حصة '.$this->slotLabel($schedule).' من '.$parsed->toDateString()
                .' إلى '.$newParsed->toDateString().' ('.substr($startsAt, 0, 5).'–'.substr($endsAt, 0, 5).')'
                .($reason ? ' — السبب: '.$reason : '')
        );

        return $session;
    }

    /** إرجاع الحصة لطبيعتها بحذف الاستثناء. */
    public function restore(ClassSession $session, User $actor): void
    {
        $schedule = $session->schedule;
        $before = $session->only(['section_id', 'schedule_id', 'date', 'status', 'postponed_date']);

        $session->delete();

        $this->audit->log(
            'session.restored',
            'class_session',
            $session->id,
            $session->tenant_id,
            before: $before,
            actor: $actor
        );

        if ($schedule) {
            $this->notify(
                $schedule,
                'إعادة حصة',
                'تمت إعادة حصة '.$this->slotLabel($schedule).' إلى موعدها الأصلي'
            );
        }
    }

    /**
     * الاستثناءات القادمة (ملغاة/مؤجلة) مجمّعة حسب الحصة الأسبوعية —
     * تُعرض على شبكة الجدول.
     *
     * @param  Collection<int, string>  $scheduleIds
     * @return Collection<string, Collection<int, ClassSession>>
     */
    public function upcomingExceptions(Collection $scheduleIds): Collection
    {
        if ($scheduleIds->isEmpty()) {
            return collect();
        }

        return ClassSession::query()
            ->withoutGlobalScope('study_session')
            ->whereIn('schedule_id', $scheduleIds)
            ->activeExceptions()
            ->where(function ($query) {
                $query->where('date', '>=', today())
                    ->orWhere('postponed_date', '>=', today());
            })
            ->with(['teacher:id,name', 'schedule.subject:id,name', 'schedule.program:id,name', 'schedule.classroom:id,name', 'schedule.section:id,name'])
            ->orderBy('date')
            ->get()
            ->groupBy('schedule_id');
    }

    /** الاستثناء الوحيد لحصة في تاريخ محدد (إن وُجد). */
    public function findException(Schedule $schedule, Carbon $date): ?ClassSession
    {
        return ClassSession::query()
            ->withoutGlobalScope('study_session')
            ->where('schedule_id', $schedule->id)
            ->whereDate('date', $date->toDateString())
            ->when($schedule->section_id === null, fn ($q) => $q->whereNull('section_id'))
            ->when($schedule->section_id !== null, fn ($q) => $q->where('section_id', $schedule->section_id))
            ->first();
    }

    /** @param array<string, mixed> $attributes */
    private function storeException(Schedule $schedule, Carbon $date, array $attributes): ClassSession
    {
        return DB::transaction(function () use ($schedule, $date, $attributes) {
            $session = $this->findException($schedule, $date)
                ?? new ClassSession([
                    'tenant_id' => $schedule->tenant_id,
                    'section_id' => $schedule->section_id,
                    'schedule_id' => $schedule->id,
                    'study_session_id' => $schedule->study_session_id,
                    'teacher_id' => $schedule->teacher_id,
                    'date' => $date->toDateString(),
                ]);

            $session->fill($attributes)->save();

            return $session;
        });
    }

    private function guardDateMatchesSlot(Schedule $schedule, string $date): Carbon
    {
        $parsed = Carbon::parse($date)->startOfDay();
        $expected = (int) $schedule->day_of_week;

        if ($parsed->dayOfWeek !== $expected) {
            throw ValidationException::withMessages([
                'date' => 'التاريخ '.$parsed->toDateString().' يوافق '
                    .ScheduleConflictService::DAY_NAMES[$parsed->dayOfWeek]
                    .' بينما الحصة يوم '.ScheduleConflictService::DAY_NAMES[$expected],
            ]);
        }

        return $parsed;
    }

    private function notify(Schedule $schedule, string $title, string $body): void
    {
        $schedule->loadMissing(['teacher:id,user_id,name', 'section:id,name', 'classroom:id,name', 'subject:id,name', 'program:id,name']);

        $students = Student::query()
            ->active()
            ->where('classroom_id', $schedule->classroom_id)
            ->when($schedule->section_id, fn ($q) => $q->where('section_id', $schedule->section_id))
            ->get(['id', 'tenant_id', 'user_id']);

        if ($students->isNotEmpty()) {
            $this->notifications->notifyRoster($students, $title, $body);
        }

        if ($schedule->teacher?->user_id) {
            $this->notifications->send(
                [$schedule->teacher->user_id],
                $title,
                $body,
                route('teacher.schedule', [], false)
            );
        }
    }

    private function slotLabel(Schedule $schedule): string
    {
        $label = $schedule->subject?->name ?? $schedule->program?->name ?? 'دراسية';

        return '«'.$label.'»';
    }
}
