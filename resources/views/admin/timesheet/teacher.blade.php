@extends('layouts.app')

@section('title', 'كشف ' . $teacher->name)

@section('content')
@php
    $authorization = app(\App\Services\AuthorizationService::class);
    $can = fn (string $permission) => $authorization->can(auth()->user(), $permission);
    $canManage = $can('work_hours.manage');
    $closed = $summary['status'] === \App\Enums\PayrollStatus::Closed;
    $currentRate = $rates->first(fn ($rate) => $rate->statusLabel() === 'ساري');
@endphp

<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('admin.timesheet.index', ['view' => 'monthly', 'month' => $monthInput]) }}" class="text-sm text-emerald-700 hover:text-emerald-800">← مركز الكشوف</a>
            <h2 class="text-2xl font-extrabold text-gray-800 mt-1">كشف العمل — {{ $teacher->name }}</h2>
            <div class="flex flex-wrap items-center gap-1 mt-1">
                @foreach($teacher->studySessions as $session)
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-teal-100 text-teal-800">{{ $session->name }}</span>
                @endforeach
                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-gray-100 text-gray-600">{{ $summary['pay_type']->label() }}</span>
                <span @class([
                    'px-2 py-0.5 rounded-full text-[11px] font-bold',
                    'bg-emerald-100 text-emerald-800' => ! $closed,
                    'bg-gray-200 text-gray-600' => $closed,
                ])>{{ $closed ? 'الشهر مغلق' : 'الشهر مفتوح' }}</span>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <form method="GET" action="{{ route('admin.teachers.timesheet.index', $teacher) }}" class="flex items-center gap-2">
                <input type="month" name="month" value="{{ $monthInput }}" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
                <button class="text-xs font-bold text-gray-600 hover:text-gray-800">عرض الشهر</button>
            </form>
            <a href="{{ route('admin.teachers.timesheet.print', ['teacher' => $teacher, 'month' => $monthInput]) }}" target="_blank"
               class="text-sm text-gray-500 hover:underline">طباعة</a>
            <a href="{{ route('admin.teachers.work-hours.index', $teacher) }}" class="text-sm text-gray-500 hover:underline">الجدول الأسبوعي</a>
            @if($can('payroll.view') || $can('finance.view'))
                <a href="{{ route('admin.payroll.sheet', ['teacher' => $teacher, 'month' => $monthInput]) }}" class="text-sm font-bold text-emerald-700 hover:underline">كشف الراتب ←</a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 divide-x divide-x-reverse divide-gray-100 bg-white rounded-2xl shadow-sm border border-gray-200 text-center overflow-hidden">
        <div class="p-4">
            <div class="text-lg font-black text-emerald-700">{{ \App\Models\WorkSlot::formatMinutes($summary['total_minutes']) }}</div>
            <div class="text-[11px] text-gray-500 mt-0.5">الفعلي — {{ \App\Support\QuranProgramSettings::monthLabel($monthInput) }}</div>
        </div>
        <div class="p-4">
            <div class="text-lg font-black text-gray-500">{{ \App\Models\WorkSlot::formatMinutes($summary['planned_minutes']) }}</div>
            <div class="text-[11px] text-gray-500 mt-0.5">المخطط الأسبوعي</div>
        </div>
        <div class="p-4">
            <div class="text-lg font-black text-gray-800" dir="ltr">
                @if($summary['pay_type'] === \App\Enums\PayType::Hourly)
                    {{ $currentRate ? number_format((float) $currentRate->rate, 2) : '—' }}
                @else
                    {{ $summary['monthly_salary'] !== null ? number_format($summary['monthly_salary'], 2) : '—' }}
                @endif
            </div>
            <div class="text-[11px] text-gray-500 mt-0.5">{{ $summary['pay_type'] === \App\Enums\PayType::Hourly ? 'سعر الساعة الحالي' : 'الراتب الشهري' }}</div>
        </div>
        <div class="p-4">
            <div class="text-lg font-black text-gray-800" dir="ltr">{{ number_format($summary['gross'], 2) }}</div>
            <div class="text-[11px] text-gray-500 mt-0.5">الإجمالي</div>
        </div>
        <div class="p-4">
            <div class="text-lg font-black text-emerald-700" dir="ltr">{{ number_format($summary['paid'], 2) }}</div>
            <div class="text-[11px] text-gray-500 mt-0.5">المدفوع</div>
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
            <span class="font-bold">تنبيه:</span> لا يوجد سعر ساعة لهذا الأستاذ في:
            <span dir="ltr">{{ implode('، ', $summary['missing_rates']) }}</span>
            @if($can('hourly_rates.manage'))
                — <a href="{{ route('admin.payroll.rates.index', ['q' => $teacher->name]) }}" class="underline font-bold">إضافة سعر</a>
            @endif
        </div>
    @endif

    @if($canManage && ! $closed)
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 space-y-4">
            <h3 class="font-black text-pine-950">إضافة فترة عمل</h3>
            <x-work-slot-form
                :action="route('admin.timesheet.slots.store')"
                :teacher="$teacher"
                :date="now()->toDateString()"
                save-and-add />

            <form method="POST" action="{{ route('admin.teachers.timesheet.generate', $teacher) }}" class="flex flex-wrap items-end gap-2 border-t border-gray-100 pt-4">
                @csrf
                <div>
                    <label class="block text-[11px] font-bold text-gray-500 mb-0.5">توليد من الجدول الأسبوعي ليوم</label>
                    <input type="date" name="date" value="{{ now()->toDateString() }}" required
                           class="border border-gray-300 rounded-lg px-2.5 py-2 text-sm" dir="ltr">
                </div>
                <button class="bg-gray-800 hover:bg-gray-900 text-white text-sm font-bold px-4 py-2 rounded-lg">توليد الفترات</button>
                <p class="text-[11px] text-gray-400 w-full">ينسخ فترات المخطط لهذا اليوم ويتجاهل المتعارض مع المسجَّل.</p>
            </form>
        </div>
    @endif

    @if($slotsByDate->isEmpty())
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-10 text-center">
            <span class="inline-flex w-14 h-14 rounded-2xl bg-gold-50 text-gold-500 grid place-items-center mb-3"><x-icon name="clock" class="w-7 h-7" /></span>
            <p class="text-gray-500 font-bold">لا توجد فترات عمل في {{ \App\Support\QuranProgramSettings::monthLabel($monthInput) }}</p>
            @if($canManage && ! $closed)
                <p class="text-gray-400 text-xs mt-1">سجّل فترة من النموذج أعلاه أو ولّدها من الجدول الأسبوعي.</p>
            @endif
        </div>
    @else
        <x-timesheet-tree
            :weeks="$weeks"
            :slots-by-date="$slotsByDate"
            :editable="$canManage"
            :teacher="$teacher"
            :payroll-closed="$closed" />
    @endif
</div>
@endsection
