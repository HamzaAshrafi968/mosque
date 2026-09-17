<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Services\AuditLogger;
use App\Services\FinanceService;
use App\Services\PayrollService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * رواتب المعلمين من جهة مدير الجامع: تحديد الراتب الشهري، متابعة ساعات العمل
 * الشهرية والعدّاد منذ آخر دفعة، وتسجيل الدفعات (كل دفعة تصفّر العدّاد).
 */
class PayrollController extends Controller
{
    public function __construct(
        private readonly PayrollService $payroll,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $monthInput = $request->validate(['month' => ['nullable', 'date_format:Y-m']])['month'] ?? null;
        $month = $monthInput
            ? CarbonImmutable::createFromFormat('Y-m', $monthInput)->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();

        $search = $request->string('q')->toString();

        $teachers = Teacher::query()
            ->with(['studySession:id,name', 'studySessions:id,name', 'workHours'])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.payroll.index', [
            'teachers' => $teachers,
            'summaries' => $this->payroll->summaries($teachers->getCollection(), $month),
            'month' => $month,
            'monthInput' => $month->format('Y-m'),
            'search' => $search,
            'currency' => FinanceService::DEFAULT_CURRENCY,
        ]);
    }

    /** تسجيل دفعة للأستاذ — تُصفّر عدّاد الساعات وتُرسل إشعاراً له. */
    public function pay(Request $request, Teacher $teacher): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->payroll->pay($teacher, $data['amount'], $data['description'] ?? null, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with(
            'success',
            $teacher->user_id
                ? 'تم تسجيل الدفعة وأُرسل إشعار للأستاذ'
                : 'تم تسجيل الدفعة (الأستاذ بلا حساب دخول — لا إشعار)'
        );
    }

    /** تحديد/تعديل الراتب الشهري للأستاذ. */
    public function updateSalary(Request $request, Teacher $teacher): RedirectResponse
    {
        $data = $request->validate([
            'monthly_salary' => ['nullable', 'numeric', 'min:0'],
        ]);

        $before = $teacher->getAttributes();
        $teacher->update(['monthly_salary' => $data['monthly_salary']]);

        $this->audit->logModel('teacher.salary_updated', $teacher, $before, actor: $request->user());

        return back()->with('success', 'تم تحديث الراتب الشهري');
    }
}
