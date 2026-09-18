<?php

namespace App\Services;

use App\Enums\PaymentState;
use App\Enums\PayType;
use App\Models\Teacher;
use App\Models\WorkSlot;
use Illuminate\Support\Collection;

/**
 * احتساب إجمالي الراتب:
 * - `monthly`: الإجمالي = الراتب الشهري الثابت (والساعات للعرض فقط).
 * - `hourly`: الإجمالي = Σ (دقائق كل فترة × سعر تاريخها) ÷ 60، مقرّباً لخانتين.
 */
class PayrollCalculator
{
    public function __construct(private readonly HourlyRateResolver $rates) {}

    /**
     * @param  Collection<int, WorkSlot>  $slots
     * @return array{
     *     pay_type: PayType,
     *     minutes: int,
     *     gross: float,
     *     breakdown: array<int, array{rate: float, minutes: int, from: string, to: string}>,
     *     missing: array<int, string>,
     *     hourly_rate: ?float,
     *     monthly_salary: ?float
     * }
     */
    public function calculate(Teacher $teacher, Collection $slots): array
    {
        $payType = $teacher->pay_type ?? PayType::Monthly;
        $minutes = WorkSlot::totalMinutesFrom($slots);

        if ($payType === PayType::Monthly) {
            return [
                'pay_type' => PayType::Monthly,
                'minutes' => $minutes,
                'gross' => round((float) ($teacher->monthly_salary ?? 0), 2),
                'breakdown' => [],
                'missing' => [],
                'hourly_rate' => null,
                'monthly_salary' => $teacher->monthly_salary !== null ? (float) $teacher->monthly_salary : null,
            ];
        }

        $resolved = $this->rates->breakdown($teacher, $slots);
        $gross = 0.0;

        foreach ($resolved['breakdown'] as $segment) {
            $gross += $segment['minutes'] / 60 * $segment['rate'];
        }

        return [
            'pay_type' => PayType::Hourly,
            'minutes' => $minutes,
            'gross' => round($gross, 2),
            'breakdown' => $resolved['breakdown'],
            'missing' => $resolved['missing'],
            'hourly_rate' => count($resolved['breakdown']) === 1 ? $resolved['breakdown'][0]['rate'] : null,
            'monthly_salary' => null,
        ];
    }

    /** حالة السداد مشتقة من الإجمالي والمدفوع. */
    public function paymentState(float $gross, float $paid): PaymentState
    {
        if ($paid <= 0) {
            return PaymentState::Unpaid;
        }

        if ($gross > 0 && $paid + 0.001 < $gross) {
            return PaymentState::Partial;
        }

        return PaymentState::Paid;
    }
}
