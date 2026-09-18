<?php

namespace App\Models;

use App\Enums\PaymentState;
use App\Enums\PayrollStatus;
use App\Enums\PayType;
use App\Support\QuranProgramSettings;
use App\Traits\FlushesTenantCache;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * كشف راتب شهري لأستاذ واحد.
 *
 * المفتوح يُحتسب حياً من فترات العمل والسعر الساري، وعند الإغلاق تُثبَّت
 * القيم كلقطة (snapshot) لا تتأثر بتغيير السعر أو الفترات لاحقاً.
 */
class PayrollPeriod extends Model
{
    use FlushesTenantCache, HasFactory, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'teacher_id',
        'year',
        'month',
        'total_minutes',
        'pay_type_snapshot',
        'monthly_salary_snapshot',
        'hourly_rate_snapshot',
        'rate_breakdown',
        'gross_amount',
        'paid_amount',
        'status',
        'calculated_at',
        'closed_at',
        'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'total_minutes' => 'integer',
            'pay_type_snapshot' => PayType::class,
            'monthly_salary_snapshot' => 'decimal:2',
            'hourly_rate_snapshot' => 'decimal:2',
            'rate_breakdown' => 'array',
            'gross_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'status' => PayrollStatus::class,
            'calculated_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** الدفعات المرتبطة بهذا الكشف (المصدر: السجل المالي). */
    public function payments(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'payroll_period_id');
    }

    public function isClosed(): bool
    {
        return $this->status === PayrollStatus::Closed;
    }

    public function monthStart(): CarbonImmutable
    {
        return CarbonImmutable::create($this->year, $this->month, 1)->startOfMonth();
    }

    public function monthKey(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }

    public function monthLabel(): string
    {
        return QuranProgramSettings::monthLabel($this->monthKey());
    }

    public function remainingAmount(): float
    {
        return round((float) $this->gross_amount - (float) $this->paid_amount, 2);
    }

    public function paymentState(): PaymentState
    {
        $gross = (float) $this->gross_amount;
        $paid = (float) $this->paid_amount;

        if ($paid <= 0) {
            return PaymentState::Unpaid;
        }

        if ($gross > 0 && $paid + 0.001 < $gross) {
            return PaymentState::Partial;
        }

        return PaymentState::Paid;
    }

    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->where('year', $year)->where('month', $month);
    }
}
