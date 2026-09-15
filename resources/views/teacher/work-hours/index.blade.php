@extends('layouts.app')

@section('title', 'ساعات عملي')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-800">ساعات عملي</h2>
            <p class="text-sm text-gray-500 mt-1">
                يحددها مدير الجامع — الإجمالي الأسبوعي: <span class="font-bold text-emerald-700">{{ $weeklyTotal }} ساعة</span>
                <span class="mx-2 text-gray-300">|</span>
                إجمالي {{ \App\Support\QuranProgramSettings::monthLabel($month->format('Y-m')) }}: <span class="font-bold text-pine-800">{{ $monthlyTotal }} ساعة</span>
            </p>
        </div>
        <form method="GET" action="{{ route('teacher.work-hours.index') }}" class="flex items-center gap-2">
            <input type="month" name="month" value="{{ $monthInput }}" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
            <button class="text-xs font-bold text-gray-600 hover:text-gray-800">عرض الشهر</button>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="font-black text-pine-950">ساعات اليوم</h3>
                <p class="text-xs text-gray-500 mt-0.5">{{ \App\Enums\WorkDay::from(now()->dayOfWeek)->label() }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @forelse($todayHours as $hour)
                    <span class="inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-800 font-bold text-sm rounded-xl px-3 py-1.5">
                        <x-icon name="clock" class="w-4 h-4" />
                        {{ substr($hour->start_time, 0, 5) }} — {{ substr($hour->end_time, 0, 5) }}
                    </span>
                @empty
                    <span class="text-gray-400 text-sm">لا توجد ساعات عمل محددة لليوم</span>
                @endforelse
            </div>
        </div>
    </div>

    @if($hours->isEmpty())
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-10 text-center">
            <span class="inline-flex w-14 h-14 rounded-2xl bg-gold-50 text-gold-500 grid place-items-center mb-3"><x-icon name="clock" class="w-7 h-7" /></span>
            <p class="text-gray-500 font-bold">لم تُحدد ساعات عمل بعد</p>
            <p class="text-gray-400 text-xs mt-1">يرجى مراجعة مدير الجامع لتحديد جدول ساعات العمل</p>
        </div>
    @else
        <x-weekly-hours-grid :hours="$hours" :days="$days" />
    @endif
</div>
@endsection
