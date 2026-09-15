<?php

namespace App\Services;

use App\Enums\FinancialDirection;
use App\Enums\FinancialTransactionType;
use App\Models\FinancialTransaction;
use App\Models\Teacher;
use App\Models\TeacherWorkHour;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * رواتب المعلمين: المدير يحدد الراتب الشهري ويتابع ساعات العمل الشهرية، وكل
 * دفعة يسجلها تصفّر عدّاد الساعات المتراكمة لأن العدّاد يُحسب من اليوم التالي
 * لآخر دفعة غير معكوسة.
 */
class PayrollService
{
    public function __construct(
        private readonly FinanceService $finance,
        private readonly NotificationService $notifications,
    ) {}

    /** تسجيل دفعة للمعلم (payment / money_in) مع إشعاره فوراً. */
    public function pay(Teacher $teacher, float|string $amount, ?string $description, User $actor): FinancialTransaction
    {
        $value = round((float) $amount, 2);

        if ($value <= 0) {
            throw ValidationException::withMessages(['amount' => ['المبلغ يجب أن يكون أكبر من صفر']]);
        }

        $transaction = $this->finance->record([
            'person_type' => 'teacher',
            'person_id' => $teacher->id,
            'transaction_type' => FinancialTransactionType::Payment->value,
            'amount' => $value,
            'description' => $description ?: 'دفعة راتب',
        ], $actor, $teacher->tenant_id);

        $this->notifyPayment($teacher, $transaction);

        return $transaction;
    }

    /** الدفعات غير المعكوسة فقط (العدّاد والإجماليات تُبنى عليها). */
    public function paymentQuery(Teacher $teacher)
    {
        return FinancialTransaction::query()
            ->where('person_type', 'teacher')
            ->where('person_id', $teacher->id)
            ->where('transaction_type', FinancialTransactionType::Payment->value)
            ->where('direction', FinancialDirection::MoneyIn->value)
            ->whereNull('reverses_id')
            ->whereNotIn('id', fn ($query) => $query
                ->select('reverses_id')
                ->from('financial_transactions')
                ->whereNotNull('reverses_id'));
    }

    /** آخر دفعة غير معكوسة للمعلم (نقطة تصفير عدّاد الساعات). */
    public function lastPayment(Teacher $teacher): ?FinancialTransaction
    {
        return $this->paymentQuery($teacher)->latest('created_at')->first();
    }

    /** ساعات الجدول المتكرر منذ آخر دفعة (صفر مباشرة بعد الدفع). */
    public function hoursSinceLastPayment(Teacher $teacher): float
    {
        return $this->summaries(collect([$teacher]))[$teacher->id]['hours_since_last_payment'];
    }

    /** الدفعات الواردة للمعلم (صفحة الأستاذ — يرى ما نزل له فقط). */
    public function payments(Teacher $teacher)
    {
        return FinancialTransaction::query()
            ->where('person_type', 'teacher')
            ->where('person_id', $teacher->id)
            ->where('transaction_type', FinancialTransactionType::Payment->value)
            ->where('direction', FinancialDirection::MoneyIn->value)
            ->with(['creator:id,name', 'reversal:id,reverses_id'])
            ->latest()
            ->paginate(30)
            ->withQueryString();
    }

    /**
     * ملخص الرواتب لمعلم أو لمجموعة معلمين (استعلامان فقط مهما كان العدد).
     *
     * @param  Collection<int, Teacher>  $teachers
     * @return array<string, array{monthly_hours: float, hours_since_last_payment: float, last_payment: ?FinancialTransaction, paid_in_month: float}>
     */
    public function summaries(Collection $teachers, ?CarbonInterface $month = null): array
    {
        $month = $month !== null ? CarbonImmutable::parse($month) : CarbonImmutable::now();
        $monthStart = $month->startOfMonth();
        $monthEnd = $month->endOfMonth();
        $today = CarbonImmutable::now();

        $payments = FinancialTransaction::query()
            ->where('person_type', 'teacher')
            ->whereIn('person_id', $teachers->pluck('id'))
            ->where('transaction_type', FinancialTransactionType::Payment->value)
            ->where('direction', FinancialDirection::MoneyIn->value)
            ->whereNull('reverses_id')
            ->whereNotIn('id', fn ($query) => $query
                ->select('reverses_id')
                ->from('financial_transactions')
                ->whereNotNull('reverses_id'))
            ->orderBy('created_at')
            ->get()
            ->groupBy('person_id');

        $out = [];

        foreach ($teachers as $teacher) {
            $rows = $payments->get($teacher->id, collect());
            $last = $rows->last();
            $periods = $teacher->relationLoaded('workHours') ? $teacher->workHours : $teacher->workHours()->get();

            // بعد الدفع يبدأ العدّاد من الغد → يتصفّر مباشرة (بند «بيتصفر دوام الساعات»).
            $from = $last?->created_at?->copy()->addDay()->startOfDay()
                ?? $teacher->hired_at?->copy()->startOfDay()
                ?? $monthStart;

            $out[$teacher->id] = [
                'monthly_hours' => TeacherWorkHour::monthlyHoursFromPeriods($periods, $month),
                'hours_since_last_payment' => TeacherWorkHour::hoursBetweenFromPeriods($periods, $from, $today),
                'last_payment' => $last,
                'paid_in_month' => round((float) $rows
                    ->filter(fn (FinancialTransaction $row) => $row->created_at >= $monthStart && $row->created_at <= $monthEnd)
                    ->sum('amount'), 2),
            ];
        }

        return $out;
    }

    private function notifyPayment(Teacher $teacher, FinancialTransaction $transaction): void
    {
        if (! $teacher->user_id) {
            return;
        }

        $amount = number_format((float) $transaction->amount, 2);

        $this->notifications->send(
            [$teacher->user_id],
            'تم إيداع دفعة لك',
            "نزلت لك دفعة بقيمة {$amount} ".FinanceService::DEFAULT_CURRENCY.' — '.($transaction->description ?: 'دفعة راتب'),
            route('teacher.finance.index')
        );
    }
}
