<?php

namespace App\Models;

use App\Enums\FinancePersonType;
use App\Enums\FinancialDirection;
use App\Enums\FinancialTransactionType;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ledger entry (source of truth) — balances are never stored, always derived.
 *
 * Accounting semantics:
 * - Every row is recorded from the perspective of `person` (student/teacher).
 * - `amount` is always a positive amount.
 * - `direction` money_out increases the person's outstanding balance
 *   (charges, refunds, transfers they sent, out-adjustments).
 *   `direction` money_in decreases it (payments, transfers they received,
 *   in-adjustments).
 * - Outstanding balance = sum(money_out) − sum(money_in);
 *   positive means the person owes, negative means credit.
 * - Person-to-person transfers always create two mirror rows linked by a
 *   shared `reference`, so both sides of the relationship are preserved.
 * - Corrections happen through reversal rows (`reverses_id`) — never by
 *   silently deleting history.
 */
class FinancialTransaction extends Model
{
    use MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'person_type',
        'person_id',
        'transaction_type',
        'direction',
        'amount',
        'related_person_type',
        'related_person_id',
        'description',
        'reference',
        'reverses_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'person_type' => FinancePersonType::class,
            'transaction_type' => FinancialTransactionType::class,
            'direction' => FinancialDirection::class,
            'amount' => 'decimal:2',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    /** The person this ledger entry belongs to (Student|Teacher|null). */
    public function person(): BelongsTo
    {
        return $this->belongsTo(self::personModelClass($this->person_type), 'person_id');
    }

    /** The other side of a person-to-person transfer (Student|Teacher|null). */
    public function relatedPerson(): BelongsTo
    {
        $type = $this->related_person_type;

        return $this->belongsTo(self::personModelClass($type), 'related_person_id');
    }

    public static function personModelClass(FinancePersonType|string|null $type): string
    {
        return ($type instanceof FinancePersonType ? $type : FinancePersonType::tryFrom((string) $type)) === FinancePersonType::Teacher
            ? Teacher::class
            : Student::class;
    }

    public function scopeForPerson(Builder $query, string $personType, string $personId): Builder
    {
        return $query
            ->where('person_type', $personType)
            ->where('person_id', $personId);
    }
}
