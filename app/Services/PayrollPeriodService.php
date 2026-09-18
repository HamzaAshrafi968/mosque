<?php

namespace App\Services;

use App\Enums\FinancialDirection;
use App\Enums\FinancialTransactionType;
use App\Enums\PaymentState;
use App\Enums\PayrollStatus;
use App\Enums\PayType;
use App\Models\FinancialTransaction;
use App\Models\PayrollPeriod;
use App\Models\Teacher;
use App\Models\TeacherWorkHour;
use App\Models\User;
use App\Models\WorkSlot;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * كشوف الرواتب الشهرية.
 *
 * - الشهر المفتوح يُحتسب حياً من فترات العمل الفعلية + سعر كل تاريخ.
 * - عند الإغلاق تُثبَّت اللقطة (`total_minutes`, `gross_amount`, تفصيل التسعير)
 *   ولا تتأثر بأي تغيير لاحق على الفترات أو الأسعار.
 * - الدفعات تبقى صفوفاً في السجل المالي مرتبطةً بالكشف، والمدفوع/المتبقي
 *   يُشتقان منها دائماً (مع ذاكرة مؤقتة على الكشف لتسريع القوائم).
 */
class PayrollPeriodService
{
    public function __construct(
        private readonly PayrollCalculator $calculator,
        private readonly FinanceService $finance,
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    /** @return Collection<int, WorkSlot> */
    public function slotsFor(Teacher $teacher, CarbonInterface $month): Collection
    {
        $month = CarbonImmutable::parse($month)->startOfMonth();

        return WorkSlot::query()
            ->forTeacher($teacher->id)
            ->between($month->startOfMonth(), $month->endOfMonth())
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * ملخص كشف أستاذ لشهر واحد (يُحتسب حياً للمفتوح، ومن اللقطة للمغلق).
     *
     * @return array{
     *     period: ?PayrollPeriod,
     *     pay_type: PayType,
     *     total_minutes: int,
     *     planned_minutes: int,
     *     gross: float,
     *     breakdown: array<int, array{rate: float, minutes: int, from: string, to: string}>,
     *     missing_rates: array<int, string>,
     *     hourly_rate: ?float,
     *     monthly_salary: ?float,
     *     paid: float,
     *     remaining: float,
     *     state: PaymentState,
     *     status: PayrollStatus
     * }
     */
    public function summary(Teacher $teacher, CarbonInterface $month, ?PayrollPeriod $period = null, ?Collection $slots = null): array
    {
        $month = CarbonImmutable::parse($month)->startOfMonth();
        $slots ??= $this->slotsFor($teacher, $month);
        $period ??= $this->findPeriod($teacher, $month);
        $paid = $period !== null ? $this->paidFor($period) : 0.0;
        $plannedMinutes = (int) round(TeacherWorkHour::monthlyHours($teacher->id, $month) * 60);

        return $this->buildSummary($teacher, $slots, $period, $paid, $plannedMinutes);
    }

    /**
     * ملخصات مجموعة أساتذة باستعلامات مجمّعة (بدون N+1).
     *
     * @param  Collection<int, Teacher>  $teachers
     * @return array<string, array<string, mixed>>
     */
    public function summaries(Collection $teachers, CarbonInterface $month): array
    {
        $month = CarbonImmutable::parse($month)->startOfMonth();
        $ids = $teachers->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        $slots = WorkSlot::query()
            ->whereIn('teacher_id', $ids)
            ->between($month->startOfMonth(), $month->endOfMonth())
            ->get()
            ->groupBy('teacher_id');

        $periods = PayrollPeriod::query()
            ->whereIn('teacher_id', $ids)
            ->forMonth((int) $month->year, (int) $month->month)
            ->get()
            ->keyBy('teacher_id');

        $paid = $this->paidByPeriod($periods->pluck('id')->all());

        // المخطط (الجدول المتكرر) — يُحمَّل مرة واحدة لكل الأساتذة.
        $workHours = TeacherWorkHour::query()
            ->whereIn('teacher_id', $ids)
            ->get()
            ->groupBy('teacher_id');

        $out = [];

        foreach ($teachers as $teacher) {
            $period = $periods->get($teacher->id);
            $plannedMinutes = (int) round(
                TeacherWorkHour::monthlyHoursFromPeriods($workHours->get($teacher->id, collect()), $month) * 60
            );

            $out[$teacher->id] = $this->buildSummary(
                $teacher,
                $slots->get($teacher->id, collect()),
                $period,
                $period !== null ? ($paid[$period->id] ?? 0.0) : 0.0,
                $plannedMinutes
            );
        }

        return $out;
    }

    /** إنشاء كشف الشهر إن لم يكن موجوداً وتحديث قيمه (للمفتوح فقط). */
    public function ensure(Teacher $teacher, CarbonInterface $month, ?User $actor = null): PayrollPeriod
    {
        $month = CarbonImmutable::parse($month)->startOfMonth();

        $period = PayrollPeriod::firstOrCreate(
            [
                'teacher_id' => $teacher->id,
                'year' => (int) $month->year,
                'month' => (int) $month->month,
            ],
            [
                'tenant_id' => $teacher->tenant_id,
                'status' => PayrollStatus::Open,
            ]
        );

        return $this->refresh($period);
    }

    /** إعادة احتساب كشف مفتوح من الفترات والأسعار الحالية. */
    public function refresh(PayrollPeriod $period): PayrollPeriod
    {
        if ($period->isClosed()) {
            return $period;
        }

        $teacher = $period->teacher;
        $calculated = $this->calculator->calculate($teacher, $this->slotsFor($teacher, $period->monthStart()));

        $period->fill([
            'total_minutes' => $calculated['minutes'],
            'pay_type_snapshot' => $calculated['pay_type'],
            'monthly_salary_snapshot' => $calculated['monthly_salary'],
            'hourly_rate_snapshot' => $calculated['hourly_rate'],
            'rate_breakdown' => $calculated['breakdown'] !== [] ? $calculated['breakdown'] : null,
            'gross_amount' => $calculated['gross'],
            'paid_amount' => $this->paidFor($period),
            'calculated_at' => now(),
        ])->save();

        return $period;
    }

    /** إعادة احتساب كل الكشوف المفتوحة لأستاذ (بعد تغيير سعر الساعة مثلاً). */
    public function refreshOpenForTeacher(Teacher $teacher): void
    {
        PayrollPeriod::query()
            ->where('teacher_id', $teacher->id)
            ->where('status', PayrollStatus::Open->value)
            ->get()
            ->each(fn (PayrollPeriod $period) => $this->refresh($period));
    }

    /** تحديث كشف الشهر المفتوح بعد تغيير فترة عمل (BR-17). */
    public function refreshForSlot(WorkSlot $slot): void
    {
        $date = CarbonImmutable::parse($slot->date);

        $period = PayrollPeriod::query()
            ->where('teacher_id', $slot->teacher_id)
            ->forMonth((int) $date->year, (int) $date->month)
            ->first();

        if ($period !== null && ! $period->isClosed()) {
            $this->refresh($period);
        }
    }

    /** تسجيل دفعة راتب مرتبطة بالكشف (دفع جزئي مسموح، والزائد مرفوض). */
    public function recordPayment(
        PayrollPeriod $period,
        float|string $amount,
        ?string $method,
        ?string $reference,
        ?string $description,
        User $actor,
    ): FinancialTransaction {
        if (! $period->isClosed()) {
            $this->refresh($period);
        }

        $value = round((float) $amount, 2);

        if ($value <= 0) {
            throw ValidationException::withMessages(['amount' => ['المبلغ يجب أن يكون أكبر من صفر']]);
        }

        if ($period->pay_type_snapshot === PayType::Hourly) {
            $missing = $this->missingRatesFor($period);

            if ($missing !== []) {
                throw ValidationException::withMessages([
                    'amount' => ['لا يمكن احتساب الراتب — لا يوجد سعر ساعة في: '.implode('، ', $missing)],
                ]);
            }
        }

        $gross = (float) $period->gross_amount;
        $paid = $this->paidFor($period);
        $remaining = round($gross - $paid, 2);

        if ($gross > 0 && $value > $remaining + 0.001) {
            throw ValidationException::withMessages([
                'amount' => ['المبلغ يتجاوز المتبقي ('.number_format($remaining, 2).' '.FinanceService::DEFAULT_CURRENCY.')'],
            ]);
        }

        $transaction = $this->finance->record([
            'person_type' => 'teacher',
            'person_id' => $period->teacher_id,
            'transaction_type' => FinancialTransactionType::Payment->value,
            'amount' => $value,
            'description' => $description ?: 'دفعة راتب — '.$period->monthLabel(),
            'reference' => $reference,
            'payment_method' => $method,
            'payroll_period_id' => $period->id,
        ], $actor, $period->tenant_id);

        $period->update(['paid_amount' => $this->paidFor($period)]);

        $this->notifyPayment($period, $transaction);

        return $transaction;
    }

    /** إغلاق كشف الشهر: تثبيت اللقطة ومنع تعديل الفترات داخله (BR-12). */
    public function close(Teacher $teacher, CarbonInterface $month, User $actor): PayrollPeriod
    {
        $period = $this->ensure($teacher, $month, $actor);

        if ($period->isClosed()) {
            return $period;
        }

        if ($period->pay_type_snapshot === PayType::Hourly) {
            $missing = $this->missingRatesFor($period);

            if ($missing !== []) {
                throw ValidationException::withMessages([
                    'close' => ['لا يمكن إغلاق الشهر — لا يوجد سعر ساعة في: '.implode('، ', $missing)],
                ]);
            }
        }

        $period->update([
            'status' => PayrollStatus::Closed,
            'closed_at' => now(),
            'closed_by' => $actor->id,
        ]);

        $this->audit->logModel('payroll.closed', $period, actor: $actor);

        $this->notifyClosed($period);

        return $period->refresh();
    }

    /** إعادة فتح كشف مغلق بصلاحية خاصة وسبب مُلزم. */
    public function reopen(PayrollPeriod $period, string $reason, User $actor): PayrollPeriod
    {
        if (! $period->isClosed()) {
            return $period;
        }

        $period->update([
            'status' => PayrollStatus::Open,
            'closed_at' => null,
            'closed_by' => null,
        ]);

        $this->audit->log('payroll.reopened', 'payroll_period', $period->id, $period->tenant_id, after: [
            'reason' => $reason,
            'month' => $period->monthKey(),
            'teacher_id' => $period->teacher_id,
        ], actor: $actor);

        return $this->refresh($period);
    }

    /** هل يقع التاريخ داخل شهر مغلق لهذا الأستاذ؟ */
    public function isMonthClosed(string $teacherId, CarbonInterface|string $date): bool
    {
        $date = CarbonImmutable::parse($date);

        return PayrollPeriod::query()
            ->where('teacher_id', $teacherId)
            ->forMonth((int) $date->year, (int) $date->month)
            ->where('status', PayrollStatus::Closed->value)
            ->exists();
    }

    public function findPeriod(Teacher $teacher, CarbonInterface $month): ?PayrollPeriod
    {
        $month = CarbonImmutable::parse($month)->startOfMonth();

        return PayrollPeriod::query()
            ->where('teacher_id', $teacher->id)
            ->forMonth((int) $month->year, (int) $month->month)
            ->first();
    }

    /** المدفوع الفعلي من السجل المالي (الدفعات غير المعكوسة المرتبطة بالكشف). */
    public function paidFor(PayrollPeriod $period): float
    {
        $total = FinancialTransaction::query()
            ->where('payroll_period_id', $period->id)
            ->where('transaction_type', FinancialTransactionType::Payment->value)
            ->where('direction', FinancialDirection::MoneyIn->value)
            ->whereNull('reverses_id')
            ->whereNotIn('id', fn ($query) => $query
                ->select('reverses_id')
                ->from('financial_transactions')
                ->whereNotNull('reverses_id'))
            ->sum('amount');

        return round((float) $total, 2);
    }

    /** @param  array<int, string>  $periodIds */
    private function paidByPeriod(array $periodIds): array
    {
        if ($periodIds === []) {
            return [];
        }

        return FinancialTransaction::query()
            ->whereIn('payroll_period_id', $periodIds)
            ->where('transaction_type', FinancialTransactionType::Payment->value)
            ->where('direction', FinancialDirection::MoneyIn->value)
            ->whereNull('reverses_id')
            ->whereNotIn('id', fn ($query) => $query
                ->select('reverses_id')
                ->from('financial_transactions')
                ->whereNotNull('reverses_id'))
            ->selectRaw('payroll_period_id, sum(amount) as total')
            ->groupBy('payroll_period_id')
            ->pluck('total', 'payroll_period_id')
            ->map(fn ($value) => round((float) $value, 2))
            ->all();
    }

    /** @return array<int, string> */
    private function missingRatesFor(PayrollPeriod $period): array
    {
        $teacher = $period->teacher;
        $slots = $this->slotsFor($teacher, $period->monthStart());

        return app(HourlyRateResolver::class)->breakdown($teacher, $slots)['missing'];
    }

    /**
     * @param  Collection<int, WorkSlot>  $slots
     * @return array<string, mixed>
     */
    private function buildSummary(Teacher $teacher, Collection $slots, ?PayrollPeriod $period, float $paid, int $plannedMinutes): array
    {
        $calculated = $this->calculator->calculate($teacher, $slots);

        if ($period !== null && $period->isClosed()) {
            $calculated = [
                'pay_type' => $period->pay_type_snapshot,
                'minutes' => (int) $period->total_minutes,
                'gross' => (float) $period->gross_amount,
                'breakdown' => $period->rate_breakdown ?? [],
                'missing' => [],
                'hourly_rate' => $period->hourly_rate_snapshot !== null ? (float) $period->hourly_rate_snapshot : null,
                'monthly_salary' => $period->monthly_salary_snapshot !== null ? (float) $period->monthly_salary_snapshot : null,
            ];
        }

        $gross = (float) $calculated['gross'];
        $remaining = round($gross - $paid, 2);

        return [
            'period' => $period,
            'pay_type' => $calculated['pay_type'],
            'total_minutes' => $calculated['minutes'],
            'planned_minutes' => $plannedMinutes,
            'gross' => $gross,
            'breakdown' => $calculated['breakdown'],
            'missing_rates' => $calculated['missing'],
            'hourly_rate' => $calculated['hourly_rate'],
            'monthly_salary' => $calculated['monthly_salary'],
            'paid' => $paid,
            'remaining' => $remaining,
            'state' => $this->calculator->paymentState($gross, $paid),
            'status' => $period?->status ?? PayrollStatus::Open,
        ];
    }

    private function notifyPayment(PayrollPeriod $period, FinancialTransaction $transaction): void
    {
        $teacher = $period->teacher;

        if ($teacher?->user_id === null) {
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

    private function notifyClosed(PayrollPeriod $period): void
    {
        $teacher = $period->teacher;

        if ($teacher?->user_id === null) {
            return;
        }

        $gross = number_format((float) $period->gross_amount, 2);

        $this->notifications->send(
            [$teacher->user_id],
            'تم إغلاق كشف راتبك',
            'تم إغلاق كشف '.$period->monthLabel()." بإجمالي {$gross} ".FinanceService::DEFAULT_CURRENCY,
            route('teacher.payroll.index')
        );
    }
}
