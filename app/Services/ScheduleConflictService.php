<?php

namespace App\Services;

use App\Enums\SectionStudentStatus;
use App\Enums\SessionStatus;
use App\Exceptions\ScheduleConflictException;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Student;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * المصدر الوحيد للحقيقة لكل فحوصات تعارض الجدول الدراسي.
 *
 * كل نقاط كتابة الحصص (إضافة حصة/حصص، النقل بين الشعب، نقل الصف بين
 * الدوامات، التأجيل) يجب أن تمر من هنا داخل معاملة + قفل
 * (`withScheduleLock`) حتى لا يسبق حفظان متزامنان الفحص.
 *
 * قاعدة التداخل: يتداخل الوقتان إذا كان aStart < bEnd و bStart < aEnd،
 * لذا الحصتان المتجاورتان (16:00–18:00 و18:00–20:00) لا تتعارضان.
 * وتُقارن مدة الصلاحية أيضاً: حصتان بنفس الوقت لكن بفترات تواريخ غير
 * متقاطعة (مثال: أسبوع مقابل الشهر القادم) لا تتعارضان.
 *
 * تعارض المعلم يُفحص عبر كل الدوامات (المعلم قد يدرّس في أكثر من دوام)،
 * بينما تعارض الشعبة/الصف يخص نفس الشعبة فقط. النطاق الزمني للجامع الحالي
 * يبقى مطبَّقاً عبر Global Scope الخاص بالـ tenant.
 */
class ScheduleConflictService
{
    public const DAY_NAMES = [
        0 => 'الأحد',
        1 => 'الاثنين',
        2 => 'الثلاثاء',
        3 => 'الأربعاء',
        4 => 'الخميس',
        5 => 'الجمعة',
        6 => 'السبت',
    ];

    // =====================================================================
    // قاعدة التداخل الزمني
    // =====================================================================

    /** تطبيع أي صيغة وقت (H:i أو H:i:s) إلى H:i:s للمقارنة النصية. */
    public static function normalizeTime(?string $time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }

        return Carbon::parse($time)->format('H:i:s');
    }

    public static function timesOverlap(?string $aStart, ?string $aEnd, ?string $bStart, ?string $bEnd): bool
    {
        $aStart = self::normalizeTime($aStart);
        $aEnd = self::normalizeTime($aEnd);
        $bStart = self::normalizeTime($bStart);
        $bEnd = self::normalizeTime($bEnd);

        if ($aStart === null || $aEnd === null || $bStart === null || $bEnd === null) {
            return false;
        }

        return $aStart < $bEnd && $bStart < $aEnd;
    }

    /**
     * هل تتقاطع مدتا صلاحية الحصتين؟ القيمة الفارغة = مفتوحة بلا حد
     * (من البداية للنهاية)، فالحصة المفتوحة تتقاطع مع كل شيء.
     */
    public static function dateRangesOverlap(
        ?string $aStart,
        ?string $aEnd,
        ?string $bStart,
        ?string $bEnd,
    ): bool {
        if ($aStart !== null && $bEnd !== null && $aStart > $bEnd) {
            return false;
        }

        if ($bStart !== null && $aEnd !== null && $bStart > $aEnd) {
            return false;
        }

        return true;
    }

    /** قصر الاستعلام على الحصص التي تتقاطع مدتها مع نطاق الحصة المقترحة. */
    private function applyDateWindow(Builder $query, ?string $startsOn, ?string $endsOn): Builder
    {
        return $query
            ->when($endsOn !== null, fn (Builder $q) => $q->where(
                fn (Builder $inner) => $inner->whereNull('starts_on')->orWhereDate('starts_on', '<=', $endsOn)
            ))
            ->when($startsOn !== null, fn (Builder $q) => $q->where(
                fn (Builder $inner) => $inner->whereNull('ends_on')->orWhereDate('ends_on', '>=', $startsOn)
            ));
    }

    // =====================================================================
    // فحص حصة واحدة
    // =====================================================================

    /**
     * كل تعارضات الحصة المقترحة.
     *
     * @param  array<string, mixed>  $slot  (classroom_id, section_id?, teacher_id, day_of_week, starts_at, ends_at)
     * @return array<int, array{field: string, message: string}>
     */
    public function slotConflicts(array $slot, ?string $excludeScheduleId = null): array
    {
        $day = (int) ($slot['day_of_week'] ?? -1);
        $startsAt = self::normalizeTime($slot['starts_at'] ?? null);
        $endsAt = self::normalizeTime($slot['ends_at'] ?? null);

        if ($day < 0 || $day > 6 || $startsAt === null || $endsAt === null) {
            return [];
        }

        $conflicts = [];

        // 1) تداخل مع حصص نفس الشعبة (أو حصة الصف كاملاً) ضمن نفس المدة.
        $sectionConflict = $this->overlappingSchedules($slot, $day, $startsAt, $endsAt, $excludeScheduleId)
            ->with(['classroom:id,name', 'section:id,name', 'subject:id,name'])
            ->first();

        if ($sectionConflict) {
            $conflicts[] = [
                'field' => 'classroom_id',
                'message' => 'يوجد حصة أخرى لنفس الشعبة '.self::DAY_NAMES[$day]
                    .' ('.$this->window($sectionConflict->starts_at, $sectionConflict->ends_at).')'
                    .($sectionConflict->subject?->name ? ' — '.$sectionConflict->subject->name : ''),
            ];
        }

        // 2) انشغال المعلم في أي شعبة/دوام داخل الجامع ضمن نفس المدة.
        if (! empty($slot['teacher_id'])) {
            $teacherConflict = $this->applyDateWindow(
                Schedule::query()
                    ->withoutGlobalScope('study_session')
                    ->where('teacher_id', $slot['teacher_id'])
                    ->where('day_of_week', $day)
                    ->whereTime('starts_at', '<', $endsAt)
                    ->whereTime('ends_at', '>', $startsAt)
                    ->when($excludeScheduleId, fn (Builder $q) => $q->whereKeyNot($excludeScheduleId)),
                $slot['starts_on'] ?? null,
                $slot['ends_on'] ?? null,
            )
                ->with(['classroom:id,name', 'section:id,name', 'subject:id,name', 'studySession:id,name'])
                ->first();

            if ($teacherConflict) {
                $conflicts[] = [
                    'field' => 'teacher_id',
                    'message' => 'المعلم لديه حصة متعارضة '.self::DAY_NAMES[$day]
                        .' ('.$this->window($teacherConflict->starts_at, $teacherConflict->ends_at).') في '
                        .($teacherConflict->section?->name ?? $teacherConflict->classroom?->name ?? 'شعبة أخرى')
                        .($teacherConflict->studySession ? ' — دوام '.$teacherConflict->studySession->name : ''),
                ];
            }
        }

        return $conflicts;
    }

    /**
     * @param  array<string, mixed>  $slot
     *
     * @throws ScheduleConflictException
     */
    public function assertSlot(array $slot, ?string $excludeScheduleId = null): void
    {
        $this->raise($this->slotConflicts($slot, $excludeScheduleId));
    }

    /** @param array<string, mixed> $slot */
    public function assertSlotForSection(Section $section, array $slot, ?string $excludeScheduleId = null): void
    {
        $this->assertSlot(array_merge($slot, [
            'classroom_id' => $section->classroom_id,
            'section_id' => $section->id,
            'tenant_id' => $section->tenant_id,
        ]), $excludeScheduleId);
    }

    /**
     * فحص جدول كامل (إنشاء/تعديل شعبة أو توليد أسبوعي): تداخل داخلي بين
     * الحصص المقترحة نفسها + فحص كل حصة على حدة.
     *
     * @param  iterable<int, array<string, mixed>>  $slots
     *
     * @throws ScheduleConflictException
     */
    public function assertSlotsSchedule(iterable $slots, ?string $excludeScheduleId = null): void
    {
        $slots = collect($slots)->values();
        $messages = [];

        foreach ($slots as $i => $a) {
            foreach ($slots as $j => $b) {
                if ($j <= $i || ! $this->slotsOverlap($a, $b)) {
                    continue;
                }

                $messages['classroom_id'][] = 'الحصتان رقم '.($i + 1).' و'.($j + 1).' في الجدول المقترح متداخلتان';
            }
        }

        foreach ($slots as $slot) {
            foreach ($this->slotConflicts($slot, $excludeScheduleId) as $conflict) {
                $messages[$conflict['field']][] = $conflict['message'];
            }
        }

        if ($messages !== []) {
            throw ScheduleConflictException::withMessages($messages);
        }
    }

    /** نسخة ترجع الرسائل بدون رمي استثناء (للمعاينة). */
    public function slotsScheduleConflicts(iterable $slots, ?string $excludeScheduleId = null): array
    {
        try {
            $this->assertSlotsSchedule($slots, $excludeScheduleId);

            return [];
        } catch (ScheduleConflictException $exception) {
            return collect($exception->errors())->flatten()->values()->all();
        }
    }

    /** هل تتعارض حصتان مقترحتان داخل نفس الجدول؟ */
    private function slotsOverlap(array $a, array $b): bool
    {
        if ((int) ($a['day_of_week'] ?? -1) !== (int) ($b['day_of_week'] ?? -2)) {
            return false;
        }

        if (! self::timesOverlap($a['starts_at'] ?? null, $a['ends_at'] ?? null, $b['starts_at'] ?? null, $b['ends_at'] ?? null)) {
            return false;
        }

        if (! self::dateRangesOverlap(
            $a['starts_on'] ?? null,
            $a['ends_on'] ?? null,
            $b['starts_on'] ?? null,
            $b['ends_on'] ?? null,
        )) {
            return false;
        }

        if (! empty($a['teacher_id']) && ($a['teacher_id'] ?? null) === ($b['teacher_id'] ?? null)) {
            return true;
        }

        if (($a['classroom_id'] ?? null) !== ($b['classroom_id'] ?? null)) {
            return false;
        }

        $aSection = $a['section_id'] ?? null;
        $bSection = $b['section_id'] ?? null;

        return $aSection === null || $bSection === null || $aSection === $bSection;
    }

    /** حصص نفس الشعبة/الصف المتداخلة زمنياً وضمن نفس المدة مع الحصة المقترحة. */
    private function overlappingSchedules(array $slot, int $day, string $startsAt, string $endsAt, ?string $excludeScheduleId): Builder
    {
        $sectionId = $slot['section_id'] ?? null;

        return $this->applyDateWindow(
            Schedule::query()
                ->withoutGlobalScope('study_session')
                ->where('classroom_id', $slot['classroom_id'])
                ->where('day_of_week', $day)
                ->whereTime('starts_at', '<', $endsAt)
                ->whereTime('ends_at', '>', $startsAt)
                ->when($excludeScheduleId, fn (Builder $q) => $q->whereKeyNot($excludeScheduleId)),
            $slot['starts_on'] ?? null,
            $slot['ends_on'] ?? null,
        )
            // حصة الشعبة تتعارض مع حصص نفس الشعبة أو حصة الصف كاملاً،
            // وحصة الصف (بلا شعبة) تتعارض مع أي حصة في نفس الصف.
            ->when($sectionId !== null, fn (Builder $q) => $q->where(
                fn (Builder $inner) => $inner->where('section_id', $sectionId)->orWhereNull('section_id')
            ));
    }

    // =====================================================================
    // تعارضات الطالب (عند التسجيل/النقل)
    // =====================================================================

    public function studentHasConflict(Student $student, Section $section): bool
    {
        return $this->studentConflictDetails($student, $section) !== [];
    }

    /**
     * تعارضات جدول الطالب عند الانضمام لشعبة جديدة: تُقارن حصص الشعبة
     * الجديدة مع حصص شعبة الطالب الحالية وكل تسجيلاته النشطة الأخرى.
     *
     * @return array<int, string>
     */
    public function studentConflictDetails(Student $student, Section $section): array
    {
        $targetSchedules = Schedule::query()
            ->withoutGlobalScope('study_session')
            ->where('classroom_id', $section->classroom_id)
            ->where(fn (Builder $q) => $q->where('section_id', $section->id)->orWhereNull('section_id'))
            ->with(['subject:id,name'])
            ->get();

        if ($targetSchedules->isEmpty()) {
            return [];
        }

        $otherSectionIds = collect([$student->section_id])
            ->merge(
                $student->enrollments()
                    ->where('status', SectionStudentStatus::Active)
                    ->pluck('section_id')
            )
            ->filter()
            ->unique()
            ->reject(fn ($id) => $id === $section->id)
            ->values();

        if ($otherSectionIds->isEmpty()) {
            return [];
        }

        $studentSchedules = Schedule::query()
            ->withoutGlobalScope('study_session')
            ->whereIn('section_id', $otherSectionIds)
            ->with(['subject:id,name', 'section:id,name'])
            ->get();

        $messages = [];

        foreach ($studentSchedules as $existing) {
            foreach ($targetSchedules as $proposed) {
                if ($existing->day_of_week !== $proposed->day_of_week) {
                    continue;
                }

                if (! self::timesOverlap($existing->starts_at, $existing->ends_at, $proposed->starts_at, $proposed->ends_at)) {
                    continue;
                }

                if (! self::dateRangesOverlap(
                    $existing->starts_on?->toDateString(),
                    $existing->ends_on?->toDateString(),
                    $proposed->starts_on?->toDateString(),
                    $proposed->ends_on?->toDateString(),
                )) {
                    continue;
                }

                $messages[] = 'يتعارض مع حصص الطالب الحالية: '
                    .($existing->subject?->name ?? 'حصة')
                    .' في '.($existing->section?->name ?? 'شعبته')
                    .' '.self::DAY_NAMES[$existing->day_of_week]
                    .' ('.$this->window($existing->starts_at, $existing->ends_at).')';
            }
        }

        return array_values(array_unique($messages));
    }

    // =====================================================================
    // تعارضات المعلم (للعرض في بوابة الأستاذ)
    // =====================================================================

    /**
     * كل أزواج الحصص المتداخلة لمعلم واحد (نفس اليوم/الوقت) — للعرض فقط.
     *
     * @param  iterable<int, string>  $teacherIds
     * @return Collection<int, array{teacher_id: string, first: Schedule, second: Schedule}>
     */
    public function findTeacherConflicts(iterable $teacherIds): Collection
    {
        $teacherIds = collect($teacherIds)->filter()->unique()->values();

        if ($teacherIds->isEmpty()) {
            return collect();
        }

        $schedules = Schedule::query()
            ->withoutGlobalScope('study_session')
            ->whereIn('teacher_id', $teacherIds)
            ->with(['classroom:id,name', 'section:id,name', 'subject:id,name', 'program:id,name,color'])
            ->orderBy('day_of_week')
            ->orderBy('starts_at')
            ->get()
            ->groupBy('teacher_id');

        $conflicts = collect();

        foreach ($schedules as $teacherId => $rows) {
            foreach ($rows->groupBy('day_of_week') as $dayRows) {
                $dayRows = $dayRows->values();

                for ($i = 0; $i < $dayRows->count(); $i++) {
                    for ($j = $i + 1; $j < $dayRows->count(); $j++) {
                        if (self::timesOverlap(
                            $dayRows[$i]->starts_at,
                            $dayRows[$i]->ends_at,
                            $dayRows[$j]->starts_at,
                            $dayRows[$j]->ends_at
                        ) && self::dateRangesOverlap(
                            $dayRows[$i]->starts_on?->toDateString(),
                            $dayRows[$i]->ends_on?->toDateString(),
                            $dayRows[$j]->starts_on?->toDateString(),
                            $dayRows[$j]->ends_on?->toDateString(),
                        )) {
                            $conflicts->push([
                                'teacher_id' => $teacherId,
                                'first' => $dayRows[$i],
                                'second' => $dayRows[$j],
                            ]);
                        }
                    }
                }
            }
        }

        return $conflicts->values();
    }

    // =====================================================================
    // تعارضات التأجيل
    // =====================================================================

    /**
     * فحص الموعد الجديد لحصة مؤجلة: حصص المعلم الأسبوعية، تأجيلاته الأخرى،
     * حصص نفس الشعبة، وتأجيلات نفس الشعبة.
     *
     * @return array<int, array{field: string, message: string}>
     */
    public function postponementConflicts(
        Schedule $schedule,
        string $date,
        ?string $startsAt,
        ?string $endsAt,
        ?string $excludeClassSessionId = null,
    ): array {
        $startsAt = self::normalizeTime($startsAt);
        $endsAt = self::normalizeTime($endsAt);

        if ($startsAt === null || $endsAt === null) {
            return [['field' => 'postponed_starts_at', 'message' => 'حدد وقت البداية والنهاية للموعد الجديد']];
        }

        $parsed = Carbon::parse($date);
        $day = $parsed->dayOfWeek;
        $conflicts = [];

        // 1) حصص المعلم الأسبوعية الأخرى السارية في الموعد الجديد.
        if ($schedule->teacher_id) {
            $teacherClash = $this->applyDateWindow(
                Schedule::query()
                    ->withoutGlobalScope('study_session')
                    ->where('teacher_id', $schedule->teacher_id)
                    ->whereKeyNot($schedule->id)
                    ->where('day_of_week', $day)
                    ->whereTime('starts_at', '<', $endsAt)
                    ->whereTime('ends_at', '>', $startsAt),
                $parsed->toDateString(),
                $parsed->toDateString(),
            )
                ->with(['section:id,name', 'classroom:id,name'])
                ->first();

            if ($teacherClash) {
                $conflicts[] = [
                    'field' => 'postponed_starts_at',
                    'message' => 'المعلم لديه حصة أخرى في الموعد الجديد ('
                        .self::DAY_NAMES[$day].' '.$this->window($startsAt, $endsAt).') في '
                        .($teacherClash->section?->name ?? $teacherClash->classroom?->name),
                ];
            }

            // 2) تأجيلات أخرى لنفس المعلم في نفس اليوم.
            $postponedClash = ClassSession::query()
                ->withoutGlobalScope('study_session')
                ->where('teacher_id', $schedule->teacher_id)
                ->where('status', SessionStatus::Postponed)
                ->whereDate('postponed_date', $parsed->toDateString())
                ->when($excludeClassSessionId, fn (Builder $q) => $q->whereKeyNot($excludeClassSessionId))
                ->whereTime('postponed_starts_at', '<', $endsAt)
                ->whereTime('postponed_ends_at', '>', $startsAt)
                ->first();

            if ($postponedClash) {
                $conflicts[] = [
                    'field' => 'postponed_starts_at',
                    'message' => 'لدى المعلم حصة مؤجلة أخرى إلى نفس الموعد ('
                        .$this->window($postponedClash->postponed_starts_at, $postponedClash->postponed_ends_at).')',
                ];
            }
        }

        // 3) حصص نفس الشعبة/الصف السارية في الموعد الجديد.
        $sectionClash = $this->applyDateWindow(
            Schedule::query()
                ->withoutGlobalScope('study_session')
                ->where('classroom_id', $schedule->classroom_id)
                ->whereKeyNot($schedule->id)
                ->where('day_of_week', $day)
                ->whereTime('starts_at', '<', $endsAt)
                ->whereTime('ends_at', '>', $startsAt)
                ->when($schedule->section_id !== null, fn (Builder $q) => $q->where(
                    fn (Builder $inner) => $inner->where('section_id', $schedule->section_id)->orWhereNull('section_id')
                )),
            $parsed->toDateString(),
            $parsed->toDateString(),
        )
            ->with(['subject:id,name'])
            ->first();

        if ($sectionClash) {
            $conflicts[] = [
                'field' => 'postponed_starts_at',
                'message' => 'يوجد حصة أخرى لنفس الشعبة في الموعد الجديد ('
                    .$this->window($sectionClash->starts_at, $sectionClash->ends_at).')'
                    .($sectionClash->subject?->name ? ' — '.$sectionClash->subject->name : ''),
            ];
        }

        // 4) تأجيلات أخرى لنفس الشعبة في نفس اليوم.
        $sectionPostponedClash = ClassSession::query()
            ->withoutGlobalScope('study_session')
            ->where('section_id', $schedule->section_id)
            ->where('status', SessionStatus::Postponed)
            ->whereDate('postponed_date', $parsed->toDateString())
            ->when($excludeClassSessionId, fn (Builder $q) => $q->whereKeyNot($excludeClassSessionId))
            ->whereTime('postponed_starts_at', '<', $endsAt)
            ->whereTime('postponed_ends_at', '>', $startsAt)
            ->when($schedule->section_id === null, fn (Builder $q) => $q->whereNull('section_id'))
            ->first();

        if ($sectionPostponedClash) {
            $conflicts[] = [
                'field' => 'postponed_starts_at',
                'message' => 'توجد حصة مؤجلة أخرى لنفس الشعبة في نفس الموعد',
            ];
        }

        return $conflicts;
    }

    // =====================================================================
    // نقل الصف بين الدوامات
    // =====================================================================

    /**
     * فحص تعارضات معلم عند نقل صف (وشعبه/جداوله) إلى دوام آخر:
     * كل حصة منقولة تُقارن بحصص المعلم التي لن تُنقل.
     *
     * @return array<int, array{field: string, message: string}>
     */
    public function classroomShiftConflicts(Classroom $classroom): array
    {
        $sectionIds = Section::query()
            ->withoutGlobalScope('study_session')
            ->where('classroom_id', $classroom->id)
            ->pluck('id');

        $moving = Schedule::query()
            ->withoutGlobalScope('study_session')
            ->where(fn (Builder $q) => $q->where('classroom_id', $classroom->id)->orWhereIn('section_id', $sectionIds))
            ->get();

        if ($moving->isEmpty()) {
            return [];
        }

        $movingIds = $moving->pluck('id');
        $conflicts = [];

        foreach ($moving as $schedule) {
            $clash = $this->applyDateWindow(
                Schedule::query()
                    ->withoutGlobalScope('study_session')
                    ->where('teacher_id', $schedule->teacher_id)
                    ->whereNotIn('id', $movingIds)
                    ->where('day_of_week', $schedule->day_of_week)
                    ->whereTime('starts_at', '<', $schedule->ends_at)
                    ->whereTime('ends_at', '>', $schedule->starts_at),
                $schedule->starts_on?->toDateString(),
                $schedule->ends_on?->toDateString(),
            )
                ->with(['section:id,name', 'classroom:id,name'])
                ->first();

            if ($clash) {
                $conflicts[] = [
                    'field' => 'study_session_id',
                    'message' => 'نقل الصف يسبب تعارضاً للمعلم '.self::DAY_NAMES[$schedule->day_of_week]
                        .' ('.$this->window($schedule->starts_at, $schedule->ends_at).') مع '
                        .($clash->section?->name ?? $clash->classroom?->name),
                ];
            }
        }

        return $conflicts;
    }

    // =====================================================================
    // القفل والحماية من التزامن
    // =====================================================================

    /**
     * قفل MySQL مسمّى حول كتابة الجدول (مفاتيح مثل teacher:{id} وsection:{id}).
     * على SQLite (بيئة الاختبارات) يُنفَّذ مباشرة بدون قفل.
     *
     * @param  iterable<int, string>  $keys
     */
    public function withScheduleLock(iterable $keys, Closure $callback): mixed
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'mysql' && $driver !== 'mariadb') {
            return $callback();
        }

        $names = collect($keys)
            ->filter()
            ->map(fn ($key) => 'sch_'.substr(hash('sha256', (string) $key), 0, 56))
            ->unique()
            ->sort()
            ->values();

        $acquired = [];

        try {
            foreach ($names as $name) {
                $result = DB::selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$name]);

                if ((int) ($result->acquired ?? 0) !== 1) {
                    throw new RuntimeException('تعذر قفل الجدول حالياً، أعد المحاولة');
                }

                $acquired[] = $name;
            }

            return $callback();
        } finally {
            foreach ($acquired as $name) {
                DB::select('SELECT RELEASE_LOCK(?)', [$name]);
            }
        }
    }

    // =====================================================================
    // أدوات مساعدة
    // =====================================================================

    /** @param array<int, array{field: string, message: string}> $conflicts */
    private function raise(array $conflicts): void
    {
        if ($conflicts === []) {
            return;
        }

        $messages = [];

        foreach ($conflicts as $conflict) {
            $messages[$conflict['field']][] = $conflict['message'];
        }

        throw ScheduleConflictException::withMessages($messages);
    }

    private function window(?string $startsAt, ?string $endsAt): string
    {
        $startsAt = self::normalizeTime($startsAt);
        $endsAt = self::normalizeTime($endsAt);

        return substr((string) $startsAt, 0, 5).'–'.substr((string) $endsAt, 0, 5);
    }
}
