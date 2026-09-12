@php
    $gender = $child->gender ?? 'male';
    $isMale = $gender === 'male';
    $fallback = $isMale ? 'bg-gradient-to-br from-pine-500 to-pine-800' : 'bg-gradient-to-br from-gold-400 to-gold-700';
    $active = request()->route()->getName();
    $tabs = [
        'overview' => ['نظرة عامة', 'guardian.children.overview', 'home'],
        'attendance' => ['الحضور', 'guardian.children.attendance', 'attendance'],
        'subjects' => ['المواد', 'guardian.children.subjects', 'subjects'],
        'teachers' => ['المعلمون', 'guardian.children.teachers', 'teachers'],
        'exams' => ['الامتحانات', 'guardian.children.exams', 'exam'],
        'grades' => ['الدرجات', 'guardian.children.grades', 'grades'],
        'homeworks' => ['الواجبات', 'guardian.children.homeworks', 'homework'],
        'announcements' => ['الإعلانات', 'guardian.children.announcements', 'megaphone'],
    ];
@endphp

<nav class="flex items-center gap-1.5 text-xs font-bold text-gray-400 mb-4 animate-fade-in">
    <a href="{{ route('guardian.dashboard') }}" class="hover:text-emerald-700 transition inline-flex items-center gap-1">
        <x-icon name="home" class="w-3.5 h-3.5" />بوابة ولي الأمر
    </a>
    <x-icon name="chevron" class="w-3.5 h-3.5 -rotate-90 text-gray-300" />
    <span class="text-pine-900 truncate">{{ $child->name }}</span>
</nav>

<section class="gradient-sidebar relative overflow-hidden rounded-[28px] text-white p-6 sm:p-8 mb-6 shadow-[0_22px_50px_-22px_rgba(5,32,25,0.55)] reveal">
    <div class="sidebar-pattern absolute inset-0 pointer-events-none"></div>
    <div aria-hidden="true" class="absolute -top-16 -end-10 w-72 h-72 rounded-full bg-gold-300/15 blur-3xl pointer-events-none"></div>
    <div aria-hidden="true" class="absolute -bottom-20 -start-16 w-64 h-64 rounded-full bg-emerald-400/15 blur-3xl pointer-events-none"></div>

    <div class="relative flex flex-wrap items-center gap-5 sm:gap-7">
        <div class="shrink-0 rounded-full p-[3px] bg-gradient-to-br from-gold-200 via-gold-400 to-gold-600 shadow-lg shadow-pine-950/40">
            <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-full bg-pine-950 overflow-hidden grid place-items-center text-white text-4xl font-black">
                @if($child->avatarUrl())
                    <img src="{{ $child->avatarUrl() }}" alt="{{ $child->name }}" class="w-full h-full object-cover">
                @else
                    <span class="w-full h-full grid place-items-center {{ $fallback }}">{{ $child->avatarInitial() }}</span>
                @endif
            </div>
        </div>

        <div class="min-w-0 flex-1">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-gold-300/30 bg-gold-400/10 px-3 py-1 text-[11px] font-bold text-gold-200 mb-3">
                <span class="pulse-dot w-1.5 h-1.5 rounded-full bg-gold-300"></span>
                الملف الأكاديمي للطالب
            </span>
            <h1 class="text-2xl sm:text-3xl font-black leading-tight break-words">{{ $child->name }}</h1>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 mt-2.5 text-sm text-emerald-50/80 font-semibold">
                <span class="inline-flex items-center gap-1.5">
                    <x-icon name="classrooms" class="w-4 h-4 text-gold-300" />
                    {{ $child->classroom?->name ?? 'غير مقيد بصف' }}
                    @if($child->section)
                        — شعبة {{ $child->section->name }}
                    @endif
                </span>
                <span class="w-1 h-1 rounded-full bg-white/25"></span>
                <span class="inline-flex items-center gap-1.5">
                    <x-icon name="user" class="w-4 h-4 text-gold-300" />
                    {{ $isMale ? 'ذكر' : 'أنثى' }}
                </span>
                @if($child->birth_date)
                    <span class="w-1 h-1 rounded-full bg-white/25"></span>
                    <span class="inline-flex items-center gap-1.5">
                        <x-icon name="calendar" class="w-4 h-4 text-gold-300" />
                        {{ $child->birth_date->format('Y/m/d') }}
                    </span>
                @endif
            </div>
        </div>

        <span class="inline-flex items-center gap-1.5 rounded-2xl border border-white/10 bg-white/5 backdrop-blur px-4 py-2.5 text-xs font-black {{ $child->status === 'active' ? 'text-emerald-200' : 'text-gold-200' }}">
            <span class="w-2 h-2 rounded-full {{ $child->status === 'active' ? 'bg-emerald-300' : 'bg-gold-300' }}"></span>
            {{ $child->status === 'active' ? 'طالب نشط' : 'غير نشط' }}
        </span>
    </div>
</section>

<div class="flex flex-wrap items-center gap-2 mb-7 rounded-2xl bg-white/80 backdrop-blur border border-pine-950/5 shadow-[0_1px_3px_rgba(5,32,25,0.05)] p-2 reveal rd-1">
    @foreach($tabs as $key => [$label, $routeName, $icon])
        <a href="{{ route($routeName, $child) }}"
           class="inline-flex items-center gap-1.5 rounded-xl px-3.5 sm:px-4 py-2.5 text-sm font-bold transition-all duration-300 active:scale-95 {{ $active === $routeName ? 'bg-gradient-to-l from-emerald-700 to-pine-800 text-white shadow-md shadow-emerald-900/25' : 'text-gray-500 hover:text-pine-900 hover:bg-pine-100/70' }}">
            <x-icon name="{{ $icon }}" class="w-4 h-4" />
            <span>{{ $label }}</span>
        </a>
    @endforeach
</div>
