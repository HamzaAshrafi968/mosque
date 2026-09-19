<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HourlyRate;
use App\Models\Teacher;
use App\Services\AuditLogger;
use App\Services\FinanceService;
use App\Services\HourlyRateService;
use App\Services\PayrollPeriodService;
use App\Support\QuranProgramSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * «الإعدادات → أسعار الساعة»: سجل تاريخي لسعر ساعة كل أستاذ. إضافة السعر
 * تحوّل الأستاذ تلقائياً إلى الأجر بالساعة (عبر HourlyRateService) فيُحتسب
 * راتبه من فترات عمله، وتعديل السعر لا يمس الكشوف المغلقة (اللقطات ثابتة).
 */
class HourlyRateController extends Controller
{
    public function __construct(
        private readonly HourlyRateService $rates,
        private readonly PayrollPeriodService $payroll,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();
        $selectedTeacherId = $request->string('teacher_id')->toString();

        $rates = HourlyRate::query()
            ->with(['teacher:id,name', 'creator:id,name'])
            ->when($search !== '', fn ($query) => $query->whereHas(
                'teacher',
                fn ($teacher) => $teacher->where('name', 'like', "%{$search}%")
            ))
            ->orderByDesc('effective_from')
            ->orderBy('teacher_id')
            ->paginate(30)
            ->withQueryString();

        // الأسعار السارية اليوم — لملخص الشهر الحالي (ساعات العمل × السعر).
        $activeRates = HourlyRate::query()
            ->with('teacher:id,name,pay_type')
            ->activeOn(now()->toDateString())
            ->get()
            ->filter(fn (HourlyRate $rate) => $rate->teacher !== null)
            ->unique('teacher_id')
            ->values();

        $month = now()->startOfMonth();
        $teachersWithRates = $activeRates->pluck('teacher')->values();

        return view('admin.settings.hourly-rates', [
            'rates' => $rates,
            'teachers' => Teacher::query()->orderBy('name')->get(['id', 'name', 'pay_type']),
            'search' => $search,
            'selectedTeacherId' => $selectedTeacherId,
            'activeRates' => $activeRates,
            'summaries' => $teachersWithRates->isEmpty()
                ? []
                : $this->payroll->summaries($teachersWithRates, $month),
            'monthInput' => $month->format('Y-m'),
            'monthLabel' => QuranProgramSettings::monthLabel($month->format('Y-m')),
            'currency' => FinanceService::DEFAULT_CURRENCY,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'teacher_id' => ['required', 'uuid'],
            'rate' => ['required', 'numeric', 'gt:0'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
        ]);

        $teacher = Teacher::query()->findOrFail($data['teacher_id']);

        try {
            $this->rates->create($teacher, $data, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with(
            'success',
            'تمت إضافة سعر الساعة — حُوّل الأستاذ إلى الأجر بالساعة ويُحتسب راتبه من ساعات عمله'
        );
    }

    public function destroy(Request $request, HourlyRate $hourlyRate): RedirectResponse
    {
        $teacher = $hourlyRate->teacher;

        $this->audit->logModel('hourly_rate.deleted', $hourlyRate, actor: $request->user());
        $hourlyRate->delete();

        if ($teacher !== null) {
            $this->payroll->refreshOpenForTeacher($teacher);
        }

        return back()->with('success', 'تم حذف سعر الساعة (الكشوف المغلقة لا تتأثر)');
    }

    /** الرابط القديم `/admin/payroll/rates` → تبويب «أسعار الساعة» في الإعدادات. */
    public function legacyIndex(Request $request): RedirectResponse
    {
        return redirect()->route('admin.settings.hourly-rates.index', $request->query());
    }
}
