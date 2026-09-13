<?php

namespace App\Services;

use App\Enums\AttributeOptionSource;
use App\Enums\CustomFieldType;
use App\Enums\ScheduleProgramType;
use App\Models\Program;
use App\Models\ProgramAttribute;
use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * تخصصات الجداول (programs): provisioning، الفترات، والخصائص المخصصة.
 *
 * Mirrors the conventions of StudySessionService (provisioning) and
 * CustomFieldService (typed custom fields) so programs stay consistent
 * with the rest of the app.
 */
class ProgramService
{
    /** أنماط وسم خيارات الطلاب. */
    public const STUDENT_LABEL_MODES = ['name', 'name_age', 'name_juz', 'name_age_juz'];

    /** البرامج الافتراضية لكل جامع (قابلة للتعديل والحذف). */
    public const DEFAULT_PROGRAMS = [
        ['name' => 'برنامج التحفيظ', 'code' => 'tahfeez', 'type' => ScheduleProgramType::Tahfeez],
        ['name' => 'برنامج الإجازة', 'code' => 'ijazah', 'type' => ScheduleProgramType::Ijazah],
        ['name' => 'اختبارات الحفظ', 'code' => 'hafiz_exams', 'type' => ScheduleProgramType::HafizExams],
        ['name' => 'الدورات الشرعية', 'code' => 'sharia_courses', 'type' => ScheduleProgramType::ShariaCourses],
        ['name' => 'البرامج القرآنية', 'code' => 'quran', 'type' => ScheduleProgramType::Quran],
    ];

    /**
     * Create the five default programs for a mosque (idempotent).
     */
    public function provisionTenantPrograms(Tenant $tenant): void
    {
        $exists = Program::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->exists();

        if ($exists) {
            return;
        }

        foreach (self::DEFAULT_PROGRAMS as $index => $definition) {
            Program::create([
                'tenant_id' => $tenant->id,
                'name' => $definition['name'],
                'code' => $definition['code'],
                'type' => $definition['type'],
                'color' => $definition['type']->color(),
                'is_active' => true,
                'sort_order' => $index,
            ]);
        }
    }

    /** Generate a unique program code inside a mosque when the admin leaves it blank. */
    public function uniqueCode(string $tenantId, ?string $hint = null): string
    {
        $base = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '_', (string) $hint)));

        if ($base === '' || $base === '_') {
            $base = 'program_'.now()->format('YmdHis');
        }

        $candidate = $base;
        $suffix = 1;

        while (Program::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('code', $candidate)
            ->exists()) {
            $candidate = $base.'_'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * Sync the program periods from a form payload (create/update/delete).
     *
     * @param  array<int, array{id?: string|null, name?: string|null, starts_at?: string|null, ends_at?: string|null, sort_order?: int|string|null, is_active?: mixed}>  $periods
     *
     * @throws ValidationException
     */
    public function syncPeriods(Program $program, array $periods): void
    {
        $this->assertPeriodsDoNotOverlap($periods);

        $keptIds = [];

        DB::transaction(function () use ($program, $periods, &$keptIds) {
            foreach (array_values($periods) as $index => $row) {
                $name = trim((string) ($row['name'] ?? ''));

                if ($name === '') {
                    continue;
                }

                $payload = [
                    'name' => $name,
                    'starts_at' => $row['starts_at'] ?: null,
                    'ends_at' => $row['ends_at'] ?: null,
                    'sort_order' => (int) ($row['sort_order'] ?? $index),
                    'is_active' => ! empty($row['is_active']),
                ];

                $period = ! empty($row['id'])
                    ? $program->periods()->whereKey($row['id'])->first()
                    : null;

                if ($period) {
                    $period->update($payload);
                } else {
                    $period = $program->periods()->create($payload + ['tenant_id' => $program->tenant_id]);
                }

                $keptIds[] = $period->id;
            }

            $removed = $program->periods()
                ->when($keptIds !== [], fn ($q) => $q->whereNotIn('id', $keptIds))
                ->get();

            foreach ($removed as $period) {
                if ($period->schedules()->withoutGlobalScope('study_session')->exists()) {
                    throw ValidationException::withMessages([
                        'periods' => "لا يمكن حذف الفترة «{$period->name}» لوجود حصص مرتبطة بها — عطّلها بدل ذلك",
                    ]);
                }

                $period->delete();
            }
        });
    }

    /**
     * Sync the program custom attributes (definitions + values).
     *
     * @param  array<int, array{id?: string|null, name?: string|null, field_key?: string|null, field_type?: string|null, required?: mixed, options?: mixed, options_source?: string|null, options_config?: mixed, value?: mixed, sort_order?: int|string|null, is_active?: mixed}>  $attributes
     *
     * @throws ValidationException
     */
    public function syncAttributes(Program $program, array $attributes): void
    {
        DB::transaction(function () use ($program, $attributes) {
            $keptIds = [];
            $usedKeys = [];

            foreach (array_values($attributes) as $index => $row) {
                $name = trim((string) ($row['name'] ?? ''));

                if ($name === '') {
                    continue;
                }

                $type = CustomFieldType::tryFrom((string) ($row['field_type'] ?? '')) ?? CustomFieldType::Text;
                $required = ! empty($row['required']);
                $source = AttributeOptionSource::tryFrom((string) ($row['options_source'] ?? ''))
                    ?? AttributeOptionSource::Manual;

                $config = $source === AttributeOptionSource::Students
                    ? $this->normaliseOptionsConfig($row['options_config'] ?? null, $index)
                    : null;

                $options = $source === AttributeOptionSource::Students
                    ? $this->studentOptions($program->tenant_id, $config ?? [])
                    : $this->parseOptions($row['options'] ?? null, $type);

                $key = trim((string) ($row['field_key'] ?? ''));

                if ($key !== '' && in_array($key, $usedKeys, true)) {
                    throw ValidationException::withMessages([
                        "attributes.{$index}.field_key" => 'مفتاح الحقل مستخدم أكثر من مرة',
                    ]);
                }

                $key = $key !== '' ? $key : $this->uniqueKey($program, $name, $usedKeys);
                $usedKeys[] = $key;

                $value = $this->normaliseValue($type, $row['value'] ?? null, $options, $required, $name, $index);

                $payload = [
                    'name' => $name,
                    'field_key' => $key,
                    'field_type' => $type,
                    'required' => $required,
                    'options' => $source === AttributeOptionSource::Manual ? $options : null,
                    'options_source' => $source,
                    'options_config' => $config,
                    'value' => $value,
                    'sort_order' => (int) ($row['sort_order'] ?? $index),
                    'is_active' => ! empty($row['is_active']),
                ];

                $attribute = ! empty($row['id'])
                    ? $program->attributes()->whereKey($row['id'])->first()
                    : null;

                if ($attribute) {
                    $attribute->update($payload);
                } else {
                    $attribute = $program->attributes()->create($payload + ['tenant_id' => $program->tenant_id]);
                }

                $keptIds[] = $attribute->id;
            }

            $program->attributes()
                ->when($keptIds !== [], fn ($q) => $q->whereNotIn('id', $keptIds))
                ->get()
                ->each(fn (ProgramAttribute $attribute) => $attribute->delete());
        });
    }

    /** @return Collection<int, array{attribute: ProgramAttribute, value: mixed, display: string}> */
    public function displayedAttributes(Program $program): Collection
    {
        return $program->attributes()
            ->where('is_active', true)
            ->get()
            ->map(function (ProgramAttribute $attribute) {
                $value = $this->deserialise($attribute);

                return [
                    'attribute' => $attribute,
                    'value' => $value,
                    'display' => $this->toDisplay($attribute, $value),
                ];
            })
            ->filter(fn (array $row) => $row['value'] !== null && $row['value'] !== '');
    }

    /** Convert a stored attribute value to its natural PHP representation. */
    public function deserialise(ProgramAttribute $attribute): mixed
    {
        $stored = $attribute->value;

        if ($stored === null || $stored === '') {
            return null;
        }

        return match ($attribute->field_type) {
            CustomFieldType::Boolean => $stored === '1',
            CustomFieldType::Number => is_numeric($stored) ? (float) $stored : $stored,
            CustomFieldType::Multiselect => json_decode($stored, true) ?? [],
            default => $stored,
        };
    }

    /** Human-readable display string for a natural value. */
    public function toDisplay(ProgramAttribute $attribute, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($attribute->field_type) {
            CustomFieldType::Boolean => $value ? 'نعم' : 'لا',
            CustomFieldType::Multiselect => implode('، ', array_map('strval', (array) $value)),
            default => (string) $value,
        };
    }

    /**
     * الخيارات الفعلية للخصيصة: يدوية من عمود options، أو محسوبة من الطلاب
     * حسب المرشّحات المخزّنة في options_config.
     *
     * @return array<int, string>
     */
    public function resolvedOptions(ProgramAttribute $attribute): array
    {
        if ($attribute->options_source === AttributeOptionSource::Students) {
            return $this->studentOptions($attribute->tenant_id, $attribute->options_config ?? []);
        }

        return $attribute->options ?? [];
    }

    /**
     * صفوف الطلاب المتاحين للاختيار في نموذج البرنامج (كل طلاب الجامع،
     * بمعزل عن الدوام النشط لأن المرشّح يحدّد المجموعة بنفسه).
     *
     * @return array<int, array{name: string, gender: string|null, status: string|null, classroom_id: string|null, age: int|null, juz: float|null}>
     */
    public function studentOptionRows(string $tenantId): array
    {
        return $this->availableStudents($tenantId, ['student_status' => 'all'])
            ->map(fn (Student $student) => [
                'name' => $student->name,
                'gender' => $student->gender,
                'status' => $student->status,
                'classroom_id' => $student->classroom_id,
                'age' => $student->birth_date?->age,
                'juz' => $student->memorized_juz !== null ? (float) $student->memorized_juz : null,
            ])
            ->values()
            ->all();
    }

    /**
     * الطلاب المطابقون لمرشّحات الخصيصة.
     *
     * @param  array<string, mixed>  $config
     * @return Collection<int, Student>
     */
    public function availableStudents(string $tenantId, array $config = []): Collection
    {
        $today = now()->startOfDay();

        return Student::query()
            ->withoutGlobalScopes(['tenant', 'study_session'])
            ->where('tenant_id', $tenantId)
            ->when(($config['student_status'] ?? 'active') === 'active', fn ($query) => $query->where('status', 'active'))
            ->when(($config['gender'] ?? 'all') !== 'all', fn ($query) => $query->where('gender', $config['gender']))
            ->when(! empty($config['classroom_id']), fn ($query) => $query->where('classroom_id', $config['classroom_id']))
            ->when(isset($config['age_from']), fn ($query) => $query->whereDate('birth_date', '<=', $today->copy()->subYears((int) $config['age_from'])))
            ->when(isset($config['age_to']), fn ($query) => $query->whereDate('birth_date', '>', $today->copy()->subYears((int) $config['age_to'] + 1)))
            ->when(isset($config['juz_from']), fn ($query) => $query->where('memorized_juz', '>=', $config['juz_from']))
            ->when(isset($config['juz_to']), fn ($query) => $query->where('memorized_juz', '<=', $config['juz_to']))
            ->orderBy('name')
            ->get();
    }

    /**
     * وسوم خيارات الطلاب بعد تطبيق المرشّحات.
     *
     * @param  array<string, mixed>  $config
     * @return array<int, string>
     */
    public function studentOptions(string $tenantId, array $config): array
    {
        return $this->availableStudents($tenantId, $config)
            ->map(fn (Student $student) => $this->studentLabel($student, $config['label_mode'] ?? 'name'))
            ->values()
            ->all();
    }

    /** وسم الطالب حسب النمط المختار: الاسم، ومعه العمر و/أو عدد الأجزاء. */
    private function studentLabel(Student $student, ?string $mode): string
    {
        $mode = $mode ?: 'name';
        $parts = [];

        if (str_contains($mode, 'age')) {
            $age = $student->birth_date?->age;

            if ($age !== null) {
                $parts[] = $age.' سنة';
            }
        }

        if (str_contains($mode, 'juz') && $student->memorized_juz !== null) {
            $parts[] = $this->formatJuz((float) $student->memorized_juz).' جزء';
        }

        return $parts === [] ? $student->name : $student->name.' — '.implode(' — ', $parts);
    }

    /** 30.0 → «30»، 12.5 → «12.5». */
    private function formatJuz(float $juz): string
    {
        return rtrim(rtrim(number_format($juz, 1, '.', ''), '0'), '.');
    }

    /**
     * تنظيف مرشّحات خيارات الطلاب والتحقق من منطقيتها.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function normaliseOptionsConfig(mixed $raw, int $index): array
    {
        $raw = is_array($raw) ? $raw : [];

        $status = (string) ($raw['student_status'] ?? 'active');
        $gender = (string) ($raw['gender'] ?? 'all');
        $labelMode = (string) ($raw['label_mode'] ?? 'name');

        $config = [
            'student_status' => in_array($status, ['all', 'active'], true) ? $status : 'active',
            'gender' => in_array($gender, ['all', 'male', 'female'], true) ? $gender : 'all',
            'label_mode' => in_array($labelMode, self::STUDENT_LABEL_MODES, true) ? $labelMode : 'name',
        ];

        foreach (['age_from', 'age_to'] as $key) {
            if (isset($raw[$key]) && $raw[$key] !== '' && is_numeric($raw[$key])) {
                $config[$key] = max(0, (int) $raw[$key]);
            }
        }

        foreach (['juz_from', 'juz_to'] as $key) {
            if (isset($raw[$key]) && $raw[$key] !== '' && is_numeric($raw[$key])) {
                $config[$key] = max(0.0, min(30.0, (float) $raw[$key]));
            }
        }

        if (! empty($raw['classroom_id']) && is_string($raw['classroom_id'])) {
            $config['classroom_id'] = $raw['classroom_id'];
        }

        if (isset($config['age_from'], $config['age_to']) && $config['age_from'] > $config['age_to']) {
            throw ValidationException::withMessages([
                "attributes.{$index}.options_config.age_from" => '«العمر من» يجب أن يكون أصغر من «العمر إلى»',
            ]);
        }

        if (isset($config['juz_from'], $config['juz_to']) && $config['juz_from'] > $config['juz_to']) {
            throw ValidationException::withMessages([
                "attributes.{$index}.options_config.juz_from" => '«الأجزاء من» يجب أن يكون أصغر من «الأجزاء إلى»',
            ]);
        }

        return $config;
    }

    /** Generate a unique attribute key inside a program. */
    private function uniqueKey(Program $program, string $name, array $pending = []): string
    {
        $base = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $name)));

        if ($base === '' || $base === '_') {
            $base = 'attr_'.now()->format('YmdHis');
        }

        $candidate = $base;
        $suffix = 1;

        while (in_array($candidate, $pending, true)
            || ProgramAttribute::withoutGlobalScope('tenant')
                ->where('program_id', $program->id)
                ->where('field_key', $candidate)
                ->exists()) {
            $candidate = $base.'_'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /** @return array<int, string>|null */
    private function parseOptions(mixed $raw, CustomFieldType $type): ?array
    {
        if (! $type->hasOptions()) {
            return null;
        }

        if (is_array($raw)) {
            $options = $raw;
        } else {
            $options = preg_split('/[\r\n,]+/u', (string) $raw) ?: [];
        }

        $options = array_values(array_filter(array_map('trim', $options), fn ($option) => $option !== ''));

        return $options === [] ? null : $options;
    }

    /**
     * Validate and normalise a raw attribute value for storage.
     *
     * @param  array<int, string>|null  $options
     *
     * @throws ValidationException
     */
    private function normaliseValue(
        CustomFieldType $type,
        mixed $value,
        ?array $options,
        bool $required,
        string $name,
        int $index,
    ): ?string {
        // The Blade form submits multiselect values as comma-separated text.
        if ($type === CustomFieldType::Multiselect && is_string($value)) {
            $value = array_values(array_filter(
                array_map('trim', preg_split('/[,،]+/u', $value) ?: []),
                fn ($item) => $item !== ''
            ));
        }

        $isEmpty = $value === null || $value === '' || (is_array($value) && $value === []);

        if ($isEmpty) {
            if ($required) {
                throw ValidationException::withMessages([
                    "attributes.{$index}.value" => "الخصيصة «{$name}» مطلوبة",
                ]);
            }

            return null;
        }

        $rules = match ($type) {
            CustomFieldType::Text => ['string', 'max:255'],
            CustomFieldType::Textarea => ['string', 'max:5000'],
            CustomFieldType::Number => ['numeric'],
            CustomFieldType::Date => ['date'],
            CustomFieldType::Boolean => ['boolean'],
            CustomFieldType::Select => ['string', Rule::in($options ?? [])],
            CustomFieldType::Multiselect => ['array', Rule::in($options ?? [])],
        };

        $validator = validator(
            ['value' => $value],
            ['value' => $rules],
            [],
            ['value' => "الخصيصة «{$name}»"]
        );

        if ($validator->fails()) {
            throw ValidationException::withMessages([
                "attributes.{$index}.value" => $validator->errors()->first('value'),
            ]);
        }

        return match ($type) {
            CustomFieldType::Boolean => $value ? '1' : '0',
            CustomFieldType::Multiselect => json_encode(array_values((array) $value)),
            default => (string) $value,
        };
    }

    /**
     * Ensure periods inside one program do not overlap (only complete ranges).
     *
     * @throws ValidationException
     */
    private function assertPeriodsDoNotOverlap(array $periods): void
    {
        $ranges = [];

        foreach (array_values($periods) as $index => $row) {
            $start = $row['starts_at'] ?? null;
            $end = $row['ends_at'] ?? null;

            if (! $start || ! $end || empty($row['is_active'])) {
                continue;
            }

            $ranges[] = ['index' => $index, 'start' => $start, 'end' => $end, 'name' => trim((string) ($row['name'] ?? ''))];
        }

        usort($ranges, fn ($a, $b) => strcmp($a['start'], $b['start']));

        for ($i = 1; $i < count($ranges); $i++) {
            if (strcmp($ranges[$i]['start'], $ranges[$i - 1]['end']) < 0) {
                throw ValidationException::withMessages([
                    "periods.{$ranges[$i]['index']}.starts_at" => "الفترة «{$ranges[$i]['name']}» تتداخل مع الفترة السابقة",
                ]);
            }
        }
    }

    /** Whether a program can be deleted (no schedule entries attached). */
    public function canDelete(Program $program): bool
    {
        return ! $program->schedules()->withoutGlobalScope('study_session')->exists();
    }

    /**
     * البرامج المتاحة لدوام معيّن: إن كان للدوام قائمة برامج محددة تُعرض وحدها،
     * وإن لم تُحدد له أي برنامج تظهر كل البرامج المفعّلة (سلوك «كل الدوامات»).
     *
     * @param  array<string, mixed>  $with
     * @return Collection<int, Program>
     */
    public function availablePrograms(?string $studySessionId, array $with = []): Collection
    {
        $query = Program::query()->active();

        if ($with !== []) {
            $query->with($with);
        }

        if ($this->sessionRestrictsPrograms($studySessionId)) {
            $query->whereHas('studySessions', fn ($q) => $q->where('study_sessions.id', $studySessionId));
        }

        return $query->orderBy('sort_order')->orderBy('name')->get();
    }

    /** هل للدوام قائمة برامج محددة؟ (لا ارتباطات = كل البرامج متاحة) */
    public function sessionRestrictsPrograms(?string $studySessionId): bool
    {
        if (blank($studySessionId)) {
            return false;
        }

        return DB::table('program_study_session')
            ->where('study_session_id', $studySessionId)
            ->exists();
    }

    /**
     * خريطة تقييد البرامج لكل دوام: [study_session_id => [program_id, ...]]
     * للدوامات المقيّدة فقط؛ الدوام الغائب من الخريطة = كل البرامج.
     *
     * @return array<string, array<int, string>>
     */
    public function sessionProgramMap(): array
    {
        $tenantId = config('app.current_tenant_id');

        return DB::table('program_study_session')
            ->join('study_sessions', 'study_sessions.id', '=', 'program_study_session.study_session_id')
            ->when($tenantId !== null, fn ($q) => $q->where('study_sessions.tenant_id', $tenantId))
            ->get(['program_study_session.study_session_id', 'program_study_session.program_id'])
            ->groupBy('study_session_id')
            ->map(fn ($rows) => $rows->pluck('program_id')->values()->all())
            ->all();
    }

    /** هل البرنامج مسموح في الدوام المحدد؟ (بلا دوام أو بلا قيود = مسموح) */
    public function programAllowedInSession(?string $programId, ?string $studySessionId): bool
    {
        if (blank($programId) || blank($studySessionId) || ! $this->sessionRestrictsPrograms($studySessionId)) {
            return true;
        }

        return DB::table('program_study_session')
            ->where('study_session_id', $studySessionId)
            ->where('program_id', $programId)
            ->exists();
    }
}
