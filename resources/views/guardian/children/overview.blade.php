@extends('layouts.app')

@section('title', 'ملف الطالب — '.$child->name)

@section('content')
@include('guardian.children.partials.header')

@php
    $pct = $attendance['percentage'] ?? null;
    $pending = $homeworks->filter(fn ($row) => ! $row['submission'] || $row['submission']->status === 'pending');
    $months = [1 => 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
    @php
        $stats = [
            ['label' => 'نسبة الحضور', 'value' => $pct !== null ? $pct.'٪' : '—', 'icon' => 'target', 'tint' => $pct !== null && $pct < 75 ? 'bg-red-50 text-red-500' : 'bg-emerald-50 text-emerald-600'],
            ['label' => 'أيام الغياب', 'value' => $attendance['absent'], 'icon' => 'x', 'tint' => 'bg-red-50 text-red-500'],
            ['label' => 'واجبات معلقة', 'value' => $pending->count(), 'icon' => 'homework', 'tint' => 'bg-amber-50 text-amber-500'],
            ['label' => 'امتحانات قادمة', 'value' => count($upcomingExams), 'icon' => 'exam', 'tint' => 'bg-sky-50 text-sky-600'],
        ];
    @endphp
    @foreach($stats as $i => $stat)
        <div class="reveal rd-{{ $i + 1 }} rounded-2xl bg-white border border-pine-950/[0.06] p-5 flex items-center gap-4 shadow-[0_1px_3px_rgba(5,32,25,0.05)]">
            <span class="w-12 h-12 shrink-0 rounded-2xl grid place-items-center {{ $stat['tint'] }}"><x-icon name="{{ $stat['icon'] }}" class="w-6 h-6" /></span>
            <div>
                <div class="text-2xl font-black text-pine-950 tabular-nums leading-none">{{ $stat['value'] }}</div>
                <div class="text-xs text-gray-400 font-bold mt-1.5">{{ $stat['label'] }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <section class="reveal rounded-2xl bg-white border border-pine-950/[0.06] shadow-[0_1px_3px_rgba(5,32,25,0.05)] overflow-hidden">
            <header class="flex items-center justify-between px-5 sm:px-6 py-4 border-b border-gray-100">
                <h2 class="font-black text-pine-950 flex items-center gap-2.5 text-base">
                    <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 grid place-items-center"><x-icon name="exam" class="w-4 h-4" /></span>
                    الامتحانات القادمة
                </h2>
                <a href="{{ route('guardian.children.exams', $child) }}" class="text-xs font-bold text-emerald-700 hover:text-emerald-900 transition">كل الامتحانات</a>
            </header>
            <div class="divide-y divide-gray-50">
                @forelse($upcomingExams->take(4) as $exam)
                    <div class="flex flex-wrap items-center gap-3 px-5 sm:px-6 py-3.5 hover:bg-pine-100/30 transition">
                        <span class="w-11 h-11 rounded-xl bg-gradient-to-br from-gold-100 to-gold-200 text-gold-800 grid place-items-center text-center leading-tight">
                            <span class="text-sm font-black">{{ $exam->exam_date->format('d') }}</span>
                            <span class="text-[9px] font-bold">{{ $months[$exam->exam_date->month] ?? $exam->exam_date->format('m') }}</span>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="font-bold text-pine-950 text-sm truncate">{{ $exam->title }}</div>
                            <div class="text-xs text-gray-400 font-semibold mt-0.5 flex items-center gap-1.5">
                                <x-icon name="subjects" class="w-3.5 h-3.5 text-gold-500" />{{ $exam->subject?->name ?? 'مادة عامة' }}
                            </div>
                        </div>
                        <span class="inline-flex items-center gap-1.5 text-[11px] font-black text-emerald-700 bg-emerald-50 rounded-full px-3 py-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 pulse-dot"></span>
                            بعد {{ now()->diffInDays($exam->exam_date) == 0 ? 'اليوم' : now()->diffInDays($exam->exam_date).' يوم' }}
                        </span>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center">
                        <span class="inline-flex w-14 h-14 rounded-2xl bg-gray-50 text-gray-300 grid place-items-center mb-3"><x-icon name="exam" class="w-7 h-7" /></span>
                        <p class="text-gray-400 font-black text-sm">لا توجد امتحانات قادمة</p>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="reveal rd-1 rounded-2xl bg-white border border-pine-950/[0.06] shadow-[0_1px_3px_rgba(5,32,25,0.05)] overflow-hidden">
            <header class="flex items-center justify-between px-5 sm:px-6 py-4 border-b border-gray-100">
                <h2 class="font-black text-pine-950 flex items-center gap-2.5 text-base">
                    <span class="w-8 h-8 rounded-lg bg-amber-50 text-amber-500 grid place-items-center"><x-icon name="homework" class="w-4 h-4" /></span>
                    الواجبات الحالية
                </h2>
                <a href="{{ route('guardian.children.homeworks', $child) }}" class="text-xs font-bold text-emerald-700 hover:text-emerald-900 transition">كل الواجبات</a>
            </header>
            <div class="divide-y divide-gray-50">
                @forelse($homeworks->take(4) as $row)
                    @php $homework = $row['homework']; $submission = $row['submission']; @endphp
                    <div class="flex flex-wrap items-center gap-3 px-5 sm:px-6 py-3.5 hover:bg-pine-100/30 transition">
                        <span class="w-10 h-10 shrink-0 rounded-xl bg-amber-50 text-amber-500 grid place-items-center"><x-icon name="homework" class="w-5 h-5" /></span>
                        <div class="min-w-0 flex-1">
                            <div class="font-bold text-pine-950 text-sm truncate">{{ $homework->title }}</div>
                            <div class="text-xs text-gray-400 font-semibold mt-0.5">الاستحقاق: {{ $homework->due_date->format('Y/m/d') }}@if($homework->subject?->name) — {{ $homework->subject->name }}@endif</div>
                        </div>
                        @if($submission && $submission->status === 'graded')
                            <span class="text-[11px] font-black text-emerald-700 bg-emerald-50 rounded-full px-3 py-1.5">تم التصحيح — {{ $submission->grade }}</span>
                        @elseif($submission && $submission->submitted_at)
                            <span class="text-[11px] font-black text-sky-700 bg-sky-50 rounded-full px-3 py-1.5">تم الإرسال</span>
                        @else
                            <span class="text-[11px] font-black text-amber-600 bg-amber-50 rounded-full px-3 py-1.5">قيد الإنجاز</span>
                        @endif
                    </div>
                @empty
                    <div class="px-6 py-10 text-center">
                        <span class="inline-flex w-14 h-14 rounded-2xl bg-gray-50 text-gray-300 grid place-items-center mb-3"><x-icon name="homework" class="w-7 h-7" /></span>
                        <p class="text-gray-400 font-black text-sm">لا توجد واجبات حالياً</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    <aside class="space-y-6">
        <section class="reveal rd-2 rounded-2xl bg-white border border-pine-950/[0.06] shadow-[0_1px_3px_rgba(5,32,25,0.05)] overflow-hidden">
            <header class="px-5 sm:px-6 py-4 border-b border-gray-100">
                <h2 class="font-black text-pine-950 flex items-center gap-2.5 text-base">
                    <span class="w-8 h-8 rounded-lg bg-gold-50 text-gold-600 grid place-items-center"><x-icon name="user" class="w-4 h-4" /></span>
                    البيانات الشخصية
                </h2>
            </header>
            <dl class="divide-y divide-gray-50 text-sm">
                @foreach([
                    ['الصف', $child->classroom?->name ?? '—'],
                    ['الشعبة', $child->section?->name ?? '—'],
                    ['الجنس', $child->gender === 'male' ? 'ذكر' : 'أنثى'],
                    ['تاريخ الميلاد', $child->birth_date?->format('Y/m/d') ?? '—'],
                    ['الحالة', $child->status === 'active' ? 'نشط' : 'غير نشط'],
                ] as [$label, $value])
                    <div class="flex items-center justify-between px-5 sm:px-6 py-3">
                        <dt class="text-gray-400 font-semibold text-xs">{{ $label }}</dt>
                        <dd class="font-bold text-pine-950">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
            <div class="p-4 border-t border-gray-100">
                <a href="{{ route('guardian.children.attendance', $child) }}" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-l from-emerald-700 to-pine-800 text-white text-sm font-bold py-2.5 hover:brightness-110 transition active:scale-95 shadow-md shadow-emerald-900/20">
                    <x-icon name="attendance" class="w-4 h-4" />سجل الحضور الكامل
                </a>
            </div>
        </section>

        <section class="reveal rd-3 rounded-2xl bg-white border border-pine-950/[0.06] shadow-[0_1px_3px_rgba(5,32,25,0.05)] overflow-hidden">
            <header class="flex items-center justify-between px-5 sm:px-6 py-4 border-b border-gray-100">
                <h2 class="font-black text-pine-950 flex items-center gap-2.5 text-base">
                    <span class="w-8 h-8 rounded-lg bg-pine-100 text-pine-700 grid place-items-center"><x-icon name="teachers" class="w-4 h-4" /></span>
                    معلمو {{ $child->name }}
                </h2>
                <a href="{{ route('guardian.children.teachers', $child) }}" class="text-xs font-bold text-emerald-700 hover:text-emerald-900 transition">الكل</a>
            </header>
            <div class="divide-y divide-gray-50">
                @forelse($teachers->take(4) as $row)
                    <div class="flex items-center gap-3 px-5 py-3">
                        <x-avatar :src="$row['teacher']->avatarUrl()" :name="$row['teacher']->name" size="sm" fallback-class="bg-gradient-to-br from-pine-500 to-pine-800" />
                        <div class="min-w-0">
                            <div class="font-bold text-pine-950 text-sm truncate">{{ $row['teacher']->name }}</div>
                            <div class="text-[11px] text-gray-400 font-semibold">{{ $row['subject'] ? 'مدرس مادة '.$row['subject']->name : 'مشرف على الشعبة' }}</div>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-gray-400 text-sm font-semibold">لا يوجد معلمون مرتبطون بعد</div>
                @endforelse
            </div>
        </section>
    </aside>
</div>
@endsection
