<?php

namespace App\Services;

use App\Enums\FinancialDirection;
use App\Enums\FinancialTransactionType;
use App\Models\FinancialTransaction;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Financial ledger operations (spec §13-§16).
 *
 * Ledger model (documented on FinancialTransaction):
 * - The ledger rows are the source of truth; balances are always derived.
 * - amount > 0; `direction` decides the sign contribution.
 * - money_out  → outstanding += amount  (charge, refund, sent transfer, out-adjustment)
 * - money_in   → outstanding -= amount  (payment, received transfer, in-adjustment)
 * - Outstanding balance = Σ money_out − Σ money_in (positive = owes).
 * - Transfers create mirrored rows for both persons under one reference.
 * - Corrections: reversal rows linked by `reverses_id` — nothing is deleted.
 */
class FinanceService
{
    public const DEFAULT_CURRENCY = 'ر.س';

    public function __construct(private readonly AuditLogger $audit) {}

    public static function personModel(string $personType): string
    {
        return $personType === 'teacher' ? Teacher::class : Student::class;
    }

    /** Resolve and tenant-check a person (Student|Teacher). */
    public function personOrFail(string $personType, string $personId, string $tenantId): Model
    {
        $person = self::personModel($personType)::query()->find($personId);

        if (! $person) {
            throw ValidationException::withMessages(['person_id' => ['الشخص غير موجود']]);
        }

        if ($person->tenant_id !== $tenantId) {
            throw ValidationException::withMessages(['person_id' => ['الشخص لا ينتمي لنفس الجامع']]);
        }

        return $person;
    }

    /**
     * Record a single-side transaction: charge / payment / refund / adjustment.
     *
     * @param  array{person_type: string, person_id: string, transaction_type: string, direction?: string, amount: float|string, description?: ?string, reference?: ?string}  $data
     */
    public function record(array $data, User $actor, ?string $tenantId = null): FinancialTransaction
    {
        $tenantId ??= $actor->tenant_id;

        $person = $this->personOrFail($data['person_type'], $data['person_id'], $tenantId);

        $relatedType = $data['related_person_type'] ?? null;
        $relatedId = $data['related_person_id'] ?? null;

        if ($relatedType && $relatedId) {
            // Optional counterparty (student|teacher) — validated the same way.
            $related = $this->personOrFail($relatedType, $relatedId, $tenantId);
            $relatedType = $related instanceof Teacher ? 'teacher' : 'student';
        }

        $type = FinancialTransactionType::from($data['transaction_type']);
        $direction = $this->directionFor($type, $data['direction'] ?? null);

        $amount = $this->validateAmount($data['amount']);

        $transaction = FinancialTransaction::create([
            'tenant_id' => $tenantId,
            'person_type' => $person->getMorphClass() === Teacher::class ? 'teacher' : 'student',
            'person_id' => $person->id,
            'transaction_type' => $type,
            'direction' => $direction,
            'amount' => $amount,
            'related_person_type' => $relatedType,
            'related_person_id' => $relatedId,
            'description' => $data['description'] ?? null,
            'reference' => $data['reference'] ?? null,
            'created_by' => $actor->id,
        ]);

        $this->audit->log(
            'finance.transaction_created',
            'financial_transaction',
            $transaction->id,
            $tenantId,
            after: $this->describe($transaction),
            actor: $actor
        );

        return $transaction;
    }

    /**
     * Person-to-person transfer: mirrors both sides under one reference so the
     * ledger preserves who gave what to whom, when and by whom.
     */
    public function transfer(
        string $fromType,
        string $fromId,
        string $toType,
        string $toId,
        float|string $amount,
        ?string $description,
        User $actor,
    ): array {
        $tenantId = $actor->tenant_id;

        $from = $this->personOrFail($fromType, $fromId, $tenantId);
        $to = $this->personOrFail($toType, $toId, $tenantId);

        if ($fromType === $toType && $fromId === $toId) {
            throw ValidationException::withMessages(['to_id' => ['لا يمكن التحويل لنفس الشخص']]);
        }

        $value = $this->validateAmount($amount);
        $reference = (string) Str::uuid();

        [$sender, $receiver] = DB::transaction(function () use ($fromType, $from, $toType, $to, $value, $description, $reference, $actor, $tenantId) {
            $sender = FinancialTransaction::create([
                'tenant_id' => $tenantId,
                'person_type' => $fromType,
                'person_id' => $from->id,
                'transaction_type' => FinancialTransactionType::Transfer,
                'direction' => FinancialDirection::MoneyOut,
                'amount' => $value,
                'related_person_type' => $toType,
                'related_person_id' => $to->id,
                'description' => $description,
                'reference' => $reference,
                'created_by' => $actor->id,
            ]);

            $receiver = FinancialTransaction::create([
                'tenant_id' => $tenantId,
                'person_type' => $toType,
                'person_id' => $to->id,
                'transaction_type' => FinancialTransactionType::Transfer,
                'direction' => FinancialDirection::MoneyIn,
                'amount' => $value,
                'related_person_type' => $fromType,
                'related_person_id' => $from->id,
                'description' => $description,
                'reference' => $reference,
                'created_by' => $actor->id,
            ]);

            return [$sender, $receiver];
        });

        foreach ([$sender, $receiver] as $row) {
            $this->audit->log(
                'finance.transfer_created',
                'financial_transaction',
                $row->id,
                $tenantId,
                after: $this->describe($row),
                actor: $actor
            );
        }

        return [$sender, $receiver];
    }

    /**
     * Reverse a transaction (or a transfer: both mirrored legs are reversed).
     * History is preserved; the reversal itself is a new ledger row.
     */
    public function reverse(string $transactionId, User $actor, ?string $tenantId = null): array
    {
        $tenantId ??= $actor->tenant_id;

        $original = FinancialTransaction::query()
            ->where('id', $transactionId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $original) {
            throw ValidationException::withMessages(['id' => ['العملية غير موجودة']]);
        }

        if ($original->reverses_id !== null) {
            throw ValidationException::withMessages(['id' => ['لا يمكن عكس عملية عكسية']]);
        }

        $reverse = fn (FinancialTransaction $row) => FinancialTransaction::create([
            'tenant_id' => $tenantId,
            'person_type' => $row->person_type,
            'person_id' => $row->person_id,
            'transaction_type' => $row->transaction_type,
            'direction' => $row->direction === FinancialDirection::MoneyOut
                ? FinancialDirection::MoneyIn
                : FinancialDirection::MoneyOut,
            'amount' => $row->amount,
            'related_person_type' => $row->related_person_type,
            'related_person_id' => $row->related_person_id,
            'description' => 'عكس عملية: '.($row->description ?: $row->id),
            'reference' => $row->reference,
            'reverses_id' => $row->id,
            'created_by' => $actor->id,
        ]);

        $reversals = DB::transaction(function () use ($original, $reverse) {
            $rows = [$original];

            if ($original->transaction_type === FinancialTransactionType::Transfer) {
                $counterpart = FinancialTransaction::query()
                    ->where('tenant_id', $original->tenant_id)
                    ->where('reference', $original->reference)
                    ->where('id', '!=', $original->id)
                    ->first();

                if ($counterpart) {
                    $rows[] = $counterpart;
                }
            }

            return array_map($reverse, $rows);
        });

        foreach ($reversals as $row) {
            $this->audit->log(
                'finance.transaction_reversed',
                'financial_transaction',
                $row->id,
                $tenantId,
                after: $this->describe($row),
                actor: $actor
            );
        }

        return $reversals;
    }

    /** Derived outstanding balance: positive = person owes, negative = credit. */
    public function balance(string $personType, string $personId, ?string $tenantId = null): float
    {
        $tenantId ??= config('app.current_tenant_id');

        $sum = FinancialTransaction::query()
            ->forPerson($personType, $personId)
            ->selectRaw('sum(case when direction = ? then amount else -amount end) as net', [FinancialDirection::MoneyOut->value])
            ->where('tenant_id', $tenantId)
            ->value('net');

        return (float) ($sum ?? 0);
    }

    /** Totals grouped by transaction type and direction for a person. */
    public function summary(string $personType, string $personId, ?string $tenantId = null): array
    {
        $tenantId ??= config('app.current_tenant_id');

        $rows = FinancialTransaction::query()
            ->forPerson($personType, $personId)
            ->where('tenant_id', $tenantId)
            ->selectRaw('transaction_type, direction, sum(amount) as total')
            ->groupBy('transaction_type', 'direction')
            ->get();

        $out = [
            'charges' => 0.0, 'payments' => 0.0, 'refunds' => 0.0, 'transfers' => 0.0, 'adjustments' => 0.0,
            'received' => 0.0, 'sent' => 0.0,
        ];

        foreach ($rows as $row) {
            $total = (float) $row->total;
            $in = $row->direction === FinancialDirection::MoneyIn;

            // Reversal rows reuse the type with the flipped direction, so each
            // bucket nets by direction (out − in for debt-growing entries).
            switch ($row->transaction_type) {
                case FinancialTransactionType::Charge:
                    $out['charges'] += $in ? -$total : $total;
                    break;
                case FinancialTransactionType::Payment:
                    $out['payments'] += $in ? $total : -$total;
                    break;
                case FinancialTransactionType::Refund:
                    $out['refunds'] += $in ? -$total : $total;
                    break;
                case FinancialTransactionType::Transfer:
                    if ($in) {
                        $out['received'] += $total;
                    } else {
                        $out['sent'] += $total;
                    }
                    break;
                case FinancialTransactionType::Adjustment:
                    $out['adjustments'] += $in ? -$total : $total;
                    break;
            }
        }

        $out['balance'] = $out['charges'] + $out['refunds'] + $out['adjustments'] + $out['sent']
            - $out['payments'] - $out['received'];

        return $out;
    }

    private function directionFor(FinancialTransactionType $type, ?string $override): FinancialDirection
    {
        if ($type === FinancialTransactionType::Transfer) {
            throw ValidationException::withMessages(['transaction_type' => ['التحويلات بين الأشخاص تُسجل عبر مسار التحويل']]);
        }

        if ($type === FinancialTransactionType::Adjustment) {
            try {
                return $override ? FinancialDirection::from($override) : FinancialDirection::MoneyOut;
            } catch (\ValueError) {
                throw ValidationException::withMessages(['direction' => ['اتجاه التسوية يجب أن يكون money_in أو money_out']]);
            }
        }

        return match ($type) {
            FinancialTransactionType::Payment => FinancialDirection::MoneyIn,
            default => FinancialDirection::MoneyOut,
        };
    }

    private function validateAmount(float|string $amount): float
    {
        $value = round((float) $amount, 2);

        if ($value <= 0) {
            throw ValidationException::withMessages(['amount' => ['المبلغ يجب أن يكون أكبر من صفر']]);
        }

        return $value;
    }

    private function describe(FinancialTransaction $transaction): array
    {
        return $transaction->only([
            'person_type', 'person_id', 'transaction_type', 'direction',
            'amount', 'related_person_type', 'related_person_id', 'description', 'reference',
        ]);
    }
}
