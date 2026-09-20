<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FinancePersonType;
use App\Enums\FinancialDirection;
use App\Http\Controllers\Controller;
use App\Models\FinancialTransaction;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function __construct(private readonly FinanceService $finance) {}

    /** Persons list with derived balances (index of the finance area). */
    public function index(Request $request): View
    {
        $type = FinancePersonType::tryFrom($request->input('type', 'student')) ?? FinancePersonType::Student;
        $query = $request->string('q')->toString();
        $onlyOwing = $request->boolean('owing');

        $personModel = FinanceService::personModel($type->value);
        $table = (new $personModel)->getTable();

        // Net outstanding balance subquery (balance = Σ money_out − Σ money_in),
        // so sorting and the "owing only" filter run in SQL instead of PHP.
        $net = FinancialTransaction::query()
            ->selectRaw('sum(case when direction = ? then amount else -amount end) as net', [FinancialDirection::MoneyOut->value])
            ->where('tenant_id', config('app.current_tenant_id'))
            ->where('person_type', $type->value)
            ->whereColumn('person_id', "{$table}.id")
            ->groupBy('person_id');

        $people = $personModel::query()
            ->select("{$table}.id", "{$table}.name")
            ->selectSub($net, 'balance')
            ->when($query !== '', fn ($q) => $q->where("{$table}.name", 'like', "%{$query}%"))
            ->when($onlyOwing, fn ($q) => $q->having('balance', '>', 0))
            ->orderByDesc('balance')
            ->orderBy("{$table}.name")
            ->paginate(25)
            ->withQueryString();

        $summaries = $this->finance->summariesForPeople($type->value, $people->pluck('id')->all());

        $paginator = $people->through(
            fn ($person) => [
                'person' => $person,
                'summary' => $summaries->get($person->id, [
                    'charges' => 0.0, 'payments' => 0.0, 'refunds' => 0.0, 'transfers' => 0.0, 'adjustments' => 0.0,
                    'received' => 0.0, 'sent' => 0.0, 'balance' => 0.0,
                ]),
            ]
        );

        return view('admin.finance.index', [
            'type' => $type,
            'people' => $paginator,
            'q' => $query,
            'owing' => $onlyOwing,
        ]);
    }

    /** Person financial profile: balances, transactions, add forms (spec §16). */
    public function show(string $personType, string $person): View
    {
        $type = FinancePersonType::tryFrom($personType);

        abort_if(! $type, 404);

        $personModel = FinanceService::personModel($type->value)::query()->find($person);

        abort_if(! $personModel || $personModel->tenant_id !== config('app.current_tenant_id'), 404);

        $transactions = FinancialTransaction::query()
            ->forPerson($type->value, $person)
            ->with(['creator:id,name'])
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $otherStudents = ($type === FinancePersonType::Student
            ? Student::query()->where('id', '!=', $person)
            : Teacher::query()->where('id', '!=', $person))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.finance.show', [
            'personType' => $type,
            'person' => $personModel,
            'summary' => $this->finance->summary($type->value, $person),
            'transactions' => $transactions,
            'otherPeople' => $otherStudents,
        ]);
    }

    /** Record a charge/payment/refund/adjustment for a person. */
    public function storeTransaction(Request $request): RedirectResponse
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
            $this->finance->record($data, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', 'تم تسجيل العملية المالية');
    }

    /** Person-to-person transfer (mirrored on both ledgers). */
    public function storeTransfer(Request $request): RedirectResponse
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
            $this->finance->transfer(
                $data['from_type'], $data['from_id'],
                $data['to_type'], $data['to_id'],
                $data['amount'], $data['description'] ?? null,
                $request->user(),
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', 'تم تسجيل التحويل بين الطرفين');
    }

    /** Reverse/correct a transaction — history preserved, reversal is a new row. */
    public function reverse(Request $request, FinancialTransaction $transaction): RedirectResponse
    {
        try {
            $this->finance->reverse($transaction->id, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'تم عكس العملية (سجل العملية الأصلي محفوظ)');
    }
}
