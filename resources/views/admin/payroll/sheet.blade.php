@extends('layouts.app')

@section('title', 'كشف راتب ' . $teacher->name)

@section('content')
@php
    $authorization = app(\App\Services\AuthorizationService::class);
    $can = fn (string $permission) => $authorization->can(auth()->user(), $permission);
    $closed = $summary['status'] === \App\Enums\PayrollStatus::Closed;
    $monthLabel = \App\Support\QuranProgramSettings::monthLabel($monthInput);
    $filters = ['teacher' => $teacher->id];
    $missingRate = $summary['missing_rates'] !== [];
@endphp

<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('admin.payroll.index', ['month' => $monthInput]) }}" class="text-sm text-emerald-700 hover:text-emerald-800">← دفعات المعلمين</a>
            <h2 class="text-2xl font-extrabold text-gray-800 mt-1">كشف {{ $teacher->name }}</h2>
            <div class="flex flex-wrap items-center gap-1 mt-2">
                @foreach($teacher->studySessions as $session)
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-teal-100 text-teal-800">{{ $session->display_name }}</span>
                @endforeach
                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $summary['state']->badgeClasses() }}">{{ $summary['state']->label() }}</span>
                <span @class([
                    'px-2 py-0.5 rounded-full text-[11px] font-bold',
                    'bg-emerald-100 text-emerald-800' => ! $closed,
                    'bg-gray-200 text-gray-600' => $closed,
                ])>{{ $closed ? 'الشهر مغلق' : 'الشهر مفتوح' }}</span>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-month-stepper :month-input="$monthInput" route-name="admin.payroll.sheet" :query="$filters" />
            <a href="{{ route('admin.payroll.sheet-print', ['teacher' => $teacher, 'month' => $monthInput]) }}" target="_blank"
               class="bg-white border border-gray-200 hover:border-emerald-300 text-gray-700 text-sm font-bold px-4 py-2 rounded-lg shadow-sm">طباعة / PDF</a>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
        <div class="flex flex-wrap items-center gap-x-8 gap-y-4">
            <div class="min-w-[10rem]">
                <div class="text-xs font-bold text-gray-400">المستحق — {{ $monthLabel }}</div>
                <div class="text-3xl font-black text-gray-800 mt-0.5" dir="ltr">
                    {{ number_format($summary['gross'], 2) }} <span class="text-sm font-bold text-gray-400">{{ $currency }}</span>
                </div>
                <div class="flex flex-wrap items-center gap-3 mt-2 text-xs">
                    <span class="font-bold text-emerald-700" dir="ltr">مدفوع: {{ number_format($summary['paid'], 2) }}</span>
                    <span @class(['font-bold', 'text-amber-600' => $summary['remaining'] > 0, 'text-gray-400' => $summary['remaining'] <= 0]) dir="ltr">
                        المتبقي: {{ number_format($summary['remaining'], 2) }}
                    </span>
                    <x-duration :minutes="$summary['total_minutes']" class="font-bold text-gray-500" />
                </div>
            </div>

            <div class="flex-1 min-w-[12rem]">
                <x-pay-progress :paid="$summary['paid']" :gross="$summary['gross']" :show-label="false" class="min-w-0" />
            </div>

            <div>
                <x-quick-pay
                    :teacher="$teacher"
                    :month-input="$monthInput"
                    :remaining="$summary['remaining']"
                    :gross="$summary['gross']"
                    :currency="$currency"
                    :closed="$closed"
                    :can-pay="$can('payroll.pay')"
                    inline />
            </div>
        </div>
    </div>

    @if($summary['missing_rates'] !== [])
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 flex flex-wrap items-center gap-2">
            <x-icon name="alert" class="w-5 h-5 shrink-0" />
            <span><span class="font-bold">لا يمكن الاحتساب كاملاً:</span> لا يوجد سعر ساعة في <span dir="ltr">{{ implode('، ', $summary['missing_rates']) }}</span></span>
            @if($can('hourly_rates.manage'))
                <a href="#rate" class="underline font-bold hover:no-underline">إضافة سعر</a>
            @endif
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
        <div class="lg:col-span-2 space-y-5">
            <div class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="font-black text-pine-950">ساعات الشهر — {{ $monthLabel }}</h3>
                    @if($can('work_hours.manage') && ! $closed)
                        <details class="relative">
                            <summary class="cursor-pointer list-none inline-flex items-center gap-1.5 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-3 py-2 rounded-lg select-none">
                                <x-icon name="plus" class="w-3.5 h-3.5" />
                                إضافة ساعات
                            </summary>
                            <div class="absolute end-0 z-20 mt-2 w-[34rem] max-w-[calc(100vw-2rem)] rounded-2xl border border-gray-200 bg-white p-4 shadow-xl">
                                <x-quick-slot :teacher="$teacher" :date="now()->toDateString()" />
                            </div>
                        </details>
                    @endif
                </div>

                <x-work-month-details
                    :blocks="$blocks"
                    :slots-by-date="$slotsByDate"
                    :editable="$can('work_hours.manage')"
                    :teacher="$teacher"
                    :payroll-closed="$closed" />
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="font-black text-pine-950">الدفعات</h3>
                    <span class="text-xs text-gray-500">مدفوع هذا الشهر: <span class="font-bold text-emerald-700" dir="ltr">{{ number_format($summary['paid'], 2) }}</span> {{ $currency }}</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-gray-600 text-xs">
                                <th scope="col" class="px-4 py-2 text-right">التاريخ</th>
                                <th scope="col" class="px-4 py-2 text-right">البيان</th>
                                <th scope="col" class="px-4 py-2 text-right">الطريقة</th>
                                <th scope="col" class="px-4 py-2 text-right">المبلغ</th>
                                <th scope="col" class="px-4 py-2 text-right">سجّلها</th>
                                <th scope="col" class="px-4 py-2 text-center">الحالة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                        @forelse($payments as $payment)
                            <tr>
                                <td class="px-4 py-2 whitespace-nowrap" dir="ltr">{{ $payment->created_at->format('Y-m-d') }}</td>
                                <td class="px-4 py-2">{{ $payment->description ?: '—' }}</td>
                                <td class="px-4 py-2">{{ $payment->payment_method ?: '—' }}</td>
                                <td class="px-4 py-2 font-bold text-emerald-700" dir="ltr">+{{ number_format((float) $payment->amount, 2) }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $payment->creator?->name ?? '—' }}</td>
                                <td class="px-4 py-2 text-center">
                                    @if($payment->reversal)
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-red-100 text-red-800">معكوسة</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">مُودعة</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">لا دفعات مرتبطة بهذا الكشف بعد</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="space-y-5 lg:sticky lg:top-6">
            <div id="rate" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 space-y-3 scroll-mt-6">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="font-black text-pine-950">سعر الساعة</h3>
                    @if($currentRate !== null)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $currentRate->statusBadgeClasses() }}">{{ $currentRate->statusLabel() }}</span>
                    @endif
                </div>

                @if($currentRate !== null)
                    <div class="rounded-xl bg-emerald-50/70 border border-emerald-100 px-4 py-3">
                        <div class="flex items-end justify-between gap-2">
                            <span class="text-2xl font-black text-emerald-800" dir="ltr">{{ number_format((float) $currentRate->rate, 2) }}</span>
                            <span class="text-[11px] font-bold text-emerald-700">{{ $currency }} / ساعة</span>
                        </div>
                        <div class="text-[11px] text-emerald-700/80 mt-1">
                            ساري من <span dir="ltr">{{ $currentRate->effective_from->format('Y-m-d') }}</span>
                            — المستحق = ساعات الشهر × هذا السعر
                        </div>
                    </div>
                @else
                    <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
                        <span class="font-bold">لا يوجد سعر ساعة ساري اليوم</span> — أضف سعراً ليُحتسب المستحق من ساعات العمل.
                    </div>
                @endif

                @if($can('hourly_rates.manage'))
                    <form method="POST" action="{{ route('admin.payroll.rate', $teacher) }}" class="space-y-3 border-t border-gray-100 pt-3">
                        @csrf
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1">
                                {{ $currentRate !== null ? 'تعديل سعر الساعة' : 'إضافة سعر الساعة' }}
                            </label>
                            <div class="flex items-center gap-2">
                                <input type="number" step="0.01" min="0.01" name="rate" required
                                       value="{{ old('rate', $currentRate !== null ? number_format((float) $currentRate->rate, 2, '.', '') : '') }}"
                                       placeholder="سعر الساعة"
                                       class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm font-bold" dir="ltr">
                                <span class="text-[11px] font-bold text-gray-400">{{ $currency }}/س</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1">يسري من تاريخ</label>
                            <input type="date" name="effective_from" required value="{{ old('effective_from', now()->toDateString()) }}"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" dir="ltr">
                            <p class="text-[10px] text-gray-400 mt-1">يُغلق السعر السابق تلقائياً قبل هذا التاريخ.</p>
                        </div>
                        <button class="w-full bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">
                            {{ $currentRate !== null ? 'حفظ السعر الجديد' : 'إضافة السعر' }}
                        </button>
                    </form>
                @endif

                @if($rates->isNotEmpty())
                    <details class="border-t border-gray-100 pt-3">
                        <summary class="cursor-pointer text-[11px] font-bold text-gray-500 select-none">سجل الأسعار ({{ $rates->count() }})</summary>
                        <div class="mt-2 space-y-1.5">
                            @foreach($rates as $rate)
                                <div class="flex items-center justify-between gap-2 text-xs border-b border-gray-50 pb-1.5 last:border-0">
                                    <span class="font-bold text-gray-700" dir="ltr">{{ number_format((float) $rate->rate, 2) }}</span>
                                    <span class="text-gray-400" dir="ltr">{{ $rate->effective_from->format('Y-m-d') }} → {{ $rate->effective_to?->format('Y-m-d') ?? 'مفتوح' }}</span>
                                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold {{ $rate->statusBadgeClasses() }}">{{ $rate->statusLabel() }}</span>
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endif

                <p class="text-[11px] text-gray-400">
                    الإدارة الكاملة من
                    <a href="{{ route('admin.settings.hourly-rates.index', ['q' => $teacher->name, 'teacher_id' => $teacher->id]) }}" class="text-emerald-700 underline">إعدادات أسعار الساعة</a>.
                </p>
            </div>

            <details class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <summary class="cursor-pointer px-5 py-3 font-black text-pine-950 select-none flex items-center justify-between">
                    <span>خيارات متقدمة</span>
                    <x-icon name="chevron" class="w-4 h-4 text-gray-400" />
                </summary>
                <div class="px-5 pb-5 space-y-4 border-t border-gray-100 pt-4">
                    @if($can('work_hours.manage') && ! $closed)
                        <form method="POST" action="{{ route('admin.teachers.timesheet.generate', $teacher) }}" class="space-y-2">
                            @csrf
                            <label class="block text-[11px] font-bold text-gray-500">توليد الفترات من الجدول الأسبوعي ليوم</label>
                            <div class="flex items-center gap-2">
                                <input type="date" name="date" value="{{ now()->toDateString() }}" required
                                       class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm" dir="ltr">
                                <button class="bg-gray-800 hover:bg-gray-900 text-white text-xs font-bold px-4 py-2 rounded-lg">توليد</button>
                            </div>
                        </form>
                    @endif

                    <a href="{{ route('admin.teachers.work-hours.index', $teacher) }}" class="block text-xs font-bold text-emerald-700 hover:underline">الجدول الأسبوعي (المخطط) ←</a>

                    @if($closed)
                        @if($can('payroll.reopen'))
                            <form method="POST" action="{{ route('admin.payroll.reopen', $teacher) }}" class="space-y-2"
                                  onsubmit="return confirm('إعادة فتح الكشف تسمح بتعديل الفترات. متابعة؟')">
                                @csrf
                                <input type="hidden" name="month" value="{{ $monthInput }}">
                                <input type="text" name="reason" required maxlength="500" placeholder="سبب إعادة الفتح (إلزامي)"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                <button class="w-full bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold px-4 py-2 rounded-lg">إعادة فتح الشهر</button>
                            </form>
                        @endif
                    @elseif($can('payroll.close'))
                        <form method="POST" action="{{ route('admin.payroll.close', $teacher) }}"
                              onsubmit="return confirm('إغلاق كشف هذا الشهر؟ لن يمكن تعديل فتراته بعد ذلك.')">
                            @csrf
                            <input type="hidden" name="month" value="{{ $monthInput }}">
                            <button class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-bold px-4 py-2 rounded-lg">قفل كشف هذا المعلم</button>
                        </form>
                    @endif

                    @if(count($summary['breakdown']) > 1)
                        <div>
                            <div class="text-[11px] font-bold text-gray-500 mb-2">تفصيل التسعير (تغيّر السعر داخل الشهر)</div>
                            <div class="space-y-1.5">
                                @foreach($summary['breakdown'] as $segment)
                                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs border-b border-gray-50 pb-1.5 last:border-0">
                                        <span class="font-bold" dir="ltr">{{ number_format($segment['rate'], 2) }} × {{ \App\Models\WorkSlot::formatMinutes($segment['minutes']) }}</span>
                                        <span class="text-gray-400" dir="ltr">{{ $segment['from'] }} → {{ $segment['to'] }}</span>
                                        <span class="font-bold text-gray-700" dir="ltr">{{ number_format($segment['minutes'] / 60 * $segment['rate'], 2) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </details>

            @if($auditLogs->isNotEmpty())
                <details class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <summary class="cursor-pointer px-5 py-3 font-black text-pine-950 select-none flex items-center justify-between">
                        <span>سجل العمليات على الكشف</span>
                        <span class="text-xs font-bold text-gray-400">{{ $auditLogs->count() }}</span>
                    </summary>
                    <div class="px-5 pb-4 space-y-2">
                        @foreach($auditLogs as $log)
                            <div class="flex flex-wrap items-center justify-between gap-2 text-xs border-b border-gray-50 pb-2 last:border-0">
                                <span class="font-bold text-gray-700">{{ $log->action }}</span>
                                <span class="text-gray-400">{{ $log->user?->name ?? '—' }} — {{ $log->created_at->format('Y-m-d H:i') }}</span>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endif
        </div>
    </div>
</div>
@endsection
