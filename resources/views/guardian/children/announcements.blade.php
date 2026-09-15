@extends('layouts.app')

@section('title', 'إعلانات '.$child->name)

@section('content')
@include('guardian.children.partials.header')

@php
    $audienceMap = ['all' => 'عام', 'classroom' => 'لصف ' . ($child->classroom?->name ?? ''), 'guardians' => 'لأولياء الأمور'];
    $audienceTint = ['all' => 'bg-pine-100 text-pine-700', 'classroom' => 'bg-gold-100 text-gold-800', 'guardians' => 'bg-sky-100 text-sky-700'];
@endphp

<div class="space-y-4 max-w-4xl">
    @forelse($announcements as $i => $announcement)
        <article class="reveal rd-{{ min($i + 1, 6) }} rounded-2xl bg-white border border-pine-950/[0.06] shadow-[0_1px_3px_rgba(5,32,25,0.05)] overflow-hidden card-hover">
            <div class="flex items-start gap-4 p-5 sm:p-6">
                <div class="hidden sm:flex flex-col items-center gap-1.5 shrink-0 px-2">
                    <span class="w-11 h-11 rounded-2xl bg-gradient-to-br from-emerald-500 to-pine-800 text-gold-300 grid place-items-center">
                        <x-icon name="megaphone" class="w-5 h-5" />
                    </span>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-black text-pine-950">{{ $announcement->title }}</h3>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-black text-emerald-700 {{ $audienceTint[$announcement->audience] ?? 'bg-gray-100 text-gray-500' }} rounded-full px-2.5 py-1">
                                {{ $audienceMap[$announcement->audience] ?? 'عام' }}
                            </span>
                            <span class="inline-flex items-center gap-1 text-[11px] text-gray-400 font-semibold">
                                <x-icon name="clock" class="w-3.5 h-3.5" />
                                {{ $announcement->published_at->format('Y/m/d H:i') }}
                            </span>
                        </div>
                    </div>
                    @if(filled($announcement->body))
                        <p class="text-sm text-gray-600 font-medium leading-relaxed mt-3 whitespace-pre-wrap">{{ $announcement->body }}</p>
                    @endif
                    @if($announcement->hasAudio())
                        <audio controls preload="none" src="{{ $announcement->audioUrl() }}" class="w-full mt-3"></audio>
                    @endif
                    @if($announcement->author)
                        <div class="flex items-center gap-2.5 mt-4 pt-3 border-t border-dashed border-gray-100">
                            <x-avatar :src="$announcement->author->avatarUrl()" :name="$announcement->author->name" size="xs" fallback-class="bg-gradient-to-br from-gold-400 to-gold-700" />
                            <span class="text-xs text-gray-400 font-semibold">بواسطة: {{ $announcement->author->name }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </article>
    @empty
        <div class="reveal rounded-2xl border border-dashed border-gray-200 p-12 text-center">
            <span class="inline-flex w-16 h-16 rounded-2xl bg-gray-50 text-gray-300 grid place-items-center mb-4"><x-icon name="megaphone" class="w-8 h-8" /></span>
            <p class="text-gray-400 font-black">لا توجد إعلانات حالياً</p>
            <p class="text-gray-300 text-xs font-medium mt-1.5">تصلك إعلانات الجامع والصف مباشرة إلى هذه الصفحة</p>
        </div>
    @endforelse
</div>
@endsection
