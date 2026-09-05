<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\FinancePersonType;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\FinancialTransaction;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FinanceController extends BaseApiController
{
    public function __construct(private readonly FinanceService $finance) {}

    /** People search with derived balances (persons + outstanding). */
    public function people(Request $request): JsonResponse
    {
        $type = $request->input('type', FinancePersonType::Student->value);

        if (! in_array($type, [FinancePersonType::Student->value, FinancePersonType::Teacher->value], true)) {
            return $this->error('type يجب أن يكون student أو teacher', 422);
        }

        $query = $request->string('q')->toString();
        $model = FinanceService::personModel($type);

        $people = $model::query()
            ->when($query !== '', fn ($q) => $q->where('name', 'like', "%{$query}%"))
            ->orderBy('name')
            ->paginate(20);

        $persons = $people->map(function ($person) use ($type) {
            $balance = $this->finance->balance($type, $person->id);

            return [
                'id' => $person->id,
                'name' => $person->name,
                'phone' => $person->phone ?? $person->guardian_phone ?? null,
                'status' => $person->status ?? ($person->is_active ? 'active' : 'inactive'),
                'balance' => number_format($balance, 2),
                'balance_is_debt' => $balance > 0,
            ];
        })->values();

        return $this->success([
            'people' => $persons,
            'pagination' => [
                'current_page' => $people->currentPage(),
                'last_page' => $people->lastPage(),
                'per_page' => $people->perPage(),
                'total' => $people->total(),
            ],
        ]);
    }

    /** Person ledger: summary + paginated transactions. */
    public function person(string $personType, string $personId): JsonResponse
    {
        $this->finance->personOrFail($personType, $personId, (string) config('app.current_tenant_id'));

        $transactions = FinancialTransaction::query()
            ->forPerson($personType, $personId)
            ->latest()
            ->paginate(25);

        return $this->success([
            'summary' => $this->finance->summary($personType, $personId),
            'transactions' => $transactions->items(),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $personTypes = collect(FinancePersonType::cases())->pluck('value')->all();

        $data = $request->validate([
            'person_type' => ['required', Rule::in($personTypes)],
            'person_id' => ['required', 'uuid'],
            'transaction_type' => ['required', 'in:charge,payment,refund,adjustment'],
            'direction' => ['nullable', 'in:money_in,money_out'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:1000'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $transaction = $this->finance->record($data, $request->user());
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        }

        return $this->created([
            'id' => $transaction->id,
            'balance' => number_format($this->finance->balance($data['person_type'], $data['person_id']), 2),
        ], 'تم تسجيل العملية المالية');
    }

    public function transfer(Request $request): JsonResponse
    {
        $personTypes = collect(FinancePersonType::cases())->pluck('value')->all();

        $data = $request->validate([
            'from_type' => ['required', Rule::in($personTypes)],
            'from_id' => ['required', 'uuid'],
            'to_type' => ['required', Rule::in($personTypes)],
            'to_id' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            [$sender, $receiver] = $this->finance->transfer(
                $data['from_type'], $data['from_id'],
                $data['to_type'], $data['to_id'],
                $data['amount'], $data['description'] ?? null,
                $request->user(),
            );
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        }

        return $this->created([
            'sender_transaction_id' => $sender->id,
            'receiver_transaction_id' => $receiver->id,
            'reference' => $sender->reference,
        ], 'تم تسجيل التحويل بين الطرفين');
    }

    /** Reverse a transaction (transfers reverse both mirrored legs). */
    public function reverse(Request $request, string $transactionId): JsonResponse
    {
        try {
            $reversals = $this->finance->reverse($transactionId, $request->user());
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        }

        return $this->created([
            'reversal_ids' => collect($reversals)->pluck('id'),
        ], 'تم عكس العملية بنجاح');
    }
}
