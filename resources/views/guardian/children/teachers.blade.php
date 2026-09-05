@extends('layouts.app')

@section('title', 'معلمو '.$child->name)

@section('content')
@include('guardian.children.partials.header')

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    @forelse($teachers as $row)
        <div class="bg-white rounded-2xl shadow p-5">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-11 h-11 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">
                    {{ mb_substr($row['teacher']->name, 0, 1) }}
                </div>
                <div>
                    <h3 class="font-bold text-gray-800">{{ $row['teacher']->name }}</h3>
                    <div class="text-xs text-gray-400">{{ $row['subject'] ? 'مدرس مادة '.$row['subject']->name : 'مشرف على الشعبة' }}</div>
                </div>
            </div>
            @if($row['teacher']->specialty)
                <div class="text-sm text-gray-500">التخصص: {{ $row['teacher']->specialty }}</div>
            @endif
        </div>
    @empty
        <div class="col-span-full bg-white rounded-2xl shadow p-8 text-center text-gray-400">
            لا يوجد معلمون مرتبطون بهذا الطفل
        </div>
    @endforelse
</div>
@endsection
