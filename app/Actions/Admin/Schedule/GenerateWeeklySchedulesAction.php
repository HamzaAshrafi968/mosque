<?php

namespace App\Actions\Admin\Schedule;

use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * يولّد جدولاً أسبوعياً لبرنامج/فترة (تخصص) واحد عبر عدة أيام بضغطة واحدة —
 * مثال: برنامج التحفيظ، الفترة الأولى فقط دون الثانية.
 *
 * - تُستنتج الأوقات من الفترة المختارة كما في إضافة الحصة المفردة.
 * - الصفوف المطابقة الموجودة مسبقاً تُتخطى (idempotent) ويُعاد عددها.
 * - يُرفض التعارض الحقيقي: انشغال المعلم أو الصف/الشعبة في نفس الوقت.
 */
class GenerateWeeklySchedulesAction
{
    private const DAY_NAMES = [
        0 => 'الأحد',
        1 => 'الاثنين',
        2 => 'الثلاثاء',
        3 => 'الأربعاء',
        4 => 'الخميس',
        5 => 'الجمعة',
        6 => 'السبت',
    ];

    public function __construct(
        private readonly ResolveScheduleProgramAction $resolveProgram,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{created: int, skipped: int}
     *
     * @throws ValidationException
     */
    public function execute(array $data): array
    {
        $days = array_values(array_unique(array_map('intval', $data['days'])));
        sort($days);

        $base = $this->resolveProgram->execute(array_diff_key($data, ['days' => null]));

        $startsAt = CarbonImmutable::parse($base['starts_at'])->format('H:i:s');
        $endsAt = CarbonImmutable::parse($base['ends_at'])->format('H:i:s');

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($base, $days, $startsAt, $endsAt, &$created, &$skipped) {
            foreach ($days as $day) {
                if ($this->duplicateExists($base, $day, $startsAt, $endsAt)) {
                    $skipped++;

                    continue;
                }

                $this->assertNoConflicts($base, $day, $startsAt, $endsAt);

                Schedule::create($base + ['day_of_week' => $day]);
                $created++;
            }
        });

        return ['created' => $created, 'skipped' => $skipped];
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

    /** @throws ValidationException */
    private function assertNoConflicts(array $attributes, int $day, string $startsAt, string $endsAt): void
    {
        $overlaps = fn ($query) => $query
            ->where('day_of_week', $day)
            ->whereTime('starts_at', '<', $endsAt)
            ->whereTime('ends_at', '>', $startsAt);

        $teacherConflict = $overlaps(
            $this->schedules()->where('teacher_id', $attributes['teacher_id'])
        )->exists();

        if ($teacherConflict) {
            throw ValidationException::withMessages([
                'teacher_id' => 'المعلم لديه حصة متعارضة يوم '.self::DAY_NAMES[$day].' ('.substr($startsAt, 0, 5).'–'.substr($endsAt, 0, 5).')',
            ]);
        }

        $classroomConflict = $overlaps(
            $this->schedules()
                ->where('classroom_id', $attributes['classroom_id'])
                ->where('section_id', $attributes['section_id'] ?? null)
        )->exists();

        if ($classroomConflict) {
            throw ValidationException::withMessages([
                'classroom_id' => 'يوجد حصة أخرى لنفس الصف/الشعبة يوم '.self::DAY_NAMES[$day].' ('.substr($startsAt, 0, 5).'–'.substr($endsAt, 0, 5).')',
            ]);
        }
    }

    private function schedules(): Builder
    {
        return Schedule::withoutGlobalScope('study_session');
    }
}
