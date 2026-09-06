@extends('layouts.app')

@section('title', 'بوابة ولي الأمر')

@section('content')
@php
    $firstName = preg_split('/\s+/u', trim(auth()->user()->name), 2)[0] ?? auth()->user()->name;
@endphp

<section class="relative overflow-hidden rounded-[28px] gradient-sidebar text-white p-7 sm:p-9 mb-8 reveal">
    <div class="sidebar-pattern absolute inset-0 pointer-events-none"></div>
    <div aria-hidden="true" class="absolute -top-16 -end-10 w-72 h-72 rounded-full bg-gold-300/15 blur-3xl pointer-events-none"></div>

    <div class="relative">
        <span class="inline-flex items-center gap-1.5 rounded-full border border-gold-300/30 bg-gold-400/10 px-3.5 py-1.5 text-[11px] font-bold text-gold-200 mb-4">
            <span class="pulse-dot w-1.5 h-1.5 rounded-full bg-gold-300"></span>
            بوابة ولي الأمر — جامع {{ auth()->user()->tenant?->name }}
        </span>
        <h1 class="text-2xl sm:text-3xl font-black leading-snug">أهلاً بك، {{ $firstName }} 👋</h1>
        <p class="text-emerald-50/70 mt-2 text-sm font-medium">تابع مسيرة أبنائك الأكاديمية: الحضور، الامتحانات، الواجبات والنتائج.</p>
    </div>
</section>

<h2 class="font-black text-pine-950 text-lg mb-4 flex items-center gap-2.5">
    <span class="w-8 h-8 rounded-lg bg-gold-50 text-gold-600 grid place-items-center"><x-icon name="children" class="w-4 h-4" /></span>
    أبنائي
</h2>

@forelse($cards as $index => $card)
    @php
        $student = $card['student'];
        $pct = $card['attendance']['percentage'] ?? null;
        $gradient = ($student->gender ?? null) === 'female'
            ? 'from-gold-400 via-gold-500 to-gold-700'
            : 'from-emerald-400 via-pine-500 to-pine-800';
    @endphp
    <a href="{{ route('guardian.children.overview', $student) }}" style="animation-delay:{{ $index * 90 }}ms" class="reveal rd-1 group relative block overflow-hidden rounded-2xl bg-white border border-pine-950/[0.06] card-hover card-hover-ring shadow-[0_1px_3px_rgba(5,32,25,0.05)] mb-4">
        <div class="flex flex-wrap items-center justify-between gap-4 p-5 sm:p-6">
            <div class="flex items-center gap-4">
                <span class="w-14 h-14 rounded-2xl p-[2px] bg-gradient-to-br from-gold-200 via-gold-400 to-gold-600 shrink-0">
                    <span class="w-full h-full rounded-[12px] bg-gradient-to-br {{ $gradient }} grid place-items-center text-white text-xl font-black">
                        {{ mb_substr($student->name, 0, 1) }}
                    </span>
                </span>
                <div>
                    <div class="text-lg font-black text-pine-950 group-hover:text-emerald-700 transition-colors duration-300">{{ $student->name }}</div>
                    <div class="text-sm text-gray-500 font-semibold mt-0.5 flex items-center gap-1.5">
                        <x-icon name="classrooms" class="w-4 h-4 text-gold-500" />
                        {{ $student->classroom?->name ?? 'غير مقيد' }}
                        @if($student->section) — شعبة {{ $student->section->name }} @endif
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-x-7 gap-y-3 text-sm">
                <div class="text-center min-w-20">
                    <div class="text-2xl font-black {{ $pct !== null && $pct < 75 ? 'text-red-500' : 'text-emerald-600' }} tabular-nums">{{ $pct !== null ? $pct.'٪' : '—' }}</div>
                    <div class="text-[11px] text-gray-400 font-bold mt-0.5">نسبة الحضور</div>
                </div>
                <div class="w-px h-10 bg-gray-100 hidden sm:block"></div>
                <div class="flex gap-5 text-sm font-semibold text-gray-600">
                    <div class="text-center">
                        <span class="text-lg font-black text-red-500 tabular-nums block">{{ $card['attendance']['absent'] }}</span>
                        <span class="text-[11px] text-gray-400 font-bold">غياب</span>
                    </div>
                    <div class="text-center">
                        <span class="text-lg font-black text-amber-500 tabular-nums block">{{ $card['attendance']['late'] }}</span>
                        <span class="text-[11px] text-gray-400 font-bold">تأخر</span>
                    </div>
                    <div class="text-center">
                        <span class="text-lg font-black text-sky-600 tabular-nums block">{{ count($card['upcomingExams']) }}</span>
                        <span class="text-[11px] text-gray-400 font-bold">امتحانات قادمة</span>
                    </div>
                    <div class="text-center">
                        <span class="text-lg font-black text-gold-600 tabular-nums block">{{ $card['pendingHomeworks'] }}</span>
                        <span class="text-[11px] text-gray-400 font-bold">واجبات معلقة</span>
                    </div>
                </div>
                <span class="w-9 h-9 rounded-xl bg-gray-50 grid place-items-center text-gray-300 group-hover:bg-gradient-to-l group-hover:from-gold-400 group-hover:to-gold-600 group-hover:text-white transition-all duration-300 shrink-0">
                    <x-icon name="chevron" class="w-5 h-5 -rotate-90" />
                </span>
            </div>
        </div>
    </a>
@empty
    <div class="rounded-2xl bg-white border border-dashed border-gray-200 p-12 text-center reveal rd-1">
        <span class="inline-flex w-16 h-16 rounded-2xl bg-gray-50 text-gray-300 grid place-items-center mb-4"><x-icon name="children" class="w-8 h-8" /></span>
        <p class="text-gray-400 font-black">لا يوجد أبناء مرتبطون بحسابك حالياً</p>
        <p class="text-gray-300 text-xs font-medium mt-1.5">تواصل مع إدارة الجامع لربط أبنائك بحسابك</p>
    </div>
@endforelse

<div class="reveal rd-2 mt-7 flex items-start gap-3.5 rounded-2xl bg-gradient-to-l from-gold-50 to-white border border-gold-200/60 p-5 text-sm text-gray-600 font-semibold">
    <span class="w-9 h-9 shrink-0 rounded-xl bg-gold-400/20 text-gold-600 grid place-items-center"><x-icon name="info" class="w-5 h-5" /></span>
    <p class="leading-relaxed">يمكنك النقر على أي طفل لفتح ملفه الأكاديمي الكامل (الحضور، المواد، المعلمون، الامتحانات، الدرجات، الواجبات، الإعلانات).</p>
</div>
@endsection
