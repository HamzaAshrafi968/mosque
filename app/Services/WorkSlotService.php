<?php

namespace App\Services;

use App\Models\Teacher;
use App\Models\User;
use App\Models\WorkSlot;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * إدارة فترات العمل الفعلية (Work Slots) مع كل قواعد التحقق:
 * BR-02/04 (الترتيب والمدة الصفرية)، BR-05 (الحد الأقصى)، BR-08 (منتصف الليل)،
 * BR-03 (منع التداخل)، BR-12 (قفل الشهر المغلق).
 */
class WorkSlotService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly WorkHoursSettingsService $settings,
        private readonly PayrollPeriodService $payroll,
    ) {}

    public function create(Teacher $teacher, array $data, User $actor): WorkSlot
    {
        $date = CarbonImmutable::parse($data['date'])->toDateString();

        $this->assertMonthOpen($teacher->id, $date);
        $duration = $this->assertRange($data['start_time'], $data['end_time']);

        $slot = DB::transaction(function () use ($teacher, $data, $date, $duration, $actor) {
            $this->assertNoOverlap($teacher->id, $date, $data['start_time'], $data['end_time']);

            return WorkSlot::create([
                'tenant_id' => $teacher->tenant_id,
                'teacher_id' => $teacher->id,
                'date' => $date,
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'duration_minutes' => $duration,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);
        });

        $this->audit->logModel('work_slot.created', $slot, actor: $actor);
        $this->payroll->refreshForSlot($slot);

        return $slot;
    }

    public function update(WorkSlot $slot, array $data, User $actor): WorkSlot
    {
        $date = CarbonImmutable::parse($data['date'])->toDateString();

        $this->assertMonthOpen($slot->teacher_id, $slot->date);
        $this->assertMonthOpen($slot->teacher_id, $date);
        $duration = $this->assertRange($data['start_time'], $data['end_time']);

        $before = $slot->getAttributes();

        DB::transaction(function () use ($slot, $data, $date, $duration) {
            $this->assertNoOverlap($slot->teacher_id, $date, $data['start_time'], $data['end_time'], $slot);

            $slot->update([
                'date' => $date,
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'duration_minutes' => $duration,
                'notes' => $data['notes'] ?? null,
            ]);
        });

        $this->audit->logModel('work_slot.updated', $slot, $before, actor: $actor);
        $this->payroll->refreshForSlot($slot);

        return $slot;
    }

    public function delete(WorkSlot $slot, User $actor): void
    {
        $this->assertMonthOpen($slot->teacher_id, $slot->date);

        $this->audit->logModel('work_slot.deleted', $slot, actor: $actor);
        $slot->delete();
        $this->payroll->refreshForSlot($slot);
    }

    /**
     * توليد فترات يوم محدد من الجدول الأسبوعي المتكرر (المخطط) مع تجاهل
     * الفترات المتعارضة مع المسجَّل فعلاً.
     */
    public function generateFromSchedule(Teacher $teacher, CarbonInterface|string $date, User $actor): int
    {
        $date = CarbonImmutable::parse($date)->toDateString();
        $dayOfWeek = CarbonImmutable::parse($date)->dayOfWeek;

        $periods = $teacher->workHours()
            ->where('day_of_week', $dayOfWeek)
            ->orderBy('start_time')
            ->get();

        $created = 0;

        foreach ($periods as $period) {
            $start = substr($period->start_time, 0, 5);
            $end = substr($period->end_time, 0, 5);

            if ($this->hasOverlap($teacher->id, $date, $start, $end)) {
                continue;
            }

            $this->create($teacher, [
                'date' => $date,
                'start_time' => $start,
                'end_time' => $end,
                'notes' => 'مولّد من الجدول الأسبوعي',
            ], $actor);

            $created++;
        }

        return $created;
    }

    /** يمنع التعديل على فترات شهر أُغلق كشفه (BR-12). */
    public function assertMonthOpen(string $teacherId, CarbonInterface|string $date): void
    {
        if (! $this->payroll->isMonthClosed($teacherId, $date)) {
            return;
        }

        throw ValidationException::withMessages([
            'date' => ['الشهر مغلق — يلزم إعادة فتحه قبل تعديل الفترات'],
        ]);
    }

    private function assertRange(string $startTime, string $endTime): int
    {
        $start = WorkSlot::toMinutes($startTime);
        $end = WorkSlot::toMinutes($endTime);

        if ($end === $start) {
            throw ValidationException::withMessages([
                'end_time' => ['لا يمكن تسجيل فترة بمدة صفر'],
            ]);
        }

        if ($end < $start) {
            throw ValidationException::withMessages([
                'end_time' => ['لا يمكن تسجيل فترة تعبر منتصف الليل؛ يجب أن يكون وقت النهاية بعد وقت البداية'],
            ]);
        }

        $maxMinutes = (int) round($this->settings->maxSlotHours() * 60);

        if ($end - $start > $maxMinutes) {
            $max = rtrim(rtrim(number_format($this->settings->maxSlotHours(), 2, '.', ''), '0'), '.');

            throw ValidationException::withMessages([
                'end_time' => ["لا يمكن أن تتجاوز الفترة الواحدة {$max} ساعة"],
            ]);
        }

        return $end - $start;
    }

    private function assertNoOverlap(string $teacherId, string $date, string $startTime, string $endTime, ?WorkSlot $ignore = null): void
    {
        $start = WorkSlot::toMinutes($startTime);
        $end = WorkSlot::toMinutes($endTime);

        $conflicts = WorkSlot::query()
            ->forTeacher($teacherId)
            ->onDate($date)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
            ->lockForUpdate()
            ->get();

        foreach ($conflicts as $slot) {
            if ($start < $slot->endMinutes() && $end > $slot->startMinutes()) {
                throw ValidationException::withMessages([
                    'start_time' => ["تتعارض مع فترة قائمة: {$slot->start_time} — {$slot->end_time}"],
                ]);
            }
        }
    }

    private function hasOverlap(string $teacherId, string $date, string $startTime, string $endTime): bool
    {
        $start = WorkSlot::toMinutes($startTime);
        $end = WorkSlot::toMinutes($endTime);

        return WorkSlot::query()
            ->forTeacher($teacherId)
            ->onDate($date)
            ->get()
            ->contains(fn (WorkSlot $slot) => $start < $slot->endMinutes() && $end > $slot->startMinutes());
    }
}
