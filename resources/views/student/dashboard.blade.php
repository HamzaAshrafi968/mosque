@extends('layouts.app')

@section('title', 'بوابة الطالب')

@section('content')
{{-- ===== ترحيب ===== --}}
<section class="relative overflow-hidden rounded-[28px] gradient-sidebar text-white p-7 sm:p-9 mb-8 reveal">
    <div class="sidebar-pattern absolute inset-0 pointer-events-none"></div>
    <div aria-hidden="true" class="absolute -top-16 -start-16 w-72 h-72 rounded-full bg-gold-300/15 blur-3xl pointer-events-none"></div>

    <div class="relative flex flex-col sm:flex-row sm:items-center justify-between gap-6">
        <div class="flex items-center gap-5">
            <span class="w-16 h-16 rounded-2xl p-[2px] bg-gradient-to-br from-gold-200 via-gold-400 to-gold-600 shrink-0 shadow-xl overflow-hidden">
                @if($student->avatarUrl())
                    <img src="{{ $student->avatarUrl() }}" alt="{{ $student->name }}" class="w-full h-full rounded-[14px] object-cover">
                @else
                    <span class="w-full h-full rounded-[14px] bg-pine-900/80 grid place-items-center text-2xl font-black text-gold-200">
                        {{ mb_substr($student->name, 0, 1) }}
                    </span>
                @endif
            </span>
            <div>
                <span class="inline-flex items-center gap-1.5 rounded-full border border-gold-300/30 bg-gold-400/10 px-3 py-1 text-[11px] font-bold text-gold-200 mb-2">
                    <x-icon name="students" class="w-3.5 h-3.5" />
                    بوابة الطالب
                </span>
                <h1 class="text-2xl sm:text-3xl font-black leading-snug">مرحباً، {{ $student->name }}</h1>
                <p class="text-emerald-50/70 mt-1 text-sm font-medium">
                    {{ $student->classroom?->name ?? 'غير مقيد' }}
                    @if($student->section) — شعبة {{ $student->section->name }} @endif
                    · جامع {{ auth()->user()->tenant?->name }}
                </p>
            </div>
        </div>

        @if(($attendance['percentage'] ?? null) !== null)
            <div class="glass-card !bg-white/10 border border-white/15 rounded-2xl px-7 py-5 text-center shrink-0 animate-floaty">
                <div class="text-4xl font-black gold-text tabular-nums" data-count-up data-to="{{ (float) $attendance['percentage'] }}">0</div>
                <div class="text-[11px] text-emerald-100/80 font-semibold mt-1">نسبة حضورك الإجمالية</div>
            </div>
        @endif
    </div>
</section>

{{-- ===== اختصارات ===== --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5 mb-8">
    <a href="{{ route('student.attendance') }}" class="reveal rd-1 group relative overflow-hidden rounded-2xl bg-white p-5 border border-pine-950/[0.06] card-hover card-hover-ring shadow-[0_1px_3px_rgba(5,32,25,0.05)]">
        <span class="w-11 h-11 rounded-xl grid place-items-center text-white shadow-lg bg-gradient-to-br from-emerald-400 to-emerald-700 group-hover:scale-110 group-hover:rotate-6 transition-all duration-300"><x-icon name="attendance" class="w-5 h-5" /></span>
        <div class="mt-4">
            <div class="text-2xl font-black text-emerald-700 tabular-nums">{{ $attendance['percentage'] !== null ? $attendance['percentage'].'٪' : '—' }}</div>
            <div class="text-[13px] text-gray-500 font-bold mt-0.5">نسبة الحضور</div>
        </div>
        <x-icon name="chevron" class="absolute top-5 end-4 w-4 h-4 text-gray-200 group-hover:-translate-x-1 group-hover:text-gold-400 transition-all duration-300 -rotate-90" />
    </a>

    <a href="{{ route('student.exams') }}" class="reveal rd-2 group relative overflow-hidden rounded-2xl bg-white p-5 border border-pine-950/[0.06] card-hover card-hover-ring shadow-[0_1px_3px_rgba(5,32,25,0.05)]">
        <span class="w-11 h-11 rounded-xl grid place-items-center text-white shadow-lg bg-gradient-to-br from-sky-400 to-pine-700 group-hover:scale-110 group-hover:rotate-6 transition-all duration-300"><x-icon name="exam" class="w-5 h-5" /></span>
        <div class="mt-4">
            <div class="text-2xl font-black text-sky-600 tabular-nums">{{ count($upcomingExams) }}</div>
            <div class="text-[13px] text-gray-500 font-bold mt-0.5">امتحانات قادمة</div>
        </div>
        <x-icon name="chevron" class="absolute top-5 end-4 w-4 h-4 text-gray-200 group-hover:-translate-x-1 group-hover:text-gold-400 transition-all duration-300 -rotate-90" />
    </a>

    <a href="{{ route('student.homeworks') }}" class="reveal rd-3 group relative overflow-hidden rounded-2xl bg-white p-5 border border-pine-950/[0.06] card-hover card-hover-ring shadow-[0_1px_3px_rgba(5,32,25,0.05)]">
        <span class="w-11 h-11 rounded-xl grid place-items-center text-white shadow-lg bg-gradient-to-br from-gold-400 to-gold-700 group-hover:scale-110 group-hover:rotate-6 transition-all duration-300"><x-icon name="homework" class="w-5 h-5" /></span>
        <div class="mt-4">
            <div class="text-2xl font-black text-gold-600 tabular-nums">{{ $homeworks->filter(fn ($row) => $row['submission'] && ! $row['submission']->submitted_at)->count() }}</div>
            <div class="text-[13px] text-gray-500 font-bold mt-0.5">واجبات معلقة</div>
        </div>
        <x-icon name="chevron" class="absolute top-5 end-4 w-4 h-4 text-gray-200 group-hover:-translate-x-1 group-hover:text-gold-400 transition-all duration-300 -rotate-90" />
    </a>

    <a href="{{ route('student.grades') }}" class="reveal rd-4 group relative overflow-hidden rounded-2xl bg-white p-5 border border-pine-950/[0.06] card-hover card-hover-ring shadow-[0_1px_3px_rgba(5,32,25,0.05)]">
        <span class="w-11 h-11 rounded-xl grid place-items-center text-white shadow-lg bg-gradient-to-br from-pine-500 to-pine-800 group-hover:scale-110 group-hover:rotate-6 transition-all duration-300"><x-icon name="grades" class="w-5 h-5" /></span>
        <div class="mt-4">
            <div class="text-2xl font-black text-pine-700 tabular-nums">{{ count($publishedGrades) }}</div>
            <div class="text-[13px] text-gray-500 font-bold mt-0.5">نتائج منشورة</div>
        </div>
        <x-icon name="chevron" class="absolute top-5 end-4 w-4 h-4 text-gray-200 group-hover:-translate-x-1 group-hover:text-gold-400 transition-all duration-300 -rotate-90" />
    </a>
</div>

{{-- ===== تفاصيل ===== --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
    <section class="reveal rd-2 rounded-2xl bg-white border border-pine-950/[0.06] shadow-[0_1px_3px_rgba(5,32,25,0.05)] overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-2.5">
            <span class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 grid place-items-center"><x-icon name="exam" class="w-4 h-4" /></span>
            <h2 class="font-black text-pine-950">الامتحانات القادمة</h2>
        </div>
        @forelse($upcomingExams as $exam)
            <div class="px-6 py-3.5 flex flex-wrap items-center justify-between gap-2 border-b border-gray-50 last:border-0 hover:bg-sky-50/40 transition-colors duration-200">
                <span class="font-bold text-pine-950 text-sm">{{ $exam->title }}</span>
                <span class="text-[11px] font-bold text-gray-500 flex items-center gap-1.5">
                    <x-icon name="calendar" class="w-3.5 h-3.5 text-gold-500" />
                    {{ $exam->subject?->name }} · {{ $exam->exam_date->translatedFormat('d M Y') }}
                </span>
            </div>
        @empty
            <div class="px-6 py-10 text-center">
                <p class="text-gray-400 font-bold text-sm">لا توجد امتحانات قادمة 🎉</p>
            </div>
        @endforelse
    </section>

    <section class="reveal rd-3 rounded-2xl bg-white border border-pine-950/[0.06] shadow-[0_1px_3px_rgba(5,32,25,0.05)] overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-2.5">
            <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 grid place-items-center"><x-icon name="teachers" class="w-4 h-4" /></span>
            <h2 class="font-black text-pine-950">معلموّي</h2>
        </div>
        @forelse($teachers as $row)
            <div class="px-6 py-3 flex items-center gap-3 border-b border-gray-50 last:border-0 hover:bg-emerald-50/40 transition-colors duration-200">
                <span class="w-9 h-9 rounded-full grid place-items-center text-white text-sm font-black shrink-0 bg-gradient-to-br from-pine-500 to-emerald-600">{{ mb_substr($row['teacher']->name, 0, 1) }}</span>
                <span class="font-bold text-pine-950 text-sm">{{ $row['teacher']->name }}</span>
            </div>
        @empty
            <div class="px-6 py-10 text-center">
                <p class="text-gray-400 font-bold text-sm">لا يوجد معلمون بعد</p>
            </div>
        @endforelse
    </section>
</div>

<section class="reveal rd-4 rounded-2xl bg-white border border-pine-950/[0.06] shadow-[0_1px_3px_rgba(5,32,25,0.05)] overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <span class="w-8 h-8 rounded-lg bg-gold-50 text-gold-600 grid place-items-center"><x-icon name="homework" class="w-4 h-4" /></span>
            <h2 class="font-black text-pine-950">واجباتي الأخيرة</h2>
        </div>
        <a href="{{ route('student.homeworks') }}" class="text-[11px] font-bold text-gold-600 hover:text-gold-700 transition">عرض الكل ←</a>
    </div>

    @forelse($homeworks->take(6) as $row)
        @php $hw = $row['homework']; $sub = $row['submission']; @endphp
        <div class="px-6 py-3.5 flex flex-wrap items-center justify-between gap-2 border-b border-gray-50 last:border-0 hover:bg-gold-50/40 transition-colors duration-200">
            <div>
                <span class="font-bold text-pine-950 text-sm">{{ $hw->title }}</span>
                <span class="text-gray-400 text-[11px] font-semibold mr-2">ينتهي {{ $hw->due_date->translatedFormat('d M Y') }}</span>
            </div>
            @if($sub && $sub->submitted_at)
                <span class="inline-flex items-center gap-1 text-[11px] font-black text-sky-600 bg-sky-50 rounded-full px-2.5 py-1"><x-icon name="check" class="w-3.5 h-3.5" /> تم الإرسال</span>
            @elseif($sub && $sub->status === 'pending')
                <a href="{{ route('student.homeworks') }}" class="text-[11px] font-black text-amber-600 bg-amber-50 rounded-full px-2.5 py-1 hover:bg-amber-100 transition">لم يُرسل بعد ←</a>
            @else
                <span class="text-[11px] text-gray-300 font-bold">—</span>
            @endif
        </div>
    @empty
        <div class="px-6 py-10 text-center">
            <p class="text-gray-400 font-bold text-sm">لا توجد واجبات بعد</p>
        </div>
    @endforelse
</section>
@endsection
