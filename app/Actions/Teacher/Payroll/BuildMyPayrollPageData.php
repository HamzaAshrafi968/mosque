<?php

namespace App\Actions\Teacher\Payroll;

use App\Enums\FinancialDirection;
use App\Enums\FinancialTransactionType;
use App\Models\FinancialTransaction;
use App\Models\PayrollPeriod;
use App\Models\Teacher;
use App\Models\WorkSlot;
use App\Services\FinanceService;
use App\Services\PayrollPeriodService;
use App\Support\TimesheetAggregator;
use Carbon\CarbonImmutable;

/**
 * بيانات صفحة الأستاذ الموحّدة «رواتبي»: ملخص الشهر المحدد (ساعات + مبالغ)،
 * شجرة فترات العمل، كشوفه الشهرية، وآخر الدفعات الواردة إليه.
 */
class BuildMyPayrollPageData
{
    public function __construct(private readonly PayrollPeriodService $payroll) {}

    /** @return array<string, mixed> */
    public function execute(Teacher $teacher, CarbonImmutable $month): array
    {
        $slots = $this->payroll->slotsFor($teacher, $month);
        $summary = $this->payroll->summary($teacher, $month, null, $slots);

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

        return [
            'teacher' => $teacher,
            'month' => $month,
            'monthInput' => $month->format('Y-m'),
            'weeks' => TimesheetAggregator::monthWeeks($month->year, $month->month),
            'blocks' => TimesheetAggregator::monthDayBlocks($month->year, $month->month),
            'slotsByDate' => $slots->groupBy(fn (WorkSlot $slot) => $slot->date->toDateString()),
            'summary' => $summary,
            'periods' => $periods,
            'paid' => $paid,
            'deposits' => FinancialTransaction::query()
                ->where('person_type', 'teacher')
                ->where('person_id', $teacher->id)
                ->where('transaction_type', FinancialTransactionType::Payment->value)
                ->where('direction', FinancialDirection::MoneyIn->value)
                ->with(['creator:id,name', 'reversal:id,reverses_id'])
                ->latest()
                ->limit(10)
                ->get(),
            'currency' => FinanceService::DEFAULT_CURRENCY,
        ];
    }
}
