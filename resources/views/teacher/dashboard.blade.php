@extends('layouts.app')

@section('title', 'الصفحة الرئيسية')

@section('content')
@php
    $firstName = preg_split('/\s+/u', trim(auth()->user()->name), 2)[0] ?? auth()->user()->name;
@endphp

{{-- ===== ترحيب ===== --}}
<section class="relative overflow-hidden rounded-[28px] gradient-sidebar text-white p-7 sm:p-9 mb-8 reveal">
    <div class="sidebar-pattern absolute inset-0 pointer-events-none"></div>
    <div aria-hidden="true" class="absolute -top-16 -start-16 w-72 h-72 rounded-full bg-gold-300/15 blur-3xl pointer-events-none"></div>

    <div class="relative flex flex-col lg:flex-row lg:items-end justify-between gap-6">
        <div>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-gold-300/30 bg-gold-400/10 px-3.5 py-1.5 text-[11px] font-bold text-gold-200 mb-4">
                <span class="pulse-dot w-1.5 h-1.5 rounded-full bg-gold-300"></span>
                بوابة المعلم — جامع {{ auth()->user()->tenant?->name }}
            </span>
            <h1 class="text-2xl sm:text-4xl font-black leading-snug">أهلاً بك يا أستاذ {{ $firstName }} 👋</h1>
            <p class="text-emerald-50/70 mt-2.5 text-sm font-medium flex items-center gap-2">
                <x-icon name="calendar" class="w-4 h-4 text-gold-300/80" />
                {{ \Carbon\Carbon::now()->translatedFormat('l، d F Y') }}
            </p>
        </div>
        <div class="flex gap-3 shrink-0">
            <div class="glass-card !bg-white/10 border border-white/15 rounded-2xl px-6 py-4 text-center min-w-32">
                <div class="text-3xl font-black gold-text tabular-nums" data-count-up data-to="{{ $todaySchedule->count() }}">0</div>
                <div class="text-[11px] text-emerald-100/80 font-semibold mt-1">حصص اليوم</div>
            </div>
            <div class="glass-card !bg-white/10 border border-white/15 rounded-2xl px-6 py-4 text-center min-w-32">
                <div class="text-3xl font-black text-white tabular-nums" data-count-up data-to="{{ $pendingSubmissions }}">0</div>
                <div class="text-[11px] text-emerald-100/80 font-semibold mt-1">واجبات بانتظار التصحيح</div>
            </div>
        </div>
    </div>
</section>

{{-- ===== جدول اليوم ===== --}}
<section class="reveal rd-1 rounded-2xl bg-white border border-pine-950/[0.06] shadow-[0_1px_3px_rgba(5,32,25,0.05)] overflow-hidden mb-8">
    <div class="px-6 py-4 flex items-center justify-between border-b border-gray-100">
        <h2 class="font-black text-pine-950 flex items-center gap-2.5">
            <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 grid place-items-center"><x-icon name="calendar" class="w-4 h-4" /></span>
            جدول اليوم
        </h2>
        @if($todaySchedule->isNotEmpty())
            <span class="inline-flex items-center gap-1.5 text-[11px] font-black text-emerald-700 bg-emerald-50 rounded-full px-3 py-1.5">
                <span class="pulse-dot w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                {{ $todaySchedule->count() }} حصص
            </span>
        @endif
    </div>

    @if($todaySchedule->isEmpty())
        <div class="px-6 py-14 text-center">
            <span class="inline-flex w-14 h-14 rounded-2xl bg-gold-50 text-gold-500 grid place-items-center mb-3"><x-icon name="check" class="w-7 h-7" /></span>
            <p class="text-gray-400 font-black">لا توجد حصص اليوم</p>
            <p class="text-gray-300 text-xs font-medium mt-1">استمتع بيومك المدرسي الآخر 🌟</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="mini-table w-full text-sm">
                <thead>
                    <tr class="text-gray-500">
                        <th class="px-6 py-3 text-right font-bold whitespace-nowrap">الوقت</th>
                        <th class="px-4 py-3 text-right font-bold">المادة</th>
                        <th class="px-4 py-3 text-right font-bold">الصف</th>
                        <th class="px-6 py-3 text-right font-bold">الشعبة</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($todaySchedule as $schedule)
                        <tr>
                            <td class="px-6 py-3.5 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-pine-50 text-pine-700 font-black text-xs">
                                    <x-icon name="clock" class="w-3.5 h-3.5" />
                                    {{ $schedule->starts_at }} — {{ $schedule->ends_at }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 font-bold text-pine-950">{{ $schedule->subject?->name }}</td>
                            <td class="px-4 py-3.5 font-semibold text-gray-600">{{ $schedule->classroom?->name }}</td>
                            <td class="px-6 py-3.5 font-semibold text-gray-600">{{ $schedule->section?->name ?? 'كل الشعب' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

{{-- ===== آخر الإعلانات ===== --}}
<section class="reveal rd-2 rounded-2xl bg-white border border-pine-950/[0.06] shadow-[0_1px_3px_rgba(5,32,25,0.05)] overflow-hidden">
    <div class="px-6 py-4 flex items-center justify-between bg-gradient-to-l from-pine-800 to-pine-950 text-white">
        <h2 class="font-black flex items-center gap-2.5">
            <span class="w-8 h-8 rounded-lg bg-gold-400/20 text-gold-300 grid place-items-center"><x-icon name="megaphone" class="w-4 h-4" /></span>
            آخر الإعلانات
        </h2>
    </div>

    @if($announcements->isEmpty())
        <div class="px-6 py-12 text-center">
            <span class="inline-flex w-14 h-14 rounded-2xl bg-gray-50 text-gray-300 grid place-items-center mb-3"><x-icon name="megaphone" class="w-7 h-7" /></span>
            <p class="text-gray-400 font-bold">لا توجد إعلانات حالياً</p>
        </div>
    @else
        <div class="divide-y divide-gray-50">
            @foreach($announcements as $announcement)
                <div class="px-6 py-4 hover:bg-gold-50/40 transition-colors duration-300">
                    <div class="flex flex-wrap justify-between items-start gap-2">
                        <h3 class="font-black text-pine-950">{{ $announcement->title }}</h3>
                        <span class="text-[11px] font-bold text-gray-400 bg-gray-50 rounded-full px-2.5 py-1">{{ $announcement->published_at?->translatedFormat('d M Y') }}</span>
                    </div>
                    <p class="text-gray-500 text-sm font-medium mt-1.5 leading-relaxed">{{ $announcement->body }}</p>
                </div>
            @endforeach
        </div>
    @endif
</section>
@endsection
