<?php

namespace App\Actions\Admin\Schedule;

use App\Exceptions\ScheduleConflictException;
use App\Models\Schedule;
use App\Services\ScheduleConflictService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * يولّد جدولاً أسبوعياً لبرنامج/فترة (تخصص) واحد عبر عدة أيام بضغطة واحدة —
 * مثال: برنامج التحفيظ، الفترة الأولى فقط دون الثانية.
 *
 * - تُستنتج الأوقات من الفترة المختارة كما في إضافة الحصة المفردة.
 * - الصفوف المطابقة الموجودة مسبقاً تُتخطى (idempotent) ويُعاد عددها.
 * - يُرفض التعارض الحقيقي عبر ScheduleConflictService: انشغال المعلم
 *   (في أي دوام) أو تعارض الشعبة/الصف، داخل قفل ومعاملة واحدة.
 */
class GenerateWeeklySchedulesAction
{
    public function __construct(
        private readonly ResolveScheduleProgramAction $resolveProgram,
        private readonly ScheduleConflictService $conflicts,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{created: int, skipped: int}
     *
     * @throws ScheduleConflictException
     */
    public function execute(array $data): array
    {
        $days = array_values(array_unique(array_map('intval', $data['days'])));
        sort($days);

        $base = $this->resolveProgram->execute(array_diff_key($data, ['days' => null]));

        $startsAt = CarbonImmutable::parse($base['starts_at'])->format('H:i:s');
        $endsAt = CarbonImmutable::parse($base['ends_at'])->format('H:i:s');

        $lockKeys = array_filter([
            ! empty($base['teacher_id']) ? 'teacher:'.$base['teacher_id'] : null,
            ! empty($base['section_id']) ? 'section:'.$base['section_id'] : null,
            ! empty($base['classroom_id']) ? 'classroom:'.$base['classroom_id'] : null,
        ]);

        return $this->conflicts->withScheduleLock($lockKeys, function () use ($base, $days, $startsAt, $endsAt) {
            $created = 0;
            $skipped = 0;

            DB::transaction(function () use ($base, $days, $startsAt, $endsAt, &$created, &$skipped) {
                foreach ($days as $day) {
                    if ($this->duplicateExists($base, $day, $startsAt, $endsAt)) {
                        $skipped++;

                        continue;
                    }

                    $this->conflicts->assertSlot($base + ['day_of_week' => $day]);

                    Schedule::create($base + ['day_of_week' => $day]);
                    $created++;
                }
            });

            return ['created' => $created, 'skipped' => $skipped];
        });
    }

    /** Exact same row already exists (safe to re-run the generator). */
    private function duplicateExists(array $attributes, int $day, string $startsAt, string $endsAt): bool
    {
        return $this->schedules()
            ->where('classroom_id', $attributes['classroom_id'])
            ->where('section_id', $attributes['section_id'] ?? null)
            ->where('teacher_id', $attributes['teacher_id'])
            ->where('program_id', $attributes['program_id'] ?? null)
            ->where('program_period_id', $attributes['program_period_id'] ?? null)
            ->where('study_session_id', $attributes['study_session_id'] ?? null)
            ->where('day_of_week', $day)
            ->whereTime('starts_at', $startsAt)
            ->whereTime('ends_at', $endsAt)
            ->exists();
    }

    private function schedules(): Builder
    {
        return Schedule::withoutGlobalScope('study_session');
    }
}
