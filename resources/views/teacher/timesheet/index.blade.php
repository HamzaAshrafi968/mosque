@extends('layouts.app')

@section('title', 'كشوفي')

@section('content')
@php
    $closed = $summary['status'] === \App\Enums\PayrollStatus::Closed;
@endphp

<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-800">كشوفي</h2>
            <p class="text-sm text-gray-500 mt-1">فترات عملي الفعلية — يُسجّلها مدير الجامع، والإجماليات محسوبة تلقائياً.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <form method="GET" action="{{ route('teacher.timesheet.index') }}" class="flex items-center gap-2">
                <input type="month" name="month" value="{{ $monthInput }}" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
                <button class="text-xs font-bold text-gray-600 hover:text-gray-800">عرض الشهر</button>
            </form>
            <a href="{{ route('teacher.work-hours.index') }}" class="text-sm text-gray-500 hover:underline">جدولي الأسبوعي</a>
            <a href="{{ route('teacher.payroll.index') }}" class="text-sm font-bold text-emerald-700 hover:underline">كشوف رواتبي ←</a>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-x-reverse divide-gray-100 bg-white rounded-2xl shadow-sm border border-gray-200 text-center overflow-hidden">
        <div class="p-4">
            <div class="text-lg font-black text-emerald-700">{{ \App\Models\WorkSlot::formatMinutes($summary['total_minutes']) }}</div>
            <div class="text-[11px] text-gray-500 mt-0.5">الفعلي — {{ \App\Support\QuranProgramSettings::monthLabel($monthInput) }}</div>
        </div>
        <div class="p-4">
            <div class="text-lg font-black text-gray-500">{{ \App\Models\WorkSlot::formatMinutes($summary['planned_minutes']) }}</div>
            <div class="text-[11px] text-gray-500 mt-0.5">المخطط الأسبوعي</div>
        </div>
        <div class="p-4">
            <div class="text-lg font-black text-gray-800" dir="ltr">{{ number_format($summary['gross'], 2) }}</div>
            <div class="text-[11px] text-gray-500 mt-0.5">الإجمالي</div>
        </div>
        <div class="p-4">
            <div @class(['text-lg font-black', 'text-amber-600' => $summary['remaining'] > 0, 'text-gray-300' => $summary['remaining'] <= 0]) dir="ltr">
                {{ number_format($summary['remaining'], 2) }}
            </div>
            <div class="text-[11px] text-gray-500 mt-0.5">المتبقي</div>
        </div>
    </div>

    @if($summary['missing_rates'] !== [])
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <span class="font-bold">تنبيه:</span> لا يوجد سعر ساعة مسجَّل في بعض الأيام — راجع مدير الجامع.
        </div>
    @endif

    @if($slotsByDate->isEmpty())
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-10 text-center">
            <span class="inline-flex w-14 h-14 rounded-2xl bg-gold-50 text-gold-500 grid place-items-center mb-3"><x-icon name="clock" class="w-7 h-7" /></span>
            <p class="text-gray-500 font-bold">لا توجد فترات عمل في {{ \App\Support\QuranProgramSettings::monthLabel($monthInput) }}</p>
            <p class="text-gray-400 text-xs mt-1">تُسجَّل فترات العمل بواسطة مدير الجامع.</p>
        </div>
    @else
        <x-timesheet-tree :weeks="$weeks" :slots-by-date="$slotsByDate" :payroll-closed="$closed" />
    @endif
</div>
@endsection
