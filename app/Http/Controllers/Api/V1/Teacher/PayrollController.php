<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Contracts\Repositories\TeacherRepositoryInterface;
use App\Enums\PaymentState;
use App\Models\PayrollPeriod;
use App\Models\WorkSlot;
use App\Services\PayrollPeriodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API بوابة الأستاذ: كشوف رواتبه الشهرية (عرض فقط).
 */
class PayrollController extends BaseTeacherController
{
    public function __construct(
        TeacherRepositoryInterface $teacherRepository,
        private readonly PayrollPeriodService $payroll,
    ) {
        parent::__construct($teacherRepository);
    }

    public function index(Request $request): JsonResponse
    {
        $teacher = $this->currentTeacher($request);

        $periods = PayrollPeriod::query()
            ->where('teacher_id', $teacher->id)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate(12);

        $items = $periods->getCollection()->map(function (PayrollPeriod $period) {
            $paid = $this->payroll->paidFor($period);
            $remaining = round((float) $period->gross_amount - $paid, 2);

            return [
                'id' => $period->id,
                'month' => $period->monthKey(),
                'month_label' => $period->monthLabel(),
                'pay_type' => $period->pay_type_snapshot->value,
                'total_minutes' => (int) $period->total_minutes,
                'gross' => (float) $period->gross_amount,
                'paid' => $paid,
                'remaining' => $remaining,
                'status' => $period->status->value,
                'payment_state' => $this->state($period, $paid, $remaining)->value,
                'payment_state_label' => $this->state($period, $paid, $remaining)->label(),
            ];
        });

        return $this->success([
            'periods' => $items->values(),
            'pagination' => [
                'current_page' => $periods->currentPage(),
                'last_page' => $periods->lastPage(),
                'per_page' => $periods->perPage(),
                'total' => $periods->total(),
            ],
        ]);
    }

    public function show(Request $request, PayrollPeriod $period): JsonResponse
    {
        $teacher = $this->currentTeacher($request);

        if ($period->teacher_id !== $teacher->id) {
            return $this->forbidden();
        }

        $paid = $this->payroll->paidFor($period);

        return $this->success([
            'period' => [
                'id' => $period->id,
                'month' => $period->monthKey(),
                'month_label' => $period->monthLabel(),
                'total_minutes' => (int) $period->total_minutes,
                'total_label' => WorkSlot::formatMinutes((int) $period->total_minutes),
                'gross' => (float) $period->gross_amount,
                'paid' => $paid,
                'remaining' => round((float) $period->gross_amount - $paid, 2),
                'rate_breakdown' => $period->rate_breakdown ?? [],
                'status' => $period->status->value,
            ],
            'payments' => $period->payments()
                ->with(['creator:id,name', 'reversal:id,reverses_id'])
                ->latest()
                ->get()
                ->map(fn ($payment) => [
                    'id' => $payment->id,
                    'amount' => (float) $payment->amount,
                    'description' => $payment->description,
                    'payment_method' => $payment->payment_method,
                    'reversed' => $payment->reversal !== null,
                    'created_at' => $payment->created_at?->toIso8601String(),
                ])->values(),
        ]);
    }

    private function state(PayrollPeriod $period, float $paid, float $remaining): PaymentState
    {
        if ($paid <= 0) {
            return PaymentState::Unpaid;
        }

        if ((float) $period->gross_amount > 0 && $remaining > 0) {
            return PaymentState::Partial;
        }

        return PaymentState::Paid;
    }
}
