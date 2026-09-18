<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HourlyRate;
use App\Models\Teacher;
use App\Services\AuditLogger;
use App\Services\HourlyRateResolver;
use App\Services\PayrollPeriodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * سجل أسعار الساعة التاريخي: إضافة/عرض/حذف مع منع التداخل، وتعديل السعر
 * لا يمس الكشوف المغلقة (اللقطات ثابتة).
 */
class HourlyRateController extends Controller
{
    public function __construct(
        private readonly HourlyRateResolver $resolver,
        private readonly PayrollPeriodService $payroll,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();

        $rates = HourlyRate::query()
            ->with(['teacher:id,name', 'creator:id,name'])
            ->when($search !== '', fn ($query) => $query->whereHas(
                'teacher',
                fn ($teacher) => $teacher->where('name', 'like', "%{$search}%")
            ))
            ->orderByDesc('effective_from')
            ->orderBy('teacher_id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.payroll.rates', [
            'rates' => $rates,
            'teachers' => Teacher::query()->orderBy('name')->get(['id', 'name', 'pay_type']),
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'teacher_id' => ['required', 'uuid'],
            'rate' => ['required', 'numeric', 'gt:0'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
        ]);

        $teacher = Teacher::query()->findOrFail($data['teacher_id']);

        try {
            $this->resolver->assertNoOverlap($teacher, $data['effective_from'], $data['effective_to'] ?? null);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $rate = HourlyRate::create([
            'tenant_id' => $teacher->tenant_id,
            'teacher_id' => $teacher->id,
            'rate' => $data['rate'],
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $this->audit->logModel('hourly_rate.created', $rate, actor: $request->user());
        $this->payroll->refreshOpenForTeacher($teacher);

        return back()->with('success', 'تمت إضافة سعر الساعة');
    }

    public function destroy(Request $request, HourlyRate $hourlyRate): RedirectResponse
    {
        $teacher = $hourlyRate->teacher;

        $this->audit->logModel('hourly_rate.deleted', $hourlyRate, actor: $request->user());
        $hourlyRate->delete();

        if ($teacher !== null) {
            $this->payroll->refreshOpenForTeacher($teacher);
        }

        return back()->with('success', 'تم حذف سعر الساعة (الكشوف المغلقة لا تتأثر)');
    }
}
