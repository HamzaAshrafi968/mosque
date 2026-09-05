<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\FinancePersonType;
use App\Enums\FinancialDirection;
use App\Enums\FinancialTransactionType;
use App\Models\FinancialTransaction;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Sheikh cash ledger (spec §25-§32) — finance permissions gate every route.
 *
 * Ledger rows are recorded on the sheikh's own Teacher person:
 * - payment (money in)  → قبض/استلام نقدية
 * - refund (money out)  → دفع/تسليم نقدية (بما فيها تسليم إيرادات الجامع)
 * - transfer            → تحويل شخصي (طرفان، عبر FinanceService::transfer)
 * - adjustment          → تسوية
 * Received / handed / remaining are always derived from the ledger rows.
 */
class FinanceController extends BaseTeacherController
{
    public function __construct(private readonly FinanceService $finance) {}

    public function index(Request $request): View
    {
        $teacher = $this->currentTeacher($request);

        $totals = FinancialTransaction::query()
            ->forPerson('teacher', $teacher->id)
            ->selectRaw("
                sum(case when direction = 'money_in'  then amount else 0 end) as received,
                sum(case when direction = 'money_out' then amount else 0 end) as handed
            ")
            ->first();

        $received = (float) ($totals->received ?? 0);
        $handed = (float) ($totals->handed ?? 0);

        $transactions = FinancialTransaction::query()
            ->forPerson('teacher', $teacher->id)
            ->with(['creator:id,name', 'relatedPerson'])
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $mySections = $this->manageableSections($request);

        return view('teacher.finance.index', [
            'received' => $received,
            'handed' => $handed,
            'remaining' => round($received - $handed, 2),
            'transactions' => $transactions,
            'sectionIds' => $mySections->pluck('id'),
        ]);
    }

    /** Receipt form: choose the payer from my section students or colleagues. */
    public function receiveForm(Request $request): View
    {
        $teacher = $this->currentTeacher($request);

        $payingStudents = Student::query()
            ->active()
            ->whereIn('section_id', $this->manageableSections($request)->pluck('id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        $payingTeachers = Teacher::query()
            ->where('id', '!=', $teacher->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('teacher.finance.receive', [
            'payingStudents' => $payingStudents,
            'payingTeachers' => $payingTeachers,
        ]);
    }

    public function receive(Request $request): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);

        $data = $request->validate([
            'from_type' => ['required', Rule::in(['student', 'teacher'])],
            'from_id' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:1000'],
            'date' => ['nullable', 'date'],
        ]);

        try {
            $this->finance->record([
                'person_type' => 'teacher',
                'person_id' => $teacher->id,
                'transaction_type' => FinancialTransactionType::Payment->value,
                'amount' => $data['amount'],
                'description' => $data['description'] ?? null,
                'related_person_type' => $data['from_type'],
                'related_person_id' => $data['from_id'],
            ], $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('teacher.finance.index')->with('success', 'تم تسجيل القبض في الدفتر المالي');
    }

    public function transferForm(Request $request): View
    {
        $teacher = $this->currentTeacher($request);

        $payingStudents = Student::query()
            ->active()
            ->whereIn('section_id', $this->manageableSections($request)->pluck('id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        $colleagues = Teacher::query()
            ->where('id', '!=', $teacher->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('teacher.finance.transfer', [
            'payingStudents' => $payingStudents,
            'colleagues' => $colleagues,
        ]);
    }

    /**
     * Person-to-person transfer preserves both sides (spec §29): when the
     * sheikh gives money to someone (student refund / colleague) a mirrored
     * pair of ledger rows is written under one reference.
     */
    public function transfer(Request $request): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);

        $data = $request->validate([
            'to_type' => ['required', Rule::in(['student', 'teacher'])],
            'to_id' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->finance->transfer(
                'teacher', $teacher->id,
                $data['to_type'], $data['to_id'],
                $data['amount'], $data['description'] ?? null,
                $request->user(),
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('teacher.finance.index')->with('success', 'تم تسجيل التحويل بين الطرفين');
    }

    /** Adjustment row (both directions) to correct the sheikh ledger. */
    public function adjust(Request $request): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);

        $data = $request->validate([
            'direction' => ['required', Rule::in([FinancialDirection::MoneyIn->value, FinancialDirection::MoneyOut->value])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->finance->record([
                'person_type' => 'teacher',
                'person_id' => $teacher->id,
                'transaction_type' => FinancialTransactionType::Adjustment->value,
                'direction' => $data['direction'],
                'amount' => $data['amount'],
                'description' => $data['description'],
            ], $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('teacher.finance.index')->with('success', 'تم تسجيل التسوية المالية');
    }

    /** Reversal: the ledger history is preserved and a correction row is added. */
    public function reverse(Request $request, FinancialTransaction $transaction): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);

        abort_unless($transaction->tenant_id === $request->user()->tenant_id, 403);
        abort_unless($transaction->person_type === FinancePersonType::Teacher && $transaction->person_id === $teacher->id, 403);

        try {
            $this->finance->reverse($transaction->id, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('teacher.finance.index')->with('success', 'تم عكس العملية مع حفظ السجل الأصلي');
    }
}
