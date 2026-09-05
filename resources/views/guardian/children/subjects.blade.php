@extends('layouts.app')

@section('title', 'مواد '.$child->name)

@section('content')
@include('guardian.children.partials.header')

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    @forelse($subjects as $row)
        <div class="bg-white rounded-2xl shadow p-5">
            <h3 class="font-bold text-gray-800">{{ $row['subject']->name }}</h3>
            <div class="text-sm text-gray-500 mt-2 space-y-1">
                <div>المعلم: <span class="text-gray-700 font-medium">{{ $row['teacher']?->name ?? '—' }}</span></div>
                <div>الشعبة: {{ $row['section']?->name ?? '—' }}</div>
            </div>
        </div>
    @empty
        <div class="col-span-full bg-white rounded-2xl shadow p-8 text-center text-gray-400">
            لا توجد مواد مسجلة لهذا الطفل حالياً
        </div>
    @endforelse
</div>
@endsection
