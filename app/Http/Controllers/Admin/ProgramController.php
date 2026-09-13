<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\Program\SaveProgramAction;
use App\Enums\AttributeOptionSource;
use App\Enums\CustomFieldType;
use App\Enums\ScheduleProgramType;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Program;
use App\Services\AuditLogger;
use App\Services\ProgramService;
use App\Support\ProgramRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * تخصصات الجداول (programs): برامج التحفيظ والإجازة واختبارات الحفظ
 * والدورات الشرعية والبرامج القرآنية، مع فتراتها وخصائصها المخصصة.
 */
class ProgramController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ProgramService $service,
    ) {}

    public function index(Request $request): View
    {
        $type = ScheduleProgramType::tryFrom((string) $request->input('type'));
        $search = $request->input('search');

        $programs = Program::query()
            ->with('studySessions:id,name')
            ->withCount(['periods', 'attributes', 'schedules'])
            ->when($search, fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($type, fn ($query) => $query->where('type', $type->value))
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->boolean('status')))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.programs.index', [
            'programs' => $programs,
            'programTypes' => ScheduleProgramType::cases(),
            'type' => $type,
            'search' => $search,
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.programs.form', $this->formData($request, null, [
            'periodRows' => old('periods', []),
            'attributeRows' => old('attributes', []),
        ]));
    }

    public function store(Request $request, SaveProgramAction $save): RedirectResponse
    {
        $data = $this->validated($request);

        $program = $save->create($data, $this->tenantId($request));

        $this->audit->logModel('program.created', $program, actor: $request->user());

        return redirect()
            ->route('admin.programs.index')
            ->with('success', "تم إنشاء البرنامج «{$program->name}»");
    }

    public function edit(Request $request, Program $program): View
    {
        return view('admin.programs.form', $this->formData($request, $program, [
            'periodRows' => old('periods', $program->periods->map(fn ($period) => [
                'id' => $period->id,
                'name' => $period->name,
                'starts_at' => $period->starts_at ? substr($period->starts_at, 0, 5) : null,
                'ends_at' => $period->ends_at ? substr($period->ends_at, 0, 5) : null,
                'sort_order' => $period->sort_order,
                'is_active' => $period->is_active,
            ])->all()),
            'attributeRows' => old('attributes', $program->attributes->map(function ($attribute) {
                $value = $this->service->deserialise($attribute);

                return [
                    'id' => $attribute->id,
                    'name' => $attribute->name,
                    'field_key' => $attribute->field_key,
                    'field_type' => $attribute->field_type->value,
                    'required' => $attribute->required,
                    'options' => implode("\n", $attribute->options ?? []),
                    'options_source' => $attribute->options_source->value,
                    'options_config' => $attribute->options_config ?? [],
                    'value' => is_array($value) ? $value : ($value === true ? '1' : ($value === false ? '0' : $value)),
                    'sort_order' => $attribute->sort_order,
                    'is_active' => $attribute->is_active,
                ];
            })->all()),
        ]));
    }

    public function update(Request $request, Program $program, SaveProgramAction $save): RedirectResponse
    {
        $data = $this->validated($request, $program);
        $before = $program->getAttributes();

        $save->update($program, $data);

        $this->audit->logModel('program.updated', $program, $before, actor: $request->user());

        return redirect()
            ->route('admin.programs.index')
            ->with('success', "تم تحديث البرنامج «{$program->name}»");
    }

    public function destroy(Request $request, Program $program): RedirectResponse
    {
        if (! $this->service->canDelete($program)) {
            return back()->withErrors([
                'program' => 'لا يمكن حذف برنامج له حصص في الجداول — عطّله بدل ذلك أو احذف حصصه أولاً',
            ]);
        }

        $this->audit->logModel('program.deleted', $program, actor: $request->user());
        $program->delete();

        return redirect()
            ->route('admin.programs.index')
            ->with('success', 'تم حذف البرنامج');
    }

    private function validated(Request $request, ?Program $program = null): array
    {
        $data = $request->validate(ProgramRules::rules($this->tenantId($request), $program));

        // The Blade form always owns its period/attribute rows: an absent key
        // means the admin removed every row, so sync with an empty list.
        return $data + ['periods' => [], 'attributes' => []];
    }

    /**
     * الجامع الحالي: مدير الجوامع داخل جامع ليس له tenant_id، فيُستمد من
     * سياق الجامع الذي دخله (InitializeTenant).
     */
    private function tenantId(Request $request): string
    {
        $tenantId = config('app.current_tenant_id') ?? $request->user()->tenant_id;

        abort_if(blank($tenantId), 403, 'لا يوجد جامع محدد — اختر جامعاً أولاً');

        return (string) $tenantId;
    }

    /**
     * بيانات نموذج البرنامج المشتركة بين الإنشاء والتعديل، مع قوائم
     * الطلاب والصفوف اللازمة لمرشّحات خيارات الخصائص.
     *
     * @param  array<string, mixed>  $rows
     * @return array<string, mixed>
     */
    private function formData(Request $request, ?Program $program, array $rows): array
    {
        $tenantId = $this->tenantId($request);

        return $rows + [
            'program' => $program,
            'programTypes' => ScheduleProgramType::cases(),
            'fieldTypes' => CustomFieldType::cases(),
            'optionSources' => AttributeOptionSource::cases(),
            'studentOptions' => $this->service->studentOptionRows($tenantId),
            'classrooms' => Classroom::query()->orderBy('name')->get(['id', 'name']),
        ];
    }
}
