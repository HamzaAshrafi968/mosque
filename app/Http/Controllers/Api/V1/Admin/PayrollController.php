<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PayrollStatus;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\FinancialTransaction;
use App\Models\HourlyRate;
use App\Models\Teacher;
use App\Services\HourlyRateService;
use App\Services\PayrollPeriodService;
use App\Support\TimesheetPayload;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * API كشوف الرواتب (admin): الملخصات، كشف الأستاذ، الدفع الجزئي،
 * الإغلاق/إعادة الفتح، وسجل أسعار الساعة.
 */
class PayrollController extends BaseApiController
{
    public function __construct(
        private readonly PayrollPeriodService $payroll,
        private readonly HourlyRateService $rates,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $month = $this->resolveMonth($request);
        $search = $request->string('q')->toString();

        $teachers = Teacher::query()
            ->with(['studySession:id,name,gender', 'studySessions:id,name,gender'])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(25);

        $summaries = $this->payroll->summaries($teachers->getCollection(), $month);

        $rows = $teachers->getCollection()->map(fn (Teacher $teacher) => [
            'teacher' => TimesheetPayload::teacher($teacher),
            'summary' => TimesheetPayload::summary($summaries[$teacher->id]),
        ]);

        $collection = collect($summaries);

        return $this->success([
            'month' => $month->format('Y-m'),
            'totals' => [
                'minutes' => (int) $collection->sum('total_minutes'),
                'gross' => round((float) $collection->sum('gross'), 2),
                'paid' => round((float) $collection->sum('paid'), 2),
                'remaining' => round((float) $collection->sum('remaining'), 2),
                'closed' => $collection->filter(fn (array $row) => $row['status'] === PayrollStatus::Closed)->count(),
            ],
            'teachers' => $rows->values(),
            'pagination' => [
                'current_page' => $teachers->currentPage(),
                'last_page' => $teachers->lastPage(),
                'per_page' => $teachers->perPage(),
                'total' => $teachers->total(),
            ],
        ]);
    }

    public function sheet(Teacher $teacher, Request $request): JsonResponse
    {
        $month = $this->resolveMonth($request);
        $teacher->load(['studySession:id,name,gender', 'studySessions:id,name,gender']);

        $summary = $this->payroll->summary($teacher, $month);
        $period = $summary['period'];

        $payments = $period !== null
            ? FinancialTransaction::query()
                ->where('payroll_period_id', $period->id)
                ->with(['creator:id,name', 'reversal:id,reverses_id'])
                ->latest()
                ->get()
                ->map(fn (FinancialTransaction $payment) => [
                    'id' => $payment->id,
                    'amount' => (float) $payment->amount,
                    'description' => $payment->description,
                    'payment_method' => $payment->payment_method,
                    'reference' => $payment->reference,
                    'reversed' => $payment->reversal !== null,
                    'recorded_by' => $payment->creator?->name,
                    'created_at' => $payment->created_at?->toIso8601String(),
                ])
            : collect();

        return $this->success([
            'teacher' => TimesheetPayload::teacher($teacher),
            'month' => $month->format('Y-m'),
            'summary' => TimesheetPayload::summary($summary),
            'payments' => $payments->values(),
            'rates' => HourlyRate::query()
                ->where('teacher_id', $teacher->id)
                ->orderByDesc('effective_from')
                ->get()
                ->map(fn (HourlyRate $rate) => [
                    'id' => $rate->id,
                    'rate' => (float) $rate->rate,
                    'effective_from' => $rate->effective_from->toDateString(),
                    'effective_to' => $rate->effective_to?->toDateString(),
                    'status' => $rate->statusLabel(),
                ])->values(),
        ]);
    }

    public function pay(Teacher $teacher, Request $request): JsonResponse
    {
        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $month = $this->resolveMonth($request);
        $period = $this->payroll->ensure($teacher, $month, $request->user());

        try {
            $transaction = $this->payroll->recordPayment(
                $period,
                $data['amount'],
                $data['payment_method'] ?? null,
                $data['reference'] ?? null,
                $data['description'] ?? null,
                $request->user(),
            );
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        }

        return $this->created([
            'transaction_id' => $transaction->id,
            'period_id' => $period->id,
            'paid' => $this->payroll->paidFor($period),
            'remaining' => round((float) $period->gross_amount - $this->payroll->paidFor($period), 2),
        ], 'تم تسجيل الدفعة');
    }

    public function close(Teacher $teacher, Request $request): JsonResponse
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $month = $this->resolveMonth($request);

        try {
            $period = $this->payroll->close($teacher, $month, $request->user());
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        }

        return $this->success([
            'period_id' => $period->id,
            'month' => $period->monthKey(),
            'gross' => (float) $period->gross_amount,
            'status' => $period->status->value,
        ], 'تم إغلاق كشف الشهر');
    }

    public function reopen(Teacher $teacher, Request $request): JsonResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $month = $this->resolveMonth($request);
        $period = $this->payroll->findPeriod($teacher, $month);

        if ($period === null || ! $period->isClosed()) {
            return $this->error('لا يوجد كشف مغلق لهذا الشهر', 422);
        }

        $this->payroll->reopen($period, $data['reason'], $request->user());

        return $this->success(['period_id' => $period->id], 'تم إعادة فتح كشف الشهر');
    }

    public function ratesIndex(Request $request): JsonResponse
    {
        $search = $request->string('q')->toString();

        $rates = HourlyRate::query()
            ->with('teacher:id,name')
            ->when($search !== '', fn ($query) => $query->whereHas(
                'teacher',
                fn ($teacher) => $teacher->where('name', 'like', "%{$search}%")
            ))
            ->orderByDesc('effective_from')
            ->paginate(30);

        return $this->success([
            'rates' => $rates->getCollection()->map(fn (HourlyRate $rate) => [
                'id' => $rate->id,
                'teacher_id' => $rate->teacher_id,
                'teacher_name' => $rate->teacher?->name,
                'rate' => (float) $rate->rate,
                'effective_from' => $rate->effective_from->toDateString(),
                'effective_to' => $rate->effective_to?->toDateString(),
                'status' => $rate->statusLabel(),
            ])->values(),
            'pagination' => [
                'current_page' => $rates->currentPage(),
                'last_page' => $rates->lastPage(),
                'per_page' => $rates->perPage(),
                'total' => $rates->total(),
            ],
        ]);
    }

    public function ratesStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'teacher_id' => ['required', 'uuid'],
            'rate' => ['required', 'numeric', 'gt:0'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
        ]);

        $teacher = Teacher::query()->find($data['teacher_id']);

        if ($teacher === null) {
            return $this->notFound('المعلم غير موجود');
        }

        try {
            $rate = $this->rates->create($teacher, $data, $request->user());
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        }

        return $this->created([
            'id' => $rate->id,
            'rate' => (float) $rate->rate,
            'effective_from' => $rate->effective_from->toDateString(),
        ], 'تمت إضافة سعر الساعة — يُحتسب المستحق من ساعات العمل المسجّلة');
    }

    public function ratesDestroy(Request $request, HourlyRate $hourlyRate): JsonResponse
    {
        $teacher = $hourlyRate->teacher;
        $hourlyRate->delete();

        if ($teacher !== null) {
            $this->payroll->refreshOpenForTeacher($teacher);
        }

        return $this->noContent();
    }

    private function resolveMonth(Request $request): CarbonImmutable
    {
        $input = $request->validate(['month' => ['nullable', 'date_format:Y-m']])['month'] ?? null;

        return $input
            ? CarbonImmutable::createFromFormat('Y-m', $input)->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();
    }
}
