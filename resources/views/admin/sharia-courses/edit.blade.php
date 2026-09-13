@extends('layouts.app')

@section('title', 'تعديل دورة شرعية')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <a href="{{ route('admin.sharia-courses.show', $course) }}" class="text-sm text-emerald-700 hover:text-emerald-800">← {{ $course->name }}</a>
    <h2 class="text-2xl font-extrabold text-gray-800">تعديل الدورة الشرعية</h2>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
        @include('admin.sharia-courses._form', ['course' => $course, 'teachers' => $teachers, 'statuses' => $statuses])
    </div>
</div>
@endsection
