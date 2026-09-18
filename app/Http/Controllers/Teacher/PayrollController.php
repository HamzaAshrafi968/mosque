<?php

namespace App\Http\Controllers\Teacher;

use App\Models\PayrollPeriod;
use App\Services\FinanceService;
use App\Services\PayrollPeriodService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * بوابة الأستاذ: كشوف رواتبه الشهرية (عرض فقط) مع الدفعات المرتبطة بكل كشف.
 */
class PayrollController extends BaseTeacherController
{
    public function __construct(private readonly PayrollPeriodService $payroll) {}

    public function index(Request $request): View
    {
        $teacher = $this->currentTeacher($request);

        $periods = PayrollPeriod::query()
            ->where('teacher_id', $teacher->id)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate(12)
            ->withQueryString();

        $paid = [];

        foreach ($periods as $period) {
            $paid[$period->id] = $this->payroll->paidFor($period);
        }

        return view('teacher.payroll.index', [
            'teacher' => $teacher,
            'periods' => $periods,
            'paid' => $paid,
            'currency' => FinanceService::DEFAULT_CURRENCY,
        ]);
    }

    public function show(Request $request, PayrollPeriod $period): View
    {
        $teacher = $this->currentTeacher($request);

        abort_unless($period->teacher_id === $teacher->id, 403);

        $payments = $period->payments()
            ->with(['creator:id,name', 'reversal:id,reverses_id'])
            ->latest()
            ->get();

        return view('teacher.payroll.show', [
            'teacher' => $teacher,
            'period' => $period,
            'payments' => $payments,
            'paid' => $this->payroll->paidFor($period),
            'currency' => FinanceService::DEFAULT_CURRENCY,
        ]);
    }
}
