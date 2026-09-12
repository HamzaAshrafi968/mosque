@extends('layouts.app')

@section('title', 'امتحانات '.$child->name)

@section('content')
@include('guardian.children.partials.header')

@php
    $months = [1 => 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
@endphp

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <section class="reveal rounded-2xl bg-white border border-pine-950/[0.06] shadow-[0_1px_3px_rgba(5,32,25,0.05)] overflow-hidden self-start">
        <header class="flex items-center justify-between px-5 sm:px-6 py-4 border-b border-gray-100">
            <h2 class="font-black text-pine-950 flex items-center gap-2.5 text-base">
                <span class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 grid place-items-center"><x-icon name="exam" class="w-4 h-4" /></span>
                الامتحانات القادمة
            </h2>
            <span class="inline-flex items-center gap-1.5 text-[11px] font-black text-sky-700 bg-sky-50 rounded-full px-3 py-1">
                <span class="pulse-dot w-1.5 h-1.5 rounded-full bg-sky-500"></span>{{ count($upcomingExams) }} قادم
            </span>
        </header>
        <div class="divide-y divide-gray-50">
            @forelse($upcomingExams as $exam)
                <div class="flex items-center gap-4 px-5 sm:px-6 py-4 hover:bg-sky-50/40 transition">
                    <div class="w-14 h-14 shrink-0 rounded-2xl bg-gradient-to-br from-sky-100 to-sky-200 text-sky-800 grid place-items-center text-center leading-tight">
                        <div>
                            <span class="block text-base font-black">{{ $exam->exam_date->format('d') }}</span>
                            <span class="block text-[9px] font-bold">{{ $months[$exam->exam_date->month] ?? '' }}</span>
                        </div>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="font-black text-pine-950 text-sm truncate">{{ $exam->title }}</div>
                        <div class="text-xs text-gray-400 font-semibold mt-1 flex items-center gap-1.5">
                            <x-icon name="subjects" class="w-3.5 h-3.5 text-gold-500" />
                            {{ $exam->subject?->name ?? 'مادة عامة' }}
                        </div>
                    </div>
                    <div class="text-left shrink-0">
                        <div class="text-sm font-black text-pine-950 tabular-nums">{{ $exam->total_marks }}</div>
                        <div class="text-[10px] text-gray-400 font-bold">درجة</div>
                    </div>
                </div>
            @empty
                <div class="px-6 py-12 text-center">
                    <span class="inline-flex w-14 h-14 rounded-2xl bg-gray-50 text-gray-300 grid place-items-center mb-3"><x-icon name="exam" class="w-7 h-7" /></span>
                    <p class="text-gray-400 font-black text-sm">لا توجد امتحانات قادمة</p>
                </div>
            @endforelse
        </div>
    </section>

    <section class="reveal rd-1 rounded-2xl bg-white border border-pine-950/[0.06] shadow-[0_1px_3px_rgba(5,32,25,0.05)] overflow-hidden self-start">
        <header class="flex items-center justify-between px-5 sm:px-6 py-4 border-b border-gray-100">
            <h2 class="font-black text-pine-950 flex items-center gap-2.5 text-base">
                <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 grid place-items-center"><x-icon name="grades" class="w-4 h-4" /></span>
                نتائج الامتحانات المنشورة
            </h2>
            <a href="{{ route('guardian.children.grades', $child) }}" class="text-xs font-bold text-emerald-700 hover:text-emerald-900 transition">كل الدرجات</a>
        </header>
        <div class="divide-y divide-gray-50">
            @forelse($grades as $row)
                @php
                    $grade = $row['grade'];
                    $exam = $grade->exam;
                    $passed = (float) $grade->score >= (int) ($exam->pass_marks ?? 0);
                    $pctValue = (float) ($row['percentage'] ?? 0);
                @endphp
                <div class="px-5 sm:px-6 py-4 hover:bg-emerald-50/40 transition">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="font-black text-pine-950 text-sm truncate">{{ $exam->title }}</div>
                            <div class="text-xs text-gray-400 font-semibold mt-0.5">{{ $exam->subject?->name }} — {{ $exam->exam_date->format('Y/m/d') }}</div>
                        </div>
                        <div class="text-left shrink-0">
                            <div class="flex items-baseline gap-1">
                                <span class="text-xl font-black {{ $passed ? 'text-emerald-600' : 'text-red-500' }} tabular-nums">{{ $grade->score }}</span>
                                <span class="text-xs text-gray-400 font-bold">/ {{ $exam->total_marks }}</span>
                            </div>
                            <span class="inline-block mt-1 text-[10px] font-black rounded-full px-2 py-0.5 {{ $passed ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600' }}">
                                {{ $passed ? 'ناجح' : 'راسب' }} · {{ $row['percentage'] }}٪
                            </span>
                        </div>
                    </div>
                    <div class="mt-3 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                        <div class="h-full rounded-full {{ $passed ? 'bg-gradient-to-l from-emerald-400 to-emerald-600' : 'bg-gradient-to-l from-red-300 to-red-500' }}" style="width: {{ min(100, $pctValue) }}%"></div>
                    </div>
                </div>
            @empty
                <div class="px-6 py-12 text-center">
                    <span class="inline-flex w-14 h-14 rounded-2xl bg-gray-50 text-gray-300 grid place-items-center mb-3"><x-icon name="grades" class="w-7 h-7" /></span>
                    <p class="text-gray-400 font-black text-sm">لم تُنشر أي نتائج بعد</p>
                </div>
            @endforelse
        </div>
    </section>
</div>
@endsection
