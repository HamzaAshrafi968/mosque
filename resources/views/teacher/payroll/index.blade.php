@extends('layouts.app')

@section('title', 'كشوف رواتبي')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-800">كشوف رواتبي</h2>
            <p class="text-sm text-gray-500 mt-1">كشوفك الشهرية المعتمدة من إدارة الجامع — عرض فقط.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('teacher.timesheet.index') }}" class="text-sm text-gray-500 hover:underline">كشوفي</a>
            <a href="{{ route('teacher.finance.index') }}" class="text-sm font-bold text-emerald-700 hover:underline">دفعاتي ←</a>
        </div>
    </div>

    @if($periods->isEmpty())
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-10 text-center">
            <span class="inline-flex w-14 h-14 rounded-2xl bg-gold-50 text-gold-500 grid place-items-center mb-3"><x-icon name="wallet" class="w-7 h-7" /></span>
            <p class="text-gray-500 font-bold">لا توجد كشوف رواتب بعد</p>
            <p class="text-gray-400 text-xs mt-1">ستظهر هنا الكشوف التي تنشئها إدارة الجامع.</p>
        </div>
    @else
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <caption class="sr-only">كشوف رواتبي الشهرية</caption>
                    <thead>
                        <tr class="bg-gray-50 text-gray-600">
                            <th scope="col" class="px-4 py-3 text-right">الشهر</th>
                            <th scope="col" class="px-4 py-3 text-right">الساعات</th>
                            <th scope="col" class="px-4 py-3 text-right">الإجمالي</th>
                            <th scope="col" class="px-4 py-3 text-right">المدفوع</th>
                            <th scope="col" class="px-4 py-3 text-right">المتبقي</th>
                            <th scope="col" class="px-4 py-3 text-center">الحالة</th>
                            <th scope="col" class="px-4 py-3 text-center">إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
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
                        <tr class="border-t">
                            <td class="px-4 py-3 whitespace-nowrap font-bold text-gray-800">{{ $period->monthLabel() }}</td>
                            <td class="px-4 py-3">
                                <x-duration :minutes="$period->total_minutes" class="font-bold" />
                            </td>
                            <td class="px-4 py-3 font-bold text-gray-800" dir="ltr">{{ number_format((float) $period->gross_amount, 2) }}</td>
                            <td class="px-4 py-3 text-emerald-700" dir="ltr">{{ number_format($paidAmount, 2) }}</td>
                            <td class="px-4 py-3 font-bold" dir="ltr">
                                <span @class(['text-amber-600' => $remaining > 0, 'text-gray-300' => $remaining <= 0])>{{ number_format($remaining, 2) }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $state->badgeClasses() }}">{{ $state->label() }}</span>
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <a href="{{ route('teacher.payroll.show', $period) }}" class="text-emerald-700 hover:underline text-xs font-bold">التفاصيل</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-gray-100">{{ $periods->links() }}</div>
        </div>
    @endif
</div>
@endsection
