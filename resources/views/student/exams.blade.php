@extends('layouts.app')

@section('title', 'الامتحانات')

@section('content')
<h1 class="text-2xl font-bold text-gray-800 mb-6">الامتحانات</h1>

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
                    {{ $exam->subject?->name }} — {{ $exam->exam_date->format('Y-m-d') }}
                </div>
            </div>
        @empty
            <p class="text-gray-400 text-sm py-4 text-center">لا توجد امتحانات قادمة</p>
        @endforelse
    </div>

    <div class="bg-white rounded-2xl shadow p-5">
        <h2 class="text-lg font-bold text-gray-800 mb-4">نتائجي المنشورة</h2>
        @forelse($grades as $row)
            @php $grade = $row['grade']; $exam = $grade->exam; $passed = (float) $grade->score >= (int) $exam->pass_marks; @endphp
            <div class="py-2.5 border-b border-gray-50 last:border-0">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="font-bold text-gray-800">{{ $exam->title }}</span>
                        <div class="text-xs text-gray-400 mt-0.5">{{ $exam->subject?->name }} — {{ $exam->exam_date->format('Y-m-d') }}</div>
                    </div>
                    <div class="text-left">
                        <div>
                            <span class="text-lg font-bold {{ $passed ? 'text-emerald-600' : 'text-red-500' }}">{{ $grade->score }}/{{ $exam->total_marks }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-lg {{ $passed ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }} mr-1">{{ $passed ? 'ناجح' : 'راسب' }}</span>
                        </div>
                        <div class="text-xs text-gray-500 mt-0.5">النسبة {{ $row['percentage'] }}٪</div>
                    </div>
                </div>
            </div>
        @empty
            <p class="text-gray-400 text-sm py-4 text-center">لم تُنشر أي نتائج بعد</p>
        @endforelse
    </div>
</div>
@endsection
