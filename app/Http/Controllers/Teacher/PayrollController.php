<?php

namespace App\Http\Controllers\Teacher;

use App\Actions\Teacher\Payroll\BuildMyPayrollPageData;
use App\Models\PayrollPeriod;
use App\Services\FinanceService;
use App\Services\PayrollPeriodService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * بوابة الأستاذ: صفحة «رواتبي» الموحّدة — ملخص الشهر، ساعاته، كشوفه،
 * والدفعات الواردة إليه (عرض فقط).
 */
class PayrollController extends BaseTeacherController
{
    public function __construct(
        private readonly PayrollPeriodService $payroll,
        private readonly BuildMyPayrollPageData $pageData,
    ) {}

    public function index(Request $request): View
    {
        $teacher = $this->currentTeacher($request);

        $input = $request->validate(['month' => ['nullable', 'date_format:Y-m']])['month'] ?? null;
        $month = $input
            ? CarbonImmutable::createFromFormat('Y-m', $input)->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();

        return view('teacher.payroll.index', $this->pageData->execute($teacher, $month));
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
