@extends('layouts.app')

@section('title', 'إعلانات '.$child->name)

@section('content')
@include('guardian.children.partials.header')

<div class="space-y-4">
    @forelse($announcements as $announcement)
        <div class="bg-white rounded-2xl shadow p-5">
            <div class="flex items-center justify-between mb-2">
                <h3 class="font-bold text-gray-800">{{ $announcement->title }}</h3>
                <span class="text-xs text-gray-400">{{ $announcement->published_at->format('Y-m-d H:i') }}</span>
            </div>
            <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ $announcement->body }}</p>
            @if($announcement->author)
                <div class="text-xs text-gray-400 mt-2">بواسطة: {{ $announcement->author->name }}</div>
            @endif
        </div>
    @empty
        <div class="bg-white rounded-2xl shadow p-8 text-center text-gray-400">
            لا توجد إعلانات حالياً
        </div>
    @endforelse
</div>
@endsection
