<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FinancePersonType;
use App\Http\Controllers\Controller;
use App\Models\FinancialTransaction;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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

        $people = FinanceService::personModel($type->value)::query()
            ->when($query !== '', fn ($q) => $q->where('name', 'like', "%{$query}%"))
            ->orderBy('name')
            ->get(['id', 'name']);

        $rows = $people
            ->map(fn ($person) => [
                'person' => $person,
                'summary' => $this->finance->summary($type->value, $person->id),
            ])
            ->filter(fn ($row) => ! $onlyOwing || $row['summary']['balance'] > 0)
            ->sortByDesc(fn ($row) => $row['summary']['balance'])
            ->values();

        $perPage = 25;
        $page = max(1, (int) $request->input('page', 1));
        $slice = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $slice,
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
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
