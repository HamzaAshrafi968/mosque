<?php

namespace App\Actions\Admin\Schedule;

use App\Enums\ScheduleDuration;
use App\Models\Program;
use App\Models\Schedule;
use App\Services\ScheduleConflictService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * النموذج الموحّد لإضافة الحصص: تُختار أيام الأسبوع ومدة الصلاحية معاً،
 * فتُنشأ حصة لكل يوم بنفس النطاق الزمني (يوم/أسبوع/شهر/حتى انتهاء الدورة/
 * مفتوحة). الصفوف المطابقة الموجودة مسبقاً تُتخطى (idempotent) ويُرفض
 * التعارض الحقيقي عبر ScheduleConflictService داخل قفل ومعاملة واحدة.
 */
class SaveScheduleSlotsAction
{
    public function __construct(
        private readonly ResolveScheduleProgramAction $resolveProgram,
        private readonly ScheduleConflictService $conflicts,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{created: int, skipped: int, schedules: Collection<int, Schedule>}
     *
     * @throws ValidationException
     */
    public function execute(array $data, bool $skipDuplicates = true): array
    {
        $days = $this->days($data);
        $duration = ScheduleDuration::tryFrom((string) ($data['duration'] ?? '')) ?? ScheduleDuration::Open;
        $startsOn = ! empty($data['starts_on'])
            ? CarbonImmutable::parse($data['starts_on'])->startOfDay()
            : CarbonImmutable::today();

        $base = $this->resolveProgram->execute(
            collect($data)->except(['days', 'day_of_week', 'duration', 'starts_on'])->all()
        );

        $endsOn = $this->resolveEndDate($base, $duration, $startsOn);

        $base['starts_on'] = $startsOn->toDateString();
        $base['ends_on'] = $endsOn?->toDateString();
        $base['duration'] = $duration;

        $lockKeys = array_filter([
            ! empty($base['teacher_id']) ? 'teacher:'.$base['teacher_id'] : null,
            ! empty($base['section_id']) ? 'section:'.$base['section_id'] : null,
            ! empty($base['classroom_id']) ? 'classroom:'.$base['classroom_id'] : null,
        ]);

        return $this->conflicts->withScheduleLock(
            $lockKeys,
            function () use ($base, $days, $skipDuplicates) {
                $created = 0;
                $skipped = 0;
                $schedules = new Collection;

                DB::transaction(function () use ($base, $days, $skipDuplicates, &$created, &$skipped, &$schedules) {
                    foreach ($days as $day) {
                        $slot = $base + ['day_of_week' => $day];

                        if ($skipDuplicates && $this->duplicateExists($slot)) {
                            $skipped++;

                            continue;
                        }

                        $this->conflicts->assertSlot($slot);

                        $schedules->push(Schedule::create($slot));
                        $created++;
                    }
                });

                return ['created' => $created, 'skipped' => $skipped, 'schedules' => $schedules];
            }
        );
    }

    /**
     * أيام التكرار: `days[]` في النموذج المدموج، و`day_of_week` للتوافق الخلفي.
     *
     * @return array<int, int>
     */
    private function days(array $data): array
    {
        $days = $data['days'] ?? (isset($data['day_of_week']) ? [$data['day_of_week']] : []);

        $days = array_values(array_unique(array_map('intval', $days)));
        sort($days);

        return $days;
    }

    /** تاريخ النهاية حسب المدة؛ «حتى انتهاء الدورة» يقرأ نهاية البرنامج. */
    private function resolveEndDate(array $base, ScheduleDuration $duration, CarbonImmutable $startsOn): ?CarbonImmutable
    {
        if ($duration === ScheduleDuration::Open) {
            return null;
        }

        if ($duration === ScheduleDuration::Course) {
            $program = ! empty($base['program_id']) ? Program::find($base['program_id']) : null;

            if (! $program || $program->ends_on === null) {
                throw ValidationException::withMessages([
                    'duration' => 'البرنامج المختار بلا تاريخ نهاية — حدد «تاريخ نهاية الدورة» من صفحة البرنامج أولاً',
                ]);
            }

            $courseEndsOn = CarbonImmutable::parse($program->ends_on)->startOfDay();

            if ($courseEndsOn->lessThan($startsOn)) {
                throw ValidationException::withMessages([
                    'duration' => 'تاريخ نهاية الدورة قبل تاريخ بداية الحصة — اختر تاريخ بداية أصح',
                ]);
            }

            return $courseEndsOn;
        }

        return CarbonImmutable::parse($duration->endDate($startsOn));
    }

    /** نفس الحصة بنفس النطاق الزمني موجودة مسبقاً (إعادة الإضافة آمنة). */
    private function duplicateExists(array $slot): bool
    {
        return Schedule::query()
            ->withoutGlobalScope('study_session')
            ->where('classroom_id', $slot['classroom_id'])
            ->where('section_id', $slot['section_id'] ?? null)
            ->where('teacher_id', $slot['teacher_id'])
            ->where('program_id', $slot['program_id'] ?? null)
            ->where('program_period_id', $slot['program_period_id'] ?? null)
            ->where('study_session_id', $slot['study_session_id'] ?? null)
            ->where('day_of_week', $slot['day_of_week'])
            ->whereTime('starts_at', ScheduleConflictService::normalizeTime($slot['starts_at']))
            ->whereTime('ends_at', ScheduleConflictService::normalizeTime($slot['ends_at']))
            ->whereDate('starts_on', $slot['starts_on'])
            ->when(
                $slot['ends_on'] === null,
                fn ($query) => $query->whereNull('ends_on'),
                fn ($query) => $query->whereDate('ends_on', $slot['ends_on'])
            )
            ->exists();
    }
}
