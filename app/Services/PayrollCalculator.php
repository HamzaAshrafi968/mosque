<?php

namespace App\Services;

use App\Enums\PaymentState;
use App\Models\HourlyRate;
use App\Models\Teacher;
use App\Models\WorkSlot;
use Illuminate\Support\Collection;

/**
 * احتساب إجمالي المستحقات — بالساعات فقط:
 * الإجمالي = Σ (دقائق كل فترة عمل × سعر الساعة في تاريخها) ÷ 60، مقرّباً لخانتين.
 * الأيام بلا سعر لا تُحتسب بصفر صامت بل تُعاد في `missing` كتنبيه.
 */
class PayrollCalculator
{
    public function __construct(private readonly HourlyRateResolver $rates) {}

    /**
     * @param  Collection<int, WorkSlot>  $slots
     * @param  Collection<int, HourlyRate>|null  $preloadedRates
     * @return array{
     *     minutes: int,
     *     gross: float,
     *     breakdown: array<int, array{rate: float, minutes: int, from: string, to: string}>,
     *     missing: array<int, string>,
     *     hourly_rate: ?float,
     *     rate_is_mixed: bool
     * }
     */
    public function calculate(Teacher $teacher, Collection $slots, ?Collection $preloadedRates = null): array
    {
        $resolved = $this->rates->breakdown($teacher, $slots, $preloadedRates);
        $gross = 0.0;

        foreach ($resolved['breakdown'] as $segment) {
            $gross += $segment['minutes'] / 60 * $segment['rate'];
        }

        return [
            'minutes' => WorkSlot::totalMinutesFrom($slots),
            'gross' => round($gross, 2),
            'breakdown' => $resolved['breakdown'],
            'missing' => $resolved['missing'],
            'hourly_rate' => count($resolved['breakdown']) === 1 ? $resolved['breakdown'][0]['rate'] : null,
            'rate_is_mixed' => count($resolved['breakdown']) > 1,
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
