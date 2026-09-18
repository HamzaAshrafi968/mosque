<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PayrollStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FinancialTransaction;
use App\Models\HourlyRate;
use App\Models\Teacher;
use App\Models\WorkSlot;
use App\Services\AuditLogger;
use App\Services\FinanceService;
use App\Services\PayrollPeriodService;
use App\Services\WorkSlotService;
use App\Support\TimesheetAggregator;
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
        private readonly WorkSlotService $slots,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        [$month, $monthInput] = $this->resolveMonth($request);
        $search = $request->string('q')->toString();

        $teachers = Teacher::query()
            ->with(['studySession:id,name', 'studySessions:id,name'])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
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
            'currency' => FinanceService::DEFAULT_CURRENCY,
        ]);
    }

    public function sheet(Teacher $teacher, Request $request): View
    {
        [$month, $monthInput] = $this->resolveMonth($request);
        $teacher->load(['studySession:id,name', 'studySessions:id,name']);

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
            'weeks' => TimesheetAggregator::monthWeeks($month->year, $month->month),
            'slotsByDate' => $slots->groupBy(fn (WorkSlot $slot) => $slot->date->toDateString()),
            'payments' => $payments,
            'auditLogs' => $auditLogs,
            'rates' => HourlyRate::query()
                ->where('teacher_id', $teacher->id)
                ->orderByDesc('effective_from')
                ->get(),
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

    /** تحديد/تعديل الراتب الشهري للأستاذ (للنوع الشهري). */
    public function updateSalary(Request $request, Teacher $teacher): RedirectResponse
    {
        $data = $request->validate([
            'monthly_salary' => ['nullable', 'numeric', 'min:0'],
            'pay_type' => ['nullable', 'in:monthly,hourly'],
        ]);

        $before = $teacher->getAttributes();
        $update = [];

        if ($request->exists('monthly_salary')) {
            $update['monthly_salary'] = $data['monthly_salary'];
        }

        if (! empty($data['pay_type'])) {
            $update['pay_type'] = $data['pay_type'];
        }

        if ($update !== []) {
            $teacher->update($update);
        }

        $this->audit->logModel('teacher.salary_updated', $teacher, $before, actor: $request->user());
        $this->payroll->refreshOpenForTeacher($teacher);

        return back()->with('success', 'تم تحديث بيانات الأجر');
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

    /** معاينة إغلاق الشهر لكل المعلمين مع تحذيرات ما قبل الإغلاق. */
    public function closePreview(Request $request): View
    {
        [$month, $monthInput] = $this->resolveMonth($request);

        $teachers = Teacher::query()->orderBy('name')->get();
        $summaries = $this->payroll->summaries($teachers, $month);
        $warnings = [];

        foreach ($teachers as $teacher) {
            $summary = $summaries[$teacher->id];

            $warnings[$teacher->id] = $this->warningsFor($teacher, $summary);
        }

        return view('admin.payroll.close', [
            'teachers' => $teachers,
            'summaries' => $summaries,
            'warnings' => $warnings,
            'totals' => $this->totals($summaries),
            'month' => $month,
            'monthInput' => $monthInput,
            'currency' => FinanceService::DEFAULT_CURRENCY,
        ]);
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
                'المعلم', 'نوع الأجر', 'الدقائق', 'الساعات', 'السعر/الراتب',
                'الإجمالي', 'المدفوع', 'المتبقي', 'الحالة',
            ]);

            foreach ($teachers as $teacher) {
                $summary = $summaries[$teacher->id];

                fputcsv($handle, [
                    $teacher->name,
                    $summary['pay_type']->label(),
                    $summary['total_minutes'],
                    WorkSlot::formatMinutes($summary['total_minutes']),
                    number_format((float) ($summary['hourly_rate'] ?? $summary['monthly_salary'] ?? 0), 2, '.', ''),
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

    /** @param  array<string, mixed>  $summary */
    private function warningsFor(Teacher $teacher, array $summary): array
    {
        $warnings = [];

        if ($summary['missing_rates'] !== []) {
            $warnings[] = 'لا يوجد سعر ساعة في: '.implode('، ', $summary['missing_rates']);
        }

        if ($summary['total_minutes'] === 0 && $summary['pay_type']->value === 'hourly') {
            $warnings[] = 'لا توجد فترات عمل مسجلة';
        }

        if ($summary['pay_type']->value === 'monthly' && $summary['monthly_salary'] === null) {
            $warnings[] = 'الراتب الشهري غير محدد';
        }

        if ($summary['paid'] <= 0 && $summary['gross'] > 0) {
            $warnings[] = 'لا توجد دفعات مسجلة';
        }

        if ($summary['status'] === PayrollStatus::Closed) {
            $warnings[] = 'الكشف مغلق مسبقاً';
        }

        return $warnings;
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
