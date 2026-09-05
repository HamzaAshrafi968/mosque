@extends('layouts.app')

@section('title', 'معلموّي')

@section('content')
<h1 class="text-2xl font-bold text-gray-800 mb-6">معلموّي</h1>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    @forelse($teachers as $row)
        <div class="bg-white rounded-2xl shadow p-5">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">
                    {{ mb_substr($row['teacher']->name, 0, 1) }}
                </div>
                <div>
                    <h3 class="font-bold text-gray-800">{{ $row['teacher']->name }}</h3>
                    <div class="text-xs text-gray-400">{{ $row['subject'] ? 'مدرس مادة '.$row['subject']->name : 'مشرف الشعبة' }}</div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-span-full bg-white rounded-2xl shadow p-8 text-center text-gray-400">
            لا يوجد معلمون مرتبطون بك حالياً
        </div>
    @endforelse
</div>
@endsection
