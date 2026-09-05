@extends('layouts.app')

@section('title', 'موادي الدراسية')

@section('content')
<h1 class="text-2xl font-bold text-gray-800 mb-6">موادي الدراسية</h1>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    @forelse($subjects as $row)
        <div class="bg-white rounded-2xl shadow p-5">
            <h3 class="font-bold text-gray-800">{{ $row['subject']->name }}</h3>
            <div class="text-sm text-gray-500 mt-2">
                المعلم: <span class="text-gray-700 font-medium">{{ $row['teacher']?->name ?? '—' }}</span>
            </div>
        </div>
    @empty
        <div class="col-span-full bg-white rounded-2xl shadow p-8 text-center text-gray-400">
            لا توجد مواد مسجلة لك حالياً
        </div>
    @endforelse
</div>
@endsection
