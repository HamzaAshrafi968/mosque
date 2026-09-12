@extends('layouts.app')

@section('title', 'واجبات '.$child->name)

@section('content')
@include('guardian.children.partials.header')

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    @forelse($homeworks as $i => $row)
        @php
            $homework = $row['homework'];
            $submission = $row['submission'];
            $isGraded = $submission && $submission->status === 'graded';
            $isSubmitted = $submission && ! $isGraded && $submission->submitted_at;
            $isLate = ! $submission && $homework->due_date->isPast();
        @endphp
        <article class="reveal rd-{{ min($i + 1, 6) }} rounded-2xl bg-white border border-pine-950/[0.06] shadow-[0_1px_3px_rgba(5,32,25,0.05)] p-5 sm:p-6 card-hover flex flex-col overflow-hidden relative">
            <div class="absolute inset-x-0 top-0 h-1 {{ $isGraded ? 'bg-gradient-to-l from-emerald-400 to-emerald-600' : ($isSubmitted ? 'bg-gradient-to-l from-sky-400 to-sky-600' : ($isLate ? 'bg-gradient-to-l from-red-300 to-red-500' : 'bg-gradient-to-l from-gold-300 to-gold-500')) }}"></div>

            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="w-10 h-10 shrink-0 rounded-xl {{ $isGraded ? 'bg-emerald-50 text-emerald-600' : ($isSubmitted ? 'bg-sky-50 text-sky-600' : ($isLate ? 'bg-red-50 text-red-500' : 'bg-amber-50 text-amber-500')) }} grid place-items-center">
                        <x-icon name="homework" class="w-5 h-5" />
                    </span>
                    <h3 class="font-black text-pine-950 leading-snug">{{ $homework->title }}</h3>
                </div>
                @if($homework->subject?->name)
                    <span class="shrink-0 text-[10px] font-black text-pine-700 bg-pine-100/80 rounded-full px-2.5 py-1">{{ $homework->subject->name }}</span>
                @endif
            </div>

            @if($homework->description)
                <p class="text-sm text-gray-500 font-medium leading-relaxed mt-3 whitespace-pre-wrap">{{ Str::limit($homework->description, 220) }}</p>
            @endif

            @if($homework->teacher)
                <div class="flex items-center gap-2.5 mt-4">
                    <x-avatar :src="$homework->teacher->avatarUrl()" :name="$homework->teacher->name" size="xs" fallback-class="bg-gradient-to-br from-pine-500 to-pine-800" />
                    <span class="text-xs text-gray-400 font-semibold">أ. {{ $homework->teacher->name }}</span>
                </div>
            @endif

            <footer class="mt-auto pt-4 flex flex-wrap items-center justify-between gap-2 border-t border-dashed border-gray-100">
                <span class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-500">
                    <x-icon name="calendar" class="w-4 h-4 text-gray-300" />
                    الاستحقاق: {{ $homework->due_date->format('Y/m/d') }}
                </span>
                @if($isGraded)
                    <span class="inline-flex items-center gap-1.5 text-xs font-black text-emerald-700 bg-emerald-50 rounded-full px-3 py-1.5 ring-1 ring-emerald-100">
                        <x-icon name="check" class="w-3.5 h-3.5" />تم التصحيح — الدرجة {{ $submission->grade }}
                    </span>
                @elseif($isSubmitted)
                    <span class="inline-flex items-center gap-1.5 text-xs font-black text-sky-700 bg-sky-50 rounded-full px-3 py-1.5 ring-1 ring-sky-100">
                        <x-icon name="check" class="w-3.5 h-3.5" />أُرسل {{ $submission->submitted_at->format('Y/m/d') }}
                    </span>
                @elseif($isLate)
                    <span class="inline-flex items-center gap-1.5 text-xs font-black text-red-600 bg-red-50 rounded-full px-3 py-1.5 ring-1 ring-red-100">
                        <x-icon name="alert" class="w-3.5 h-3.5" />متأخر — لم يُرسل
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 text-xs font-black text-amber-600 bg-amber-50 rounded-full px-3 py-1.5 ring-1 ring-amber-100">
                        <span class="pulse-dot w-1.5 h-1.5 rounded-full bg-amber-500"></span>قيد الإنجاز
                    </span>
                @endif
            </footer>
        </article>
    @empty
        <div class="col-span-full reveal rounded-2xl border border-dashed border-gray-200 p-12 text-center">
            <span class="inline-flex w-16 h-16 rounded-2xl bg-gray-50 text-gray-300 grid place-items-center mb-4"><x-icon name="homework" class="w-8 h-8" /></span>
            <p class="text-gray-400 font-black">لا توجد واجبات حالياً</p>
            <p class="text-gray-300 text-xs font-medium mt-1.5">تظهر هنا الواجبات المنشورة لصف {{ $child->name }}</p>
        </div>
    @endforelse
</div>
@endsection
