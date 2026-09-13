@extends('layouts.app')

@section('title', 'دورة شرعية جديدة')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <a href="{{ route('admin.sharia-courses.index') }}" class="text-sm text-emerald-700 hover:text-emerald-800">← الدورات الشرعية</a>
    <h2 class="text-2xl font-extrabold text-gray-800">إنشاء دورة شرعية</h2>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
        @include('admin.sharia-courses._form', ['course' => null, 'teachers' => $teachers, 'statuses' => $statuses])
    </div>
</div>
@endsection
