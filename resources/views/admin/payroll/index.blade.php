@extends('layouts.app')

@section('title', 'رواتب المعلمين')

@section('content')
@php
    $authorization = app(\App\Services\AuthorizationService::class);
    $can = fn (string $permission) => $authorization->can(auth()->user(), $permission);
@endphp

<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-800">رواتب المعلمين</h2>
            <p class="text-sm text-gray-500 mt-1">
                كشوف شهرية من فترات العمل الفعلية — الاحتساب بالساعة حسب سعر كل تاريخ، والدفعات تُسجَّل في السجل المالي.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-3 text-sm">
            <a href="{{ route('admin.timesheet.index', ['view' => 'monthly', 'month' => $monthInput]) }}" class="font-bold text-emerald-700 hover:underline">كشوف العمل ←</a>
            @if($can('hourly_rates.manage'))
                <a href="{{ route('admin.payroll.rates.index') }}" class="text-gray-500 hover:underline">أسعار الساعة</a>
            @endif
            @if($can('payroll.close'))
                <a href="{{ route('admin.payroll.close-preview', ['month' => $monthInput]) }}" class="text-gray-500 hover:underline">إغلاق الشهر</a>
            @endif
            <a href="{{ route('admin.payroll.export', ['month' => $monthInput]) }}" class="text-gray-500 hover:underline">تصدير CSV</a>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.payroll.index') }}" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        <div class="md:col-span-2">
            <label class="block text-xs font-bold text-gray-600 mb-1">بحث بالاسم</label>
            <input type="text" name="q" value="{{ $search }}" placeholder="اسم المعلم" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">الشهر</label>
            <input type="month" name="month" value="{{ $monthInput }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">عرض</button>
    </form>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 divide-x divide-x-reverse divide-gray-100 bg-white rounded-2xl shadow-sm border border-gray-200 text-center overflow-hidden">
        <div class="p-4">
            <div class="text-lg font-black text-emerald-700">{{ \App\Models\WorkSlot::formatMinutes($totals['minutes']) }}</div>
            <div class="text-[11px] text-gray-500 mt-0.5">ساعات {{ \App\Support\QuranProgramSettings::monthLabel($monthInput) }}</div>
        </div>
        <div class="p-4">
            <div class="text-lg font-black text-gray-800" dir="ltr">{{ number_format($totals['gross'], 2) }}</div>
            <div class="text-[11px] text-gray-500 mt-0.5">إجمالي الرواتب ({{ $currency }})</div>
        </div>
        <div class="p-4">
            <div class="text-lg font-black text-emerald-700" dir="ltr">{{ number_format($totals['paid'], 2) }}</div>
            <div class="text-[11px] text-gray-500 mt-0.5">المدفوع</div>
        </div>
        <div class="p-4">
            <div @class(['text-lg font-black', 'text-amber-600' => $totals['remaining'] > 0, 'text-gray-300' => $totals['remaining'] <= 0]) dir="ltr">
                {{ number_format($totals['remaining'], 2) }}
            </div>
            <div class="text-[11px] text-gray-500 mt-0.5">المتبقي</div>
        </div>
        <div class="p-4">
            <div class="text-lg font-black text-gray-500">{{ $totals['closed'] }}</div>
            <div class="text-[11px] text-gray-500 mt-0.5">كشوف مغلقة</div>
        </div>
    </div>

    @if($teachers->isEmpty())
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-10 text-center text-gray-400 font-bold">لا يوجد معلمون مطابقون</div>
    @else
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <caption class="sr-only">رواتب المعلمين لشهر {{ $monthInput }}</caption>
                    <thead>
                        <tr class="bg-gray-50 text-gray-600">
                            <th scope="col" class="px-4 py-3 text-right">المعلم</th>
                            <th scope="col" class="px-4 py-3 text-right">النوع</th>
                            <th scope="col" class="px-4 py-3 text-right">الفعلي</th>
                            <th scope="col" class="px-4 py-3 text-right">المخطط</th>
                            <th scope="col" class="px-4 py-3 text-right">السعر / الراتب</th>
                            <th scope="col" class="px-4 py-3 text-right">الإجمالي</th>
                            <th scope="col" class="px-4 py-3 text-right">المدفوع</th>
                            <th scope="col" class="px-4 py-3 text-right">المتبقي</th>
                            <th scope="col" class="px-4 py-3 text-center">الحالة</th>
                            <th scope="col" class="px-4 py-3 text-center">إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($teachers as $teacher)
                        @php $summary = $summaries[$teacher->id] ?? null; @endphp
                        @if($summary)
                            <tr class="border-t">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <a href="{{ route('admin.payroll.sheet', ['teacher' => $teacher, 'month' => $monthInput]) }}"
                                       class="font-bold text-gray-800 hover:text-emerald-700">{{ $teacher->name }}</a>
                                    @if($teacher->studySessions->isNotEmpty())
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            @foreach($teacher->studySessions as $session)
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-teal-100 text-teal-800">{{ $session->name }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-gray-100 text-gray-600">{{ $summary['pay_type']->label() }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <x-duration :minutes="$summary['total_minutes']" class="font-black text-emerald-700" />
                                </td>
                                <td class="px-4 py-3">
                                    <x-duration :minutes="$summary['planned_minutes']" class="text-gray-500" />
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap font-bold text-gray-700" dir="ltr">
                                    @if($summary['pay_type'] === \App\Enums\PayType::Hourly)
                                        {{ $summary['hourly_rate'] !== null ? number_format($summary['hourly_rate'], 2) : ($summary['breakdown'] !== [] ? 'متعدد' : '—') }}
                                    @else
                                        {{ $summary['monthly_salary'] !== null ? number_format($summary['monthly_salary'], 2) : 'غير محدد' }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-bold text-gray-800" dir="ltr">{{ number_format($summary['gross'], 2) }}</td>
                                <td class="px-4 py-3 text-emerald-700" dir="ltr">{{ number_format($summary['paid'], 2) }}</td>
                                <td class="px-4 py-3 font-bold" dir="ltr">
                                    <span @class(['text-amber-600' => $summary['remaining'] > 0, 'text-gray-300' => $summary['remaining'] <= 0])>
                                        {{ number_format($summary['remaining'], 2) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    @if($summary['status'] === \App\Enums\PayrollStatus::Closed)
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-gray-200 text-gray-600">مغلق</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $summary['state']->badgeClasses() }}">{{ $summary['state']->label() }}</span>
                                    @endif
                                    @if($summary['missing_rates'] !== [])
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800">لا سعر</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <a href="{{ route('admin.payroll.sheet', ['teacher' => $teacher, 'month' => $monthInput]) }}"
                                       class="text-emerald-700 hover:underline text-xs font-bold">فتح الكشف</a>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-gray-100">{{ $teachers->links() }}</div>
        </div>
    @endif
</div>
@endsection
