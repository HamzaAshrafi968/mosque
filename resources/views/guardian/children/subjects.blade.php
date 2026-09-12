@extends('layouts.app')

@section('title', 'مواد '.$child->name)

@section('content')
@include('guardian.children.partials.header')

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    @forelse($subjects as $i => $row)
        <div class="reveal rd-{{ min($i + 1, 6) }} rounded-2xl bg-white border border-pine-950/[0.06] shadow-[0_1px_3px_rgba(5,32,25,0.05)] p-5 card-hover">
            <div class="flex items-center justify-between gap-3 mb-4">
                <span class="w-11 h-11 rounded-xl bg-gradient-to-br from-emerald-100 to-pine-100 text-pine-700 grid place-items-center">
                    <x-icon name="subjects" class="w-5 h-5" />
                </span>
                <span class="inline-flex items-center gap-1 text-[10px] font-bold text-gray-400 bg-gray-50 rounded-full px-2.5 py-1">
                    <x-icon name="sections" class="w-3 h-3" />{{ $row['section']?->name ?? 'الشعبة' }}
                </span>
            </div>
            <h3 class="font-black text-pine-950">{{ $row['subject']->name }}</h3>
            <div class="mt-3.5 pt-3.5 border-t border-dashed border-gray-100 flex items-center justify-between gap-2">
                @if($row['teacher'])
                    <div class="flex items-center gap-2.5 min-w-0">
                        <x-avatar :src="$row['teacher']->avatarUrl()" :name="$row['teacher']->name" size="sm" fallback-class="bg-gradient-to-br from-pine-500 to-pine-800" />
                        <div class="min-w-0">
                            <div class="text-xs text-gray-400 font-bold">المعلم</div>
                            <div class="text-sm font-bold text-pine-950 truncate">{{ $row['teacher']->name }}</div>
                        </div>
                    </div>
                @else
                    <span class="text-xs text-gray-400 font-semibold">لا يوجد معلم محدد</span>
                @endif
            </div>
        </div>
    @empty
        <div class="col-span-full reveal rounded-2xl border border-dashed border-gray-200 p-12 text-center">
            <span class="inline-flex w-16 h-16 rounded-2xl bg-gray-50 text-gray-300 grid place-items-center mb-4"><x-icon name="subjects" class="w-8 h-8" /></span>
            <p class="text-gray-400 font-black">لا توجد مواد مسجلة لهذا الطفل حالياً</p>
        </div>
    @endforelse
</div>
@endsection
