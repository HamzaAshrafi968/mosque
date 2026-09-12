@extends('layouts.app')

@section('title', 'معلمو '.$child->name)

@section('content')
@include('guardian.children.partials.header')

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    @forelse($teachers as $i => $row)
        @php $teacher = $row['teacher']; @endphp
        <div class="reveal rd-{{ min($i + 1, 6) }} rounded-2xl bg-white border border-pine-950/[0.06] shadow-[0_1px_3px_rgba(5,32,25,0.05)] p-5 card-hover card-hover-ring">
            <div class="flex items-center gap-4">
                <div class="shrink-0 rounded-full p-[2px] bg-gradient-to-br from-gold-200 via-gold-400 to-gold-600">
                    <x-avatar :src="$teacher->avatarUrl()" :name="$teacher->name" size="lg" fallback-class="bg-gradient-to-br from-pine-500 to-pine-800" />
                </div>
                <div class="min-w-0">
                    <h3 class="font-black text-pine-950 truncate">{{ $teacher->name }}</h3>
                    <span class="inline-flex items-center gap-1 mt-1.5 text-[11px] font-bold text-pine-700 bg-pine-100/70 rounded-full px-2.5 py-1">
                        <x-icon name="{{ $row['subject'] ? 'subjects' : 'sections' }}" class="w-3.5 h-3.5" />
                        {{ $row['subject'] ? 'مدرس مادة '.$row['subject']->name : 'مشرف على الشعبة' }}
                    </span>
                </div>
            </div>
            @if($teacher->specialty)
                <div class="mt-4 pt-3 border-t border-dashed border-gray-100 text-xs text-gray-500 font-semibold flex items-center gap-1.5">
                    <x-icon name="grades" class="w-4 h-4 text-gold-500" />
                    التخصص: {{ $teacher->specialty }}
                </div>
            @endif
        </div>
    @empty
        <div class="col-span-full reveal rounded-2xl border border-dashed border-gray-200 p-12 text-center">
            <span class="inline-flex w-16 h-16 rounded-2xl bg-gray-50 text-gray-300 grid place-items-center mb-4"><x-icon name="teachers" class="w-8 h-8" /></span>
            <p class="text-gray-400 font-black">لا يوجد معلمون مرتبطون بهذا الطفل</p>
            <p class="text-gray-300 text-xs font-medium mt-1.5">ستظهر هنا معلمو الشعبة والمواد الدراسية عند ربطهم</p>
        </div>
    @endforelse
</div>
@endsection
