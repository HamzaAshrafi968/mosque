@extends('layouts.app')

@section('title', 'ملفي الشخصي')

@section('content')
<div class="max-w-3xl">
    <section class="gradient-sidebar relative overflow-hidden rounded-[28px] text-white p-6 sm:p-8 mb-6 shadow-[0_22px_50px_-22px_rgba(5,32,25,0.55)] reveal">
        <div class="sidebar-pattern absolute inset-0 pointer-events-none"></div>
        <div aria-hidden="true" class="absolute -top-16 -end-10 w-72 h-72 rounded-full bg-gold-300/15 blur-3xl pointer-events-none"></div>
        <div class="relative flex flex-wrap items-center gap-5">
            <div class="shrink-0 rounded-full p-[3px] bg-gradient-to-br from-gold-200 via-gold-400 to-gold-600 shadow-lg shadow-pine-950/40">
                <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-full bg-pine-950 overflow-hidden grid place-items-center text-white text-4xl font-black">
                    @if($student->avatarUrl())
                        <img src="{{ $student->avatarUrl() }}" alt="{{ $student->name }}" class="w-full h-full object-cover">
                    @else
                        <span class="w-full h-full grid place-items-center {{ $student->gender === 'female' ? 'bg-gradient-to-br from-gold-400 to-gold-700' : 'bg-gradient-to-br from-emerald-500 to-pine-800' }}">{{ $student->avatarInitial() }}</span>
                    @endif
                </div>
            </div>
            <div class="min-w-0 flex-1">
                <span class="inline-flex items-center gap-1.5 rounded-full border border-gold-300/30 bg-gold-400/10 px-3 py-1 text-[11px] font-bold text-gold-200 mb-3">
                    <x-icon name="students" class="w-3.5 h-3.5" />حساب الطالب
                </span>
                <h1 class="text-2xl sm:text-3xl font-black leading-tight break-words">{{ $student->name }}</h1>
                <p class="text-emerald-50/70 text-sm font-medium mt-2">
                    {{ $student->classroom?->name ?? 'غير مقيد' }}
                    @if($student->section) — شعبة {{ $student->section->name }} @endif
                    · جامع {{ auth()->user()->tenant?->name }}
                </p>
            </div>
        </div>
    </section>

    <section class="reveal rounded-2xl bg-white border border-pine-950/[0.06] shadow-[0_1px_3px_rgba(5,32,25,0.05)] overflow-hidden">
        <header class="px-5 sm:px-6 py-4 border-b border-gray-100">
            <h2 class="font-black text-pine-950 flex items-center gap-2.5 text-base">
                <span class="w-8 h-8 rounded-lg bg-gold-50 text-gold-600 grid place-items-center"><x-icon name="user" class="w-4 h-4" /></span>
                بياناتي الشخصية
            </h2>
        </header>
        <dl class="divide-y divide-gray-50 text-sm">
            @foreach([
                ['user', 'الاسم', $student->name],
                ['calendar', 'تاريخ الميلاد', $student->birth_date?->format('Y/m/d') ?? '—'],
                ['classrooms', 'الصف', $student->classroom?->name ?? '—'],
                ['sections', 'الشعبة', $student->section?->name ?? '—'],
                ['mail', 'بريد الحساب', $user->email],
            ] as [$icon, $label, $value])
                <div class="flex items-center justify-between gap-3 px-5 sm:px-6 py-3.5">
                    <dt class="text-gray-400 font-semibold text-xs flex items-center gap-1.5">
                        <x-icon name="{{ $icon }}" class="w-4 h-4 text-gray-300" />
                        {{ $label }}
                    </dt>
                    <dd class="font-bold text-pine-950 text-left {{ $icon === 'mail' ? 'dir-ltr' : '' }} break-all">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    <div class="reveal rd-1 mt-5 flex items-start gap-3.5 rounded-2xl bg-gradient-to-l from-gold-50 to-white border border-gold-200/60 p-5 text-sm text-gray-600 font-semibold">
        <span class="w-9 h-9 shrink-0 rounded-xl bg-gold-400/20 text-gold-600 grid place-items-center"><x-icon name="info" class="w-5 h-5" /></span>
        <p class="leading-relaxed">بعض البيانات الإدارية لا تظهر في البوابة — للاستفسار أو تعديل البيانات تواصل مع إدارة الجامع.</p>
    </div>
</div>
@endsection
