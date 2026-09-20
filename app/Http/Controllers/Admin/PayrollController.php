<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PayrollStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FinancialTransaction;
use App\Models\HourlyRate;
use App\Models\StudySession;
use App\Models\Teacher;
use App\Models\WorkSlot;
use App\Services\FinanceService;
use App\Services\HourlyRateService;
use App\Services\PayrollPeriodService;
use App\Support\TimesheetAggregator;
use App\Support\XlsxWriter;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * كشوف رواتب المعلمين: احتساب شهري من الفترات الفعلية والأسعار، دفع جزئي،
 * إغلاق/إعادة فتح، وتصدير — مع بقاء الدفعات في السجل المالي.
 */
class PayrollController extends Controller
{
    public function __construct(
        private readonly PayrollPeriodService $payroll,
        private readonly HourlyRateService $rates,
    ) {}

    public function index(Request $request): View
    {
        [$month, $monthInput] = $this->resolveMonth($request);
        $search = $request->string('q')->toString();
        $sessionId = $request->input('session');

        $teachers = Teacher::query()
            ->with(['studySession:id,name,gender', 'studySessions:id,name,gender'])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->when($sessionId, fn ($query) => $query->where(fn ($inner) => $inner
                ->where('study_session_id', $sessionId)
                ->orWhereHas('studySessions', fn ($sessions) => $sessions->whereKey($sessionId))))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $summaries = $this->payroll->summaries($teachers->getCollection(), $month);

        return view('admin.payroll.index', [
            'teachers' => $teachers,
            'summaries' => $summaries,
            'totals' => $this->totals($summaries),
            'month' => $month,
            'monthInput' => $monthInput,
            'search' => $search,
            'sessionId' => $sessionId,
            'sessions' => StudySession::query()->orderForDisplay()->get(),
            'currency' => FinanceService::DEFAULT_CURRENCY,
        ]);
    }

    public function sheet(Teacher $teacher, Request $request): View
    {
        [$month, $monthInput] = $this->resolveMonth($request);
        $teacher->load(['studySession:id,name,gender', 'studySessions:id,name,gender']);

        $slots = $this->payroll->slotsFor($teacher, $month);
        $summary = $this->payroll->summary($teacher, $month, null, $slots);
        $period = $summary['period'];

        $payments = $period !== null
            ? FinancialTransaction::query()
                ->where('payroll_period_id', $period->id)
                ->with(['creator:id,name', 'reversal:id,reverses_id'])
                ->latest()
                ->get()
            : collect();

        $auditLogs = $period !== null
            ? AuditLog::query()
                ->where('entity_type', 'payroll_period')
                ->where('entity_id', $period->id)
                ->with('user:id,name')
                ->latest()
                ->limit(20)
                ->get()
            : collect();

        return view('admin.payroll.sheet', [
            'teacher' => $teacher,
            'month' => $month,
            'monthInput' => $monthInput,
            'summary' => $summary,
            'slots' => $slots,
            'blocks' => TimesheetAggregator::monthDayBlocks($month->year, $month->month),
            'slotsByDate' => $slots->groupBy(fn (WorkSlot $slot) => $slot->date->toDateString()),
            'payments' => $payments,
            'auditLogs' => $auditLogs,
            'rates' => HourlyRate::query()
                ->where('teacher_id', $teacher->id)
                ->orderByDesc('effective_from')
                ->get(),
            'currentRate' => HourlyRate::query()
                ->where('teacher_id', $teacher->id)
                ->activeOn(now()->toDateString())
                ->orderByDesc('effective_from')
                ->first(),
            'currency' => FinanceService::DEFAULT_CURRENCY,
        ]);
    }

    /** تسجيل دفعة راتب مرتبطة بكشف الشهر (دفع جزئي مسموح). */
    public function pay(Request $request, Teacher $teacher): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $month = $this->monthFromInput($data['month'] ?? null);
        $period = $this->payroll->ensure($teacher, $month, $request->user());

        try {
            $this->payroll->recordPayment(
                $period,
                $data['amount'],
                $data['payment_method'] ?? null,
                $data['reference'] ?? null,
                $data['description'] ?? null,
                $request->user(),
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with(
            'success',
            $teacher->user_id
                ? 'تم تسجيل الدفعة وأُرسل إشعار للأستاذ'
                : 'تم تسجيل الدفعة (الأستاذ بلا حساب دخول — لا إشعار)'
        );
    }

    /** تغيير سعر الساعة من كشف الأستاذ (يُغلق السعر السابق ويسري الجديد من تاريخه). */
    public function updateRate(Request $request, Teacher $teacher): RedirectResponse
    {
        $data = $request->validate([
            'rate' => ['required', 'numeric', 'gt:0'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
        ]);

        try {
            $this->rates->change($teacher, $data['rate'], $data['effective_from'], $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', 'تم تحديث سعر الساعة — أُعيد احتساب الكشوف المفتوحة فوراً');
    }

    public function close(Request $request, Teacher $teacher): RedirectResponse
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $month = $this->monthFromInput($data['month']);

        try {
            $this->payroll->close($teacher, $month, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', 'تم إغلاق كشف '.$month->format('Y-m'));
    }

    public function reopen(Request $request, Teacher $teacher): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $month = $this->monthFromInput($data['month']);
        $period = $this->payroll->findPeriod($teacher, $month);

        if ($period === null || ! $period->isClosed()) {
            return back()->withErrors(['month' => ['لا يوجد كشف مغلق لهذا الشهر']]);
        }

        $this->payroll->reopen($period, $data['reason'], $request->user());

        return back()->with('success', 'تم إعادة فتح كشف '.$month->format('Y-m'));
    }

    /** إغلاق كل كشوف الشهر بعد تأكيد كتابة الشهر. */
    public function closeAll(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'confirm_month' => ['required', 'string'],
        ]);

        if ($data['confirm_month'] !== $data['month']) {
            return back()->withErrors(['confirm_month' => ['اكتب الشهر بصيغة YYYY-MM للتأكيد']])->withInput();
        }

        $month = $this->monthFromInput($data['month']);
        $teachers = Teacher::query()->orderBy('name')->get();

        $closed = 0;
        $skipped = [];

        foreach ($teachers as $teacher) {
            $summary = $this->payroll->summary($teacher, $month);

            if ($summary['total_minutes'] === 0 && $summary['gross'] <= 0) {
                continue;
            }

            if ($summary['missing_rates'] !== []) {
                $skipped[] = $teacher->name;

                continue;
            }

            try {
                $this->payroll->close($teacher, $month, $request->user());
                $closed++;
            } catch (ValidationException) {
                $skipped[] = $teacher->name;
            }
        }

        $message = "تم إغلاق {$closed} كشف";

        if ($skipped !== []) {
            $message .= ' — تعذّر: '.implode('، ', $skipped);
        }

        return redirect()
            ->route('admin.payroll.index', ['month' => $data['month']])
            ->with('success', $message);
    }

    /** تصدير كشف الشهر Excel (.xlsx) بلا مكتبات خارجية. */
    public function exportExcel(Request $request): StreamedResponse
    {
        [$month, $monthInput] = $this->resolveMonth($request);
        $teachers = Teacher::query()->orderBy('name')->get();
        $summaries = $this->payroll->summaries($teachers, $month);

        $rows = [[
            'المعلم', 'الساعات', 'سعر الساعة', 'الإجمالي', 'المدفوع', 'المتبقي', 'حالة الكشف', 'حالة السداد',
        ]];

        foreach ($teachers as $teacher) {
            $summary = $summaries[$teacher->id];

            $rows[] = [
                $teacher->name,
                WorkSlot::formatMinutes($summary['total_minutes']),
                $summary['hourly_rate'] !== null
                    ? (float) $summary['hourly_rate']
                    : ($summary['rate_is_mixed'] ? 'سعر متغيّر' : ($summary['current_rate'] !== null ? (float) $summary['current_rate'] : '—')),
                $summary['gross'],
                $summary['paid'],
                $summary['remaining'],
                $summary['status']->label(),
                $summary['state']->label(),
            ];
        }

        return XlsxWriter::download(
            "payroll-{$monthInput}.xlsx",
            $rows,
            [22, 12, 12, 12, 12, 12, 12, 12]
        );
    }

    /** نسخة طباعة (A4) لكل كشوف الشهر — تُحفظ PDF من المتصفح. */
    public function printAll(Request $request): View
    {
        [$month, $monthInput] = $this->resolveMonth($request);
        $teachers = Teacher::query()->orderBy('name')->get();
        $summaries = $this->payroll->summaries($teachers, $month);

        return view('admin.payroll.print', [
            'teachers' => $teachers,
            'summaries' => $summaries,
            'totals' => $this->totals($summaries),
            'month' => $month,
            'monthInput' => $monthInput,
            'currency' => FinanceService::DEFAULT_CURRENCY,
        ]);
    }

    /** نسخة طباعة (A4) لكشف أستاذ واحد — تُحفظ PDF من المتصفح. */
    public function printSheet(Teacher $teacher, Request $request): View
    {
        [$month, $monthInput] = $this->resolveMonth($request);
        $teacher->load(['studySession:id,name,gender', 'studySessions:id,name,gender']);

        $slots = $this->payroll->slotsFor($teacher, $month);
        $summary = $this->payroll->summary($teacher, $month, null, $slots);
        $period = $summary['period'];

        $payments = $period !== null
            ? FinancialTransaction::query()
                ->where('payroll_period_id', $period->id)
                ->with(['creator:id,name', 'reversal:id,reverses_id'])
                ->latest()
                ->get()
            : collect();

        return view('admin.payroll.sheet-print', [
            'teacher' => $teacher,
            'month' => $month,
            'monthInput' => $monthInput,
            'summary' => $summary,
            'slotsByDate' => $slots->groupBy(fn (WorkSlot $slot) => $slot->date->toDateString()),
            'payments' => $payments,
            'currency' => FinanceService::DEFAULT_CURRENCY,
        ]);
    }

    /** تصدير كشف الشهر CSV (أرقام مطابقة للمعروض). */
    public function export(Request $request): StreamedResponse
    {
        [$month, $monthInput] = $this->resolveMonth($request);
        $teachers = Teacher::query()->orderBy('name')->get();
        $summaries = $this->payroll->summaries($teachers, $month);

        return response()->streamDownload(function () use ($teachers, $summaries) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'المعلم', 'الساعات', 'سعر الساعة', 'الإجمالي', 'المدفوع', 'المتبقي', 'الحالة',
            ]);

            foreach ($teachers as $teacher) {
                $summary = $summaries[$teacher->id];

                fputcsv($handle, [
                    $teacher->name,
                    WorkSlot::formatMinutes($summary['total_minutes']),
                    $summary['hourly_rate'] !== null
                        ? number_format($summary['hourly_rate'], 2, '.', '')
                        : ($summary['rate_is_mixed']
                            ? 'متغيّر'
                            : ($summary['current_rate'] !== null ? number_format($summary['current_rate'], 2, '.', '') : '—')),
                    number_format($summary['gross'], 2, '.', ''),
                    number_format($summary['paid'], 2, '.', ''),
                    number_format($summary['remaining'], 2, '.', ''),
                    $summary['state']->label(),
                ]);
            }

            fclose($handle);
        }, "payroll-{$monthInput}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @param  array<string, array<string, mixed>>  $summaries */
    private function totals(array $summaries): array
    {
        $collection = collect($summaries);

        return [
            'minutes' => (int) $collection->sum('total_minutes'),
            'gross' => round((float) $collection->sum('gross'), 2),
            'paid' => round((float) $collection->sum('paid'), 2),
            'remaining' => round((float) $collection->sum('remaining'), 2),
            'closed' => $collection->filter(fn (array $row) => $row['status'] === PayrollStatus::Closed)->count(),
        ];
    }

    /** @return array{0: CarbonImmutable, 1: string} */
    private function resolveMonth(Request $request): array
    {
        $input = $request->validate(['month' => ['nullable', 'date_format:Y-m']])['month'] ?? null;
        $month = $this->monthFromInput($input);

        return [$month, $month->format('Y-m')];
    }

    private function monthFromInput(?string $input): CarbonImmutable
    {
        return $input
            ? CarbonImmutable::createFromFormat('Y-m', $input)->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();
    }
}
