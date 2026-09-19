@extends('layouts.app')

@section('title', 'رواتبي')

@section('content')
@php
    $authorization = app(\App\Services\AuthorizationService::class);
    $can = fn (string $permission) => $authorization->can(auth()->user(), $permission);
    $canPayroll = $can('payroll.view');
    $canHours = $can('work_hours.view');
    $closed = $summary['status'] === \App\Enums\PayrollStatus::Closed;
    $monthLabel = \App\Support\QuranProgramSettings::monthLabel($monthInput);
@endphp

<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-800">رواتبي</h2>
            <p class="text-sm text-gray-500 mt-1">ساعاتك ومستحقاتك ودفعاتك في مكان واحد — عرض فقط.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-month-stepper :month-input="$monthInput" route-name="teacher.payroll.index" />
            @if($canHours)
                <a href="{{ route('teacher.work-hours.index') }}"
                   class="bg-white border border-gray-200 hover:border-emerald-300 text-gray-700 text-sm font-bold px-4 py-2 rounded-lg shadow-sm">جدولي الأسبوعي</a>
            @endif
            <a href="{{ route('teacher.finance.index') }}"
               class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">دفعاتي ←</a>
        </div>
    </div>

    @if($canPayroll)
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <div class="flex flex-wrap items-center gap-x-8 gap-y-4">
                <div class="min-w-[10rem]">
                    <div class="text-xs font-bold text-gray-400">مستحقك — {{ $monthLabel }}</div>
                    <div class="text-3xl font-black text-gray-800 mt-0.5" dir="ltr">
                        {{ number_format($summary['gross'], 2) }} <span class="text-sm font-bold text-gray-400">{{ $currency }}</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 mt-2 text-xs">
                        <span class="font-bold text-emerald-700" dir="ltr">مدفوع: {{ number_format($summary['paid'], 2) }}</span>
                        <span @class(['font-bold', 'text-amber-600' => $summary['remaining'] > 0, 'text-gray-400' => $summary['remaining'] <= 0]) dir="ltr">
                            المتبقي: {{ number_format($summary['remaining'], 2) }}
                        </span>
                        @if($closed)
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-200 text-gray-600">الشهر مغلق</span>
                        @endif
                    </div>
                </div>
                <div class="flex-1 min-w-[12rem]">
                    <x-pay-progress :paid="$summary['paid']" :gross="$summary['gross']" :show-label="false" class="min-w-0" />
                </div>
            </div>
        </div>

        @if($summary['missing_rates'] !== [])
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 flex items-center gap-2">
                <x-icon name="alert" class="w-5 h-5 shrink-0" />
                <span><span class="font-bold">تنبيه:</span> لا يوجد سعر ساعة مسجَّل في بعض الأيام — راجع مدير الجامع.</span>
            </div>
        @endif
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
        <div @class(['space-y-3', 'lg:col-span-2' => $canPayroll])>
            <h3 class="font-black text-pine-950">ساعاتي في {{ $monthLabel }}</h3>

            @if($slotsByDate->isEmpty())
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-10 text-center">
                    <span class="inline-flex w-14 h-14 rounded-2xl bg-gold-50 text-gold-500 grid place-items-center mb-3">
                        <x-icon name="clock" class="w-7 h-7" />
                    </span>
                    <p class="text-gray-500 font-bold">لا توجد فترات عمل في {{ $monthLabel }}</p>
                    <p class="text-gray-400 text-xs mt-1">تُسجَّل فترات العمل بواسطة مدير الجامع.</p>
                </div>
            @else
                <x-work-month-details :blocks="$blocks" :slots-by-date="$slotsByDate" />
            @endif
        </div>

        @if($canPayroll)
            <div class="space-y-5">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="font-black text-pine-950">الدفعات الواردة</h3>
                        <a href="{{ route('teacher.finance.index') }}" class="text-xs font-bold text-emerald-700 hover:underline">الكل ←</a>
                    </div>

                    @forelse($deposits as $deposit)
                        <div class="flex items-start justify-between gap-2 text-sm border-b border-gray-50 pb-2 last:border-0 last:pb-0">
                            <div class="min-w-0">
                                <div class="font-bold text-gray-800" dir="ltr">+{{ number_format((float) $deposit->amount, 2) }} {{ $currency }}</div>
                                <div class="text-[11px] text-gray-400 mt-0.5">
                                    <span dir="ltr">{{ $deposit->created_at->format('Y-m-d') }}</span>
                                    @if($deposit->description)
                                        — {{ $deposit->description }}
                                    @endif
                                </div>
                            </div>
                            @if($deposit->reversal)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-800 shrink-0">معكوسة</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 shrink-0">مُودعة</span>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-400 text-center py-4">لا دفعات واردة بعد</p>
                    @endforelse
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <h3 class="font-black text-pine-950 px-5 pt-4 pb-2">كشوفي الشهرية</h3>

                    @if($periods->isEmpty())
                        <p class="text-sm text-gray-400 text-center py-8 px-5">لا توجد كشوف رواتب بعد — تُنشئها إدارة الجامع.</p>
                    @else
                        <div class="divide-y divide-gray-100">
                            @foreach($periods as $period)
                                @php
                                    $paidAmount = $paid[$period->id] ?? 0;
                                    $remaining = round((float) $period->gross_amount - $paidAmount, 2);
                                    $state = $paidAmount <= 0
                                        ? \App\Enums\PaymentState::Unpaid
                                        : (($period->gross_amount > 0 && $paidAmount + 0.001 < (float) $period->gross_amount)
                                            ? \App\Enums\PaymentState::Partial
                                            : \App\Enums\PaymentState::Paid);
                                @endphp
                                <a href="{{ route('teacher.payroll.show', $period) }}"
                                   class="block px-5 py-3 hover:bg-gray-50/60 transition-colors">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-bold text-gray-800">{{ $period->monthLabel() }}</span>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $state->badgeClasses() }}">{{ $state->label() }}</span>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-gray-500 mt-1">
                                        <span>{{ \App\Models\WorkSlot::formatMinutes((int) $period->total_minutes) }}</span>
                                        <span>الإجمالي: <span class="font-bold text-gray-700" dir="ltr">{{ number_format((float) $period->gross_amount, 2) }}</span></span>
                                        @if($remaining > 0)
                                            <span>المتبقي: <span class="font-bold text-amber-600" dir="ltr">{{ number_format($remaining, 2) }}</span></span>
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                        <div class="p-4 border-t border-gray-100">{{ $periods->links() }}</div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
