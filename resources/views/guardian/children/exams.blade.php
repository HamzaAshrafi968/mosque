@extends('layouts.app')

@section('title', 'امتحانات '.$child->name)

@section('content')
@include('guardian.children.partials.header')

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-2xl shadow p-5">
        <h2 class="text-lg font-bold text-gray-800 mb-4">الامتحانات القادمة</h2>
        @forelse($upcomingExams as $exam)
            <div class="py-2.5 border-b border-gray-50 last:border-0">
                <div class="flex justify-between items-center">
                    <span class="font-bold text-gray-800">{{ $exam->title }}</span>
                    <span class="text-xs px-2 py-1 rounded-lg bg-indigo-100 text-indigo-700">قادم</span>
                </div>
                <div class="text-sm text-gray-500 mt-1">
                    {{ $exam->subject?->name }} — الموعد: {{ $exam->exam_date->format('Y-m-d') }}
                </div>
            </div>
        @empty
            <p class="text-gray-400 text-sm py-4 text-center">لا توجد امتحانات قادمة</p>
        @endforelse
    </div>

    <div class="bg-white rounded-2xl shadow p-5">
        <h2 class="text-lg font-bold text-gray-800 mb-4">نتائج الامتحانات المنشورة</h2>
        @forelse($grades as $row)
            @php $grade = $row['grade']; $exam = $grade->exam; @endphp
            <div class="py-2.5 border-b border-gray-50 last:border-0">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="font-bold text-gray-800">{{ $exam->title }}</span>
                        <div class="text-xs text-gray-400 mt-0.5">{{ $exam->subject?->name }} — {{ $exam->exam_date->format('Y-m-d') }}</div>
                    </div>
                    <div class="text-left">
                        <span class="text-lg font-bold {{ (float) $grade->score >= $exam->pass_marks ? 'text-emerald-600' : 'text-red-500' }}">
                            {{ $grade->score }}/{{ $exam->total_marks }}
                        </span>
                        <div class="text-xs text-gray-500">{{ $row['percentage'] }}٪</div>
                    </div>
                </div>
            </div>
        @empty
            <p class="text-gray-400 text-sm py-4 text-center">لم تُنشر أي نتائج بعد</p>
        @endforelse
    </div>
</div>
@endsection
