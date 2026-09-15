@extends('layouts.app')

@section('title', 'ساعات عمل ' . $teacher->name)

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('admin.teachers.show', $teacher) }}" class="text-sm text-emerald-700 hover:text-emerald-800">← ملف المعلم</a>
            <h2 class="text-2xl font-extrabold text-gray-800 mt-1">ساعات العمل — {{ $teacher->name }}</h2>
            <p class="text-sm text-gray-500 mt-1">
                الإجمالي الأسبوعي: <span class="font-bold text-emerald-700">{{ $weeklyTotal }} ساعة</span>
                <span class="mx-2 text-gray-300">|</span>
                إجمالي {{ \App\Support\QuranProgramSettings::monthLabel($month->format('Y-m')) }}: <span class="font-bold text-pine-800">{{ $monthlyTotal }} ساعة</span>
            </p>
        </div>
        <div class="flex items-center gap-3">
            <form method="GET" action="{{ route('admin.teachers.work-hours.index', $teacher) }}" class="flex items-center gap-2">
                <input type="month" name="month" value="{{ $monthInput }}" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
                <button class="text-xs font-bold text-gray-600 hover:text-gray-800">عرض الشهر</button>
            </form>
            <a href="{{ route('admin.work-hours.index') }}" class="text-sm text-gray-500 hover:underline">نظرة عامة على الجميع</a>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
        <h3 class="font-black text-pine-950 mb-3">إضافة فترة عمل</h3>
        <x-work-hour-form :action="route('admin.teachers.work-hours.store', $teacher)" />
    </div>

    <x-weekly-hours-grid :hours="$hours" :days="$days" editable :teacher="$teacher" />
</div>
@endsection
