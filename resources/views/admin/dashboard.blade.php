@extends('layouts.app')

@section('title', 'لوحة التحكم')

@section('content')
@php
    $todayRate = isset($stats['attendance_rate_today']) ? (float) rtrim($stats['attendance_rate_today'], '%') : null;
    $examRate = isset($stats['exam_pass_rate']) ? (float) rtrim($stats['exam_pass_rate'], '%') : null;
    $hwRate = isset($stats['homework_pass_rate']) ? (float) rtrim($stats['homework_pass_rate'], '%') : null;
    $firstName = preg_split('/\s+/u', trim(auth()->user()->name), 2)[0] ?? auth()->user()->name;
@endphp

{{-- ===== ترحيب/هيرو ===== --}}
<section class="relative overflow-hidden rounded-[28px] gradient-sidebar text-white p-7 sm:p-9 mb-8 reveal">
    <div class="sidebar-pattern absolute inset-0 pointer-events-none"></div>
    <div aria-hidden="true" class="absolute -top-16 -start-16 w-72 h-72 rounded-full bg-gold-300/15 blur-3xl pointer-events-none"></div>
    <div aria-hidden="true" class="absolute -bottom-20 -end-10 w-80 h-80 rounded-full bg-emerald-300/15 blur-3xl pointer-events-none animate-blob"></div>

    <div class="relative flex flex-col lg:flex-row lg:items-end justify-between gap-6">
        <div>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-gold-300/30 bg-gold-400/10 px-3.5 py-1.5 text-[11px] font-bold text-gold-200 mb-4">
                <span class="pulse-dot w-1.5 h-1.5 rounded-full bg-gold-300"></span>
                لوحة إدارة جامع {{ auth()->user()->tenant?->name }}
            </span>
            <h1 class="text-2xl sm:text-4xl font-black leading-snug">أهلاً بك، {{ $firstName }} 👋</h1>
            <p class="text-emerald-50/70 mt-2.5 text-sm font-medium flex items-center gap-2">
                <x-icon name="calendar" class="w-4 h-4 text-gold-300/80" />
                {{ \Carbon\Carbon::now()->translatedFormat('l، d F Y') }}
            </p>
        </div>

        @if($todayRate !== null)
            <div class="shrink-0">
                <div class="glass-card !bg-white/10 border border-white/15 rounded-2xl px-6 py-4 flex items-center gap-5 animate-floaty">
                    <div class="text-center">
                        <div class="text-3xl font-black gold-text tabular-nums" data-count-up data-to="{{ round($todayRate, 1) }}">…</div>
                        <div class="text-[11px] text-emerald-100/80 font-semibold mt-1">نسبة حضور اليوم</div>
                    </div>
                    <div class="w-px h-12 bg-white/15"></div>
                    <div class="text-center">
                        <div class="text-3xl font-black tabular-nums">{{ $stats['attendance_present_today'] ?? 0 }}</div>
                        <div class="text-[11px] text-emerald-100/80 font-semibold mt-1">طالب حاضر</div>
                    </div>
                </div>
            </div>
        @endif
    </div>
    <div class="absolute inset-x-8 bottom-0 gold-hairline !opacity-60"></div>
</section>

{{-- ===== البطاقات الرئيسية ===== --}}
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-5 mb-8">
    <div class="reveal rd-1 group relative overflow-hidden rounded-2xl bg-white p-5 border border-pine-950/[0.06] card-hover card-hover-ring shadow-[0_1px_3px_rgba(5,32,25,0.05)]">
        <span class="w-11 h-11 rounded-xl grid place-items-center text-white shadow-lg bg-gradient-to-br from-emerald-400 to-emerald-700 group-hover:rotate-6 group-hover:scale-110 transition-all duration-300"><x-icon name="students" class="w-5 h-5" /></span>
        <div class="mt-4">
            <div class="text-2xl sm:text-3xl font-black text-pine-950 tabular-nums" data-count-up data-to="{{ $stats['students_count'] ?? 0 }}">{{ $stats['students_count'] ?? 0 }}</div>
            <div class="text-[13px] text-gray-500 font-bold mt-1">الطلاب المسجلون</div>
            <div class="text-[11px] text-gray-400 font-semibold mt-1">ذكور {{ $stats['male_students_count'] ?? 0 }} · إناث {{ $stats['female_students_count'] ?? 0 }}</div>
        </div>
    </div>

    <div class="reveal rd-2 group relative overflow-hidden rounded-2xl bg-white p-5 border border-pine-950/[0.06] card-hover card-hover-ring shadow-[0_1px_3px_rgba(5,32,25,0.05)]">
        <span class="w-11 h-11 rounded-xl grid place-items-center text-white shadow-lg bg-gradient-to-br from-pine-500 to-pine-800 group-hover:rotate-6 group-hover:scale-110 transition-all duration-300"><x-icon name="teachers" class="w-5 h-5" /></span>
        <div class="mt-4">
            <div class="text-2xl sm:text-3xl font-black text-pine-950 tabular-nums" data-count-up data-to="{{ $stats['teachers_count'] ?? 0 }}">{{ $stats['teachers_count'] ?? 0 }}</div>
            <div class="text-[13px] text-gray-500 font-bold mt-1">المعلمون</div>
            <div class="text-[11px] text-gray-400 font-semibold mt-1">هيئة التدريس بالجامع</div>
        </div>
    </div>

    <div class="reveal rd-3 group relative overflow-hidden rounded-2xl bg-white p-5 border border-pine-950/[0.06] card-hover card-hover-ring shadow-[0_1px_3px_rgba(5,32,25,0.05)]">
        <span class="w-11 h-11 rounded-xl grid place-items-center text-white shadow-lg bg-gradient-to-br from-gold-400 to-gold-700 group-hover:rotate-6 group-hover:scale-110 transition-all duration-300"><x-icon name="classrooms" class="w-5 h-5" /></span>
        <div class="mt-4">
            <div class="text-2xl sm:text-3xl font-black text-pine-950 tabular-nums" data-count-up data-to="{{ $stats['classrooms_count'] ?? 0 }}">{{ $stats['classrooms_count'] ?? 0 }}</div>
            <div class="text-[13px] text-gray-500 font-bold mt-1">الصفوف الدراسية</div>
            <div class="text-[11px] text-gray-400 font-semibold mt-1">بواقع {{ $stats['sections_count'] ?? 0 }} شعبة</div>
        </div>
    </div>

    <div class="reveal rd-4 group relative overflow-hidden rounded-2xl bg-white p-5 border border-pine-950/[0.06] card-hover card-hover-ring shadow-[0_1px_3px_rgba(5,32,25,0.05)]">
        <span class="w-11 h-11 rounded-xl grid place-items-center text-white shadow-lg bg-gradient-to-br from-teal-400 to-pine-700 group-hover:rotate-6 group-hover:scale-110 transition-all duration-300"><x-icon name="attendance" class="w-5 h-5" /></span>
        <div class="mt-4">
            <div class="text-2xl sm:text-3xl font-black text-pine-950 tabular-nums" data-count-up data-to="{{ $stats['attendance_present_today'] ?? 0 }}">{{ $stats['attendance_present_today'] ?? 0 }}</div>
            <div class="text-[13px] text-gray-500 font-bold mt-1">حاضرون اليوم</div>
            <div class="text-[11px] text-gray-400 font-semibold mt-1">من إجمالي الحضور المُسجّل</div>
        </div>
    </div>
</div>

{{-- ===== تفاصيل اليوم ===== --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-8">
    <div class="reveal rd-1 lg:col-span-1 rounded-2xl bg-white border border-pine-950/[0.06] p-6 shadow-[0_1px_3px_rgba(5,32,25,0.05)]">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-black text-pine-950 flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 grid place-items-center"><x-icon name="calendar" class="w-4 h-4" /></span>
                حالة الحضور اليوم
            </h3>
            <span class="text-[11px] font-bold text-gray-400 bg-gray-50 rounded-full px-3 py-1">{{ \Carbon\Carbon::now()->format('Y-m-d') }}</span>
        </div>

        @if($todayRate !== null)
            <div class="h-3 rounded-full bg-pine-950/[0.06] overflow-hidden mb-5">
                <div class="h-full rounded-full bg-gradient-to-l from-gold-300 via-gold-400 to-gold-600 transition-all duration-1000" style="width: {{ min(100, max(0, $todayRate)) }}%"></div>
            </div>
            <div class="flex items-center justify-between text-sm mb-4">
                <span class="font-bold text-gray-600">نسبة الحضور</span>
                <span class="font-black text-gold-600 tabular-nums">{{ rtrim((string) $stats['attendance_rate_today'], '%') }}٪</span>
            </div>
        @endif

        <div class="space-y-2.5">
            <div class="flex items-center justify-between rounded-xl bg-emerald-50/70 px-4 py-2.5">
                <span class="flex items-center gap-2 text-sm font-bold text-emerald-800"><span class="w-2 h-2 rounded-full bg-emerald-500"></span>حاضرون</span>
                <span class="font-black text-emerald-700 tabular-nums text-lg">{{ $stats['attendance_present_today'] ?? 0 }}</span>
            </div>
            <div class="flex items-center justify-between rounded-xl bg-amber-50/80 px-4 py-2.5">
                <span class="flex items-center gap-2 text-sm font-bold text-amber-800"><span class="w-2 h-2 rounded-full bg-amber-400"></span>متأخرون</span>
                <span class="font-black text-amber-600 tabular-nums text-lg">{{ $stats['attendance_late_today'] ?? 0 }}</span>
            </div>
            <div class="flex items-center justify-between rounded-xl bg-red-50/80 px-4 py-2.5">
                <span class="flex items-center gap-2 text-sm font-bold text-red-800"><span class="w-2 h-2 rounded-full bg-red-400"></span>غياب</span>
                <span class="font-black text-red-500 tabular-nums text-lg">{{ $stats['attendance_absent_today'] ?? 0 }}</span>
            </div>
        </div>
    </div>

    <div class="reveal rd-2 lg:col-span-2 rounded-2xl bg-white border border-pine-950/[0.06] p-6 shadow-[0_1px_3px_rgba(5,32,25,0.05)]">
        <div class="flex items-center justify-between mb-6">
            <h3 class="font-black text-pine-950 flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-lg bg-pine-50 text-pine-700 grid place-items-center"><x-icon name="grades" class="w-4 h-4" /></span>
                النتائج الأكاديمية
            </h3>
            <span class="text-[11px] font-bold text-pine-600 bg-pine-50 rounded-full px-3 py-1">امتحانات · واجبات</span>
        </div>

        <div class="grid sm:grid-cols-2 gap-5">
            <div class="rounded-2xl border border-pine-950/[0.05] p-5">
                <div class="flex items-center gap-2 mb-4">
                    <x-icon name="exam" class="w-4 h-4 text-pine-600" />
                    <h4 class="text-sm font-black text-pine-900">الامتحانات</h4>
                </div>
                <div class="grid grid-cols-3 gap-2 text-center mb-4">
                    <div class="rounded-xl bg-green-50 py-3">
                        <div class="text-xl font-black text-green-700 tabular-nums">{{ $stats['exam_passed_count'] ?? 0 }}</div>
                        <div class="text-[10px] font-bold text-green-600/80 mt-0.5">ناجح</div>
                    </div>
                    <div class="rounded-xl bg-red-50 py-3">
                        <div class="text-xl font-black text-red-500 tabular-nums">{{ $stats['exam_failed_count'] ?? 0 }}</div>
                        <div class="text-[10px] font-bold text-red-400 mt-0.5">راسب</div>
                    </div>
                    <div class="rounded-xl bg-gold-50 py-3">
                        <div class="text-xl font-black text-gold-700 tabular-nums">{{ $examRate !== null ? round($examRate, 1).'٪' : '—' }}</div>
                        <div class="text-[10px] font-bold text-gold-600/80 mt-0.5">نسبة النجاح</div>
                    </div>
                </div>
                @if($examRate !== null)
                    <div class="h-2.5 rounded-full bg-pine-950/[0.06] overflow-hidden">
                        <div class="h-full rounded-full bg-gradient-to-l from-green-400 to-green-600" style="width: {{ min(100, max(0, $examRate)) }}%"></div>
                    </div>
                @endif
            </div>

            <div class="rounded-2xl border border-pine-950/[0.05] p-5">
                <div class="flex items-center gap-2 mb-4">
                    <x-icon name="homework" class="w-4 h-4 text-pine-600" />
                    <h4 class="text-sm font-black text-pine-900">الواجبات</h4>
                </div>
                <div class="grid grid-cols-3 gap-2 text-center mb-4">
                    <div class="rounded-xl bg-green-50 py-3">
                        <div class="text-xl font-black text-green-700 tabular-nums">{{ $stats['homework_passed_count'] ?? 0 }}</div>
                        <div class="text-[10px] font-bold text-green-600/80 mt-0.5">ناجح</div>
                    </div>
                    <div class="rounded-xl bg-red-50 py-3">
                        <div class="text-xl font-black text-red-500 tabular-nums">{{ $stats['homework_failed_count'] ?? 0 }}</div>
                        <div class="text-[10px] font-bold text-red-400 mt-0.5">راسب</div>
                    </div>
                    <div class="rounded-xl bg-gold-50 py-3">
                        <div class="text-xl font-black text-gold-700 tabular-nums">{{ $hwRate !== null ? round($hwRate, 1).'٪' : '—' }}</div>
                        <div class="text-[10px] font-bold text-gold-600/80 mt-0.5">نسبة النجاح</div>
                    </div>
                </div>
                @if($hwRate !== null)
                    <div class="h-2.5 rounded-full bg-pine-950/[0.06] overflow-hidden">
                        <div class="h-full rounded-full bg-gradient-to-l from-green-400 to-green-600" style="width: {{ min(100, max(0, $hwRate)) }}%"></div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ===== آخر الإعلانات ===== --}}
<div class="reveal rd-3 rounded-2xl bg-white border border-pine-950/[0.06] shadow-[0_1px_3px_rgba(5,32,25,0.05)] overflow-hidden">
    <div class="relative px-6 py-4 flex items-center justify-between bg-gradient-to-l from-pine-800 to-pine-950 text-white">
        <h3 class="font-black flex items-center gap-2.5">
            <span class="w-8 h-8 rounded-lg bg-gold-400/20 text-gold-300 grid place-items-center"><x-icon name="megaphone" class="w-4 h-4" /></span>
            آخر الإعلانات
        </h3>
        <a href="{{ route('admin.announcements.index') }}" class="text-[11px] font-bold text-gold-200/90 hover:text-gold-100 transition flex items-center gap-1">
            عرض الكل
            <span class="transition-transform duration-300 group-hover:-translate-x-1">←</span>
        </a>
    </div>

    @if($announcements->isEmpty())
        <div class="px-6 py-12 text-center">
            <span class="inline-flex w-14 h-14 rounded-2xl bg-gray-50 text-gray-300 grid place-items-center mb-3"><x-icon name="megaphone" class="w-7 h-7" /></span>
            <p class="text-gray-400 font-bold">لا توجد إعلانات منشورة بعد</p>
            <p class="text-gray-300 text-xs font-medium mt-1">أنشئ أول إعلان ليظهر هنا</p>
        </div>
    @else
        <div class="divide-y divide-gray-50">
            @foreach($announcements as $announcement)
                <div class="px-6 py-4 hover:bg-gold-50/40 transition-colors duration-300">
                    <div class="flex flex-wrap justify-between items-start gap-2">
                        <h4 class="font-black text-pine-950 text-[15px]">{{ $announcement->title }}</h4>
                        <span class="text-[11px] font-bold text-gray-400 bg-gray-50 rounded-full px-2.5 py-1 whitespace-nowrap">
                            {{ $announcement->author?->name }}@if($announcement->published_at) · {{ $announcement->published_at->translatedFormat('d M Y') }}@endif
                        </span>
                    </div>
                    @if(filled($announcement->body))
                        <p class="text-gray-500 text-sm font-medium mt-1.5 leading-relaxed">{{ $announcement->body }}</p>
                    @endif
                    @if($announcement->hasAudio())
                        <audio controls preload="none" src="{{ $announcement->audioUrl() }}" class="w-full mt-2"></audio>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
