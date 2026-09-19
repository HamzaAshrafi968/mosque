@extends('layouts.app')

@section('title', 'كشف راتب ' . $period->monthLabel())

@section('content')
@php
    $remaining = round((float) $period->gross_amount - $paid, 2);
    $state = $paid <= 0
        ? \App\Enums\PaymentState::Unpaid
        : (($period->gross_amount > 0 && $paid + 0.001 < (float) $period->gross_amount)
            ? \App\Enums\PaymentState::Partial
            : \App\Enums\PaymentState::Paid);
@endphp

<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('teacher.payroll.index') }}" class="text-sm text-emerald-700 hover:text-emerald-800">← رواتبي</a>
            <h2 class="text-2xl font-extrabold text-gray-800 mt-1">كشف {{ $period->monthLabel() }}</h2>
            <div class="flex flex-wrap items-center gap-1 mt-2">
                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-gray-100 text-gray-600">{{ $period->pay_type_snapshot->label() }}</span>
                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $state->badgeClasses() }}">{{ $state->label() }}</span>
                @if($period->isClosed())
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-gray-200 text-gray-600">مغلق</span>
                @endif
            </div>
        </div>
        <a href="{{ route('teacher.timesheet.index', ['month' => $period->monthKey()]) }}"
           class="bg-white border border-gray-200 hover:border-emerald-300 text-gray-700 text-sm font-bold px-4 py-2 rounded-lg shadow-sm">كشف العمل</a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <x-stat-card icon="clock" tone="success" label="الساعات"
                     :value="\App\Models\WorkSlot::formatMinutes((int) $period->total_minutes)" />
        <x-stat-card icon="wallet" label="الإجمالي"
                     :value="number_format((float) $period->gross_amount, 2)" value-dir="ltr" />
        <x-stat-card icon="check" tone="success" label="المدفوع"
                     :value="number_format($paid, 2)" value-dir="ltr" />
        <x-stat-card icon="alert" :tone="$remaining > 0 ? 'warning' : 'muted'" label="المتبقي"
                     :value="number_format($remaining, 2)" value-dir="ltr" />
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4">
        <x-pay-progress :paid="$paid" :gross="$period->gross_amount" :show-label="false" class="min-w-0" />
    </div>

    @if($period->rate_breakdown)
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <h3 class="font-black text-pine-950 mb-3">تفصيل التسعير</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600 text-xs">
                            <th scope="col" class="px-4 py-2 text-right">السعر</th>
                            <th scope="col" class="px-4 py-2 text-right">الدقائق</th>
                            <th scope="col" class="px-4 py-2 text-right">من</th>
                            <th scope="col" class="px-4 py-2 text-right">إلى</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    @foreach($period->rate_breakdown as $segment)
                        <tr>
                            <td class="px-4 py-2 font-bold" dir="ltr">{{ number_format($segment['rate'], 2) }}</td>
                            <td class="px-4 py-2">{{ \App\Models\WorkSlot::formatMinutes($segment['minutes']) }}</td>
                            <td class="px-4 py-2" dir="ltr">{{ $segment['from'] }}</td>
                            <td class="px-4 py-2" dir="ltr">{{ $segment['to'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
        <h3 class="font-black text-pine-950 mb-3">الدفعات</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-xs">
                        <th scope="col" class="px-4 py-2 text-right">التاريخ</th>
                        <th scope="col" class="px-4 py-2 text-right">البيان</th>
                        <th scope="col" class="px-4 py-2 text-right">المبلغ</th>
                        <th scope="col" class="px-4 py-2 text-center">الحالة</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse($payments as $payment)
                    <tr>
                        <td class="px-4 py-2 whitespace-nowrap" dir="ltr">{{ $payment->created_at->format('Y-m-d') }}</td>
                        <td class="px-4 py-2">{{ $payment->description ?: '—' }}</td>
                        <td class="px-4 py-2 font-bold text-emerald-700" dir="ltr">+{{ number_format((float) $payment->amount, 2) }}</td>
                        <td class="px-4 py-2 text-center">
                            @if($payment->reversal)
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-red-100 text-red-800">معكوسة</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">مُودعة</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">لا دفعات بعد</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
