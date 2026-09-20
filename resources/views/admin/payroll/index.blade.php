@extends('layouts.app')

@section('title', 'دفعات المعلمين')

@section('content')
@php
    $authorization = app(\App\Services\AuthorizationService::class);
    $can = fn (string $permission) => $authorization->can(auth()->user(), $permission);
    $monthLabel = \App\Support\QuranProgramSettings::monthLabel($monthInput);
    $filters = array_filter(['q' => $search !== '' ? $search : null, 'session' => $sessionId]);
@endphp

<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-800">دفعات المعلمين</h2>
            <p class="text-sm text-gray-500 mt-1">الراتب بالساعات: ساعات كل معلم × سعر الساعة — سجّل الساعات، وادفع بنقرة.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if($can('payroll.close'))
                <form method="POST" action="{{ route('admin.payroll.close-all') }}"
                      onsubmit="return confirm('إغلاق كل كشوف {{ $monthLabel }}؟ لن يمكن تعديل فترات الشهر بعد ذلك.')">
                    @csrf
                    <input type="hidden" name="month" value="{{ $monthInput }}">
                    <input type="hidden" name="confirm_month" value="{{ $monthInput }}">
                    <button class="bg-gray-800 hover:bg-gray-900 text-white text-sm font-bold px-4 py-2 rounded-lg">قفل الشهر</button>
                </form>
            @endif
            @if($can('payroll.view') || $can('finance.view'))
                <details class="relative">
                    <summary class="cursor-pointer list-none bg-white border border-gray-200 hover:border-emerald-300 text-gray-700 text-sm font-bold px-4 py-2 rounded-lg shadow-sm select-none">
                        تصدير / طباعة
                    </summary>
                    <div class="absolute end-0 z-20 mt-2 w-44 rounded-xl border border-gray-200 bg-white p-1.5 shadow-xl">
                        <a href="{{ route('admin.payroll.export', ['month' => $monthInput]) }}" class="block px-3 py-2 rounded-lg text-sm font-bold text-gray-600 hover:bg-gray-50">CSV</a>
                        <a href="{{ route('admin.payroll.export-excel', ['month' => $monthInput]) }}" class="block px-3 py-2 rounded-lg text-sm font-bold text-gray-600 hover:bg-gray-50">Excel</a>
                        <a href="{{ route('admin.payroll.print', ['month' => $monthInput]) }}" target="_blank" class="block px-3 py-2 rounded-lg text-sm font-bold text-gray-600 hover:bg-gray-50">طباعة الكل</a>
                    </div>
                </details>
            @endif
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <x-month-stepper :month-input="$monthInput" route-name="admin.payroll.index" :query="$filters" />

        @if($can('work_hours.manage'))
            <details class="relative">
                <summary class="cursor-pointer list-none inline-flex items-center gap-1.5 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2.5 rounded-xl shadow-sm select-none">
                    <x-icon name="plus" class="w-4 h-4" />
                    تسجيل ساعات
                </summary>
                <div class="absolute end-0 z-20 mt-2 w-[34rem] max-w-[calc(100vw-2rem)] rounded-2xl border border-gray-200 bg-white p-4 shadow-xl">
                    <x-quick-slot :teachers="$teachers->getCollection()" :date="now()->toDateString()" />
                </div>
            </details>
        @endif
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <x-stat-card icon="clock" tone="success" label="ساعات {{ $monthLabel }}"
                     :value="\App\Models\WorkSlot::formatMinutes($totals['minutes'])" />
        <x-stat-card icon="wallet" label="إجمالي المستحقات ({{ $currency }})"
                     :value="number_format($totals['gross'], 2)" value-dir="ltr"
                     :hint="'المدفوع: '.number_format($totals['paid'], 2)" />
        <x-stat-card icon="alert" :tone="$totals['remaining'] > 0 ? 'warning' : 'muted'" label="المتبقي للدفع"
                     :value="number_format($totals['remaining'], 2)" value-dir="ltr"
                     :hint="$totals['closed'] > 0 ? $totals['closed'].' كشف مغلق' : null" />
    </div>

    <form method="GET" action="{{ route('admin.payroll.index') }}"
          class="bg-white rounded-2xl shadow-sm border border-gray-200 p-3 flex flex-wrap items-end gap-3">
        <input type="hidden" name="month" value="{{ $monthInput }}">
        <div class="flex-1 min-w-[12rem]">
            <label class="block text-xs font-bold text-gray-600 mb-1">بحث بالاسم</label>
            <input type="text" name="q" value="{{ $search }}" placeholder="اسم المعلم"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">الدوام</label>
            <select name="session" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">كل الدوامات</option>
                @foreach($sessions as $session)
                    <option value="{{ $session->id }}" @selected((string) $sessionId === (string) $session->id)>{{ $session->display_name }}</option>
                @endforeach
            </select>
        </div>
        <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-5 py-2 rounded-lg">عرض</button>
    </form>

    @if($teachers->isEmpty())
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-10 text-center">
            <span class="inline-flex w-14 h-14 rounded-2xl bg-gold-50 text-gold-500 grid place-items-center mb-3">
                <x-icon name="teachers" class="w-7 h-7" />
            </span>
            <p class="text-gray-500 font-bold">لا يوجد معلمون مطابقون</p>
            <p class="text-gray-400 text-xs mt-1">جرّب تغيير الشهر أو البحث.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($teachers as $teacher)
                @php $summary = $summaries[$teacher->id] ?? null; @endphp
                @if($summary)
                    @php
                        $closed = $summary['status'] === \App\Enums\PayrollStatus::Closed;
                        $hasMissingRate = $summary['missing_rates'] !== [];
                    @endphp
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4">
                        <div class="flex flex-wrap items-center gap-x-5 gap-y-3">
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <span class="grid place-items-center w-11 h-11 rounded-2xl bg-pine-50 text-pine-800 font-black shrink-0">
                                    {{ mb_substr($teacher->name, 0, 1) }}
                                </span>
                                <div class="min-w-0">
                                    <a href="{{ route('admin.payroll.sheet', ['teacher' => $teacher, 'month' => $monthInput]) }}"
                                       class="font-black text-gray-800 hover:text-emerald-700 truncate">{{ $teacher->name }}</a>
                                    <div class="flex flex-wrap items-center gap-1 mt-1">
                                        @foreach($teacher->studySessions as $session)
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-teal-100 text-teal-800">{{ $session->display_name }}</span>
                                        @endforeach
                                        @if($closed)
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-200 text-gray-600">مغلق</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $summary['state']->badgeClasses() }}">{{ $summary['state']->label() }}</span>
                                        @endif
                                        @if($hasMissingRate)
                                            @if($can('hourly_rates.manage'))
                                                <a href="{{ route('admin.payroll.sheet', ['teacher' => $teacher, 'month' => $monthInput]) }}#rate"
                                                   class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 hover:bg-amber-200">⚠ لا يوجد سعر — أضف سعراً</a>
                                            @else
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">⚠ لا يوجد سعر</span>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="text-center min-w-[5.5rem]">
                                <div class="text-[11px] font-bold text-gray-400">الساعات</div>
                                <x-duration :minutes="$summary['total_minutes']" class="text-lg font-black text-emerald-700" />
                                @if($summary['planned_minutes'] > 0)
                                    <div class="text-[10px] text-gray-400">المخطط {{ \App\Models\WorkSlot::formatMinutes($summary['planned_minutes']) }}</div>
                                @endif
                            </div>

                            <div class="text-center min-w-[6rem]">
                                <div class="text-[11px] font-bold text-gray-400">سعر الساعة</div>
                                @if($summary['hourly_rate'] !== null)
                                    <div class="font-black text-gray-700" dir="ltr">{{ number_format($summary['hourly_rate'], 2) }}</div>
                                @elseif($summary['rate_is_mixed'])
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-100 text-sky-800">متغيّر داخل الشهر</span>
                                @elseif($summary['current_rate'] !== null)
                                    <div class="font-black text-gray-700" dir="ltr">{{ number_format($summary['current_rate'], 2) }}</div>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-400">غير محدد</span>
                                @endif
                            </div>

                            <div class="text-center min-w-[7rem]">
                                <div class="text-[11px] font-bold text-gray-400">المستحق</div>
                                <div class="font-black text-gray-800" dir="ltr">{{ number_format($summary['gross'], 2) }}</div>
                                <div class="text-[10px] text-gray-400" dir="ltr">مدفوع {{ number_format($summary['paid'], 2) }}</div>
                            </div>

                            <div class="min-w-[9rem]">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-[11px] font-bold text-gray-400">المتبقي</span>
                                    <span @class([
                                        'font-black text-sm',
                                        'text-amber-600' => $summary['remaining'] > 0,
                                        'text-emerald-700' => $summary['remaining'] <= 0,
                                    ]) dir="ltr">{{ number_format($summary['remaining'], 2) }}</span>
                                </div>
                                <x-pay-progress :paid="$summary['paid']" :gross="$summary['gross']" :show-label="false" class="mt-1.5" />
                            </div>

                            <div class="flex items-center gap-2 ms-auto">
                                <x-quick-pay
                                    :teacher="$teacher"
                                    :month-input="$monthInput"
                                    :remaining="$summary['remaining']"
                                    :gross="$summary['gross']"
                                    :currency="$currency"
                                    :closed="$closed"
                                    :can-pay="$can('payroll.pay')" />
                                <a href="{{ route('admin.payroll.sheet', ['teacher' => $teacher, 'month' => $monthInput]) }}"
                                   class="inline-flex items-center gap-1 text-emerald-700 hover:text-emerald-800 text-xs font-bold whitespace-nowrap">
                                    التفاصيل
                                    <x-icon name="chevrons-left" class="w-3.5 h-3.5" />
                                </a>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-3">{{ $teachers->links() }}</div>
    @endif
</div>
@endsection
