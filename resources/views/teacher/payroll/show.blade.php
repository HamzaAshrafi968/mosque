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
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('teacher.payroll.index') }}" class="text-sm text-emerald-700 hover:text-emerald-800">← كشوف رواتبي</a>
            <h2 class="text-2xl font-extrabold text-gray-800 mt-1">كشف {{ $period->monthLabel() }}</h2>
            <p class="text-sm text-gray-500 mt-1">
                {{ $period->pay_type_snapshot->label() }}
                @if($period->isClosed())
                    <span class="ms-2 px-2 py-0.5 rounded-full text-[11px] font-bold bg-gray-200 text-gray-600">مغلق</span>
                @endif
            </p>
        </div>
        <a href="{{ route('teacher.timesheet.index', ['month' => $period->monthKey()]) }}" class="text-sm text-gray-500 hover:underline">كشف العمل</a>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-x-reverse divide-gray-100 bg-white rounded-2xl shadow-sm border border-gray-200 text-center overflow-hidden">
        <div class="p-4">
            <div class="text-lg font-black text-emerald-700">{{ \App\Models\WorkSlot::formatMinutes((int) $period->total_minutes) }}</div>
            <div class="text-[11px] text-gray-500 mt-0.5">الساعات</div>
        </div>
        <div class="p-4">
            <div class="text-lg font-black text-gray-800" dir="ltr">{{ number_format((float) $period->gross_amount, 2) }}</div>
            <div class="text-[11px] text-gray-500 mt-0.5">الإجمالي</div>
        </div>
        <div class="p-4">
            <div class="text-lg font-black text-emerald-700" dir="ltr">{{ number_format($paid, 2) }}</div>
            <div class="text-[11px] text-gray-500 mt-0.5">المدفوع</div>
        </div>
        <div class="p-4">
            <div @class(['text-lg font-black', 'text-amber-600' => $remaining > 0, 'text-gray-300' => $remaining <= 0]) dir="ltr">{{ number_format($remaining, 2) }}</div>
            <div class="text-[11px] text-gray-500 mt-0.5">المتبقي</div>
        </div>
    </div>

    @if($period->rate_breakdown)
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <h3 class="font-black text-pine-950 mb-3">تفصيل التسعير</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600">
                            <th scope="col" class="px-4 py-2 text-right">السعر</th>
                            <th scope="col" class="px-4 py-2 text-right">الدقائق</th>
                            <th scope="col" class="px-4 py-2 text-right">من</th>
                            <th scope="col" class="px-4 py-2 text-right">إلى</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($period->rate_breakdown as $segment)
                        <tr class="border-t">
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
                    <tr class="bg-gray-50 text-gray-600">
                        <th scope="col" class="px-4 py-2 text-right">التاريخ</th>
                        <th scope="col" class="px-4 py-2 text-right">البيان</th>
                        <th scope="col" class="px-4 py-2 text-right">المبلغ</th>
                        <th scope="col" class="px-4 py-2 text-center">الحالة</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($payments as $payment)
                    <tr class="border-t">
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
