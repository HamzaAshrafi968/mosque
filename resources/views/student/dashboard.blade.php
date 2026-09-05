@extends('layouts.app')

@section('title', 'بوابة الطالب')

@section('content')
<div class="bg-gradient-to-l from-emerald-700 to-teal-700 text-white rounded-2xl shadow-lg p-6 mb-6">
    <h1 class="text-2xl font-bold">مرحباً {{ $student->name }} 👋</h1>
    <p class="text-emerald-100 mt-1">
        {{ $student->classroom?->name ?? 'غير مقيد' }}
        @if($student->section)
            — شعبة {{ $student->section->name }}
        @endif
    </p>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <a href="{{ route('student.attendance') }}" class="bg-white rounded-2xl shadow p-4 text-center hover:shadow-lg transition">
        <div class="text-2xl">📋</div>
        <div class="text-2xl font-bold text-emerald-600 mt-1">{{ $attendance['percentage'] !== null ? $attendance['percentage'].'٪' : '—' }}</div>
        <div class="text-sm text-gray-500">نسبة الحضور</div>
    </a>
    <a href="{{ route('student.exams') }}" class="bg-white rounded-2xl shadow p-4 text-center hover:shadow-lg transition">
        <div class="text-2xl">📝</div>
        <div class="text-2xl font-bold text-indigo-600 mt-1">{{ count($upcomingExams) }}</div>
        <div class="text-sm text-gray-500">امتحانات قادمة</div>
    </a>
    <a href="{{ route('student.homeworks') }}" class="bg-white rounded-2xl shadow p-4 text-center hover:shadow-lg transition">
        <div class="text-2xl">📚</div>
        <div class="text-2xl font-bold text-amber-600 mt-1">
            {{ $homeworks->filter(fn ($row) => $row['submission'] && ! $row['submission']->submitted_at)->count() }}
        </div>
        <div class="text-sm text-gray-500">واجبات معلقة</div>
    </a>
    <a href="{{ route('student.grades') }}" class="bg-white rounded-2xl shadow p-4 text-center hover:shadow-lg transition">
        <div class="text-2xl">🏆</div>
        <div class="text-2xl font-bold text-blue-600 mt-1">{{ count($publishedGrades) }}</div>
        <div class="text-sm text-gray-500">نتائج منشورة</div>
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-2xl shadow p-5">
        <h2 class="font-bold text-gray-800 mb-3">الامتحانات القادمة</h2>
        @forelse($upcomingExams as $exam)
            <div class="flex justify-between py-2 border-b border-gray-50 last:border-0 text-sm">
                <span class="font-medium">{{ $exam->title }}</span>
                <span class="text-gray-500">{{ $exam->subject?->name }} — {{ $exam->exam_date->format('Y-m-d') }}</span>
            </div>
        @empty
            <p class="text-gray-400 text-sm">لا توجد امتحانات قادمة</p>
        @endforelse
    </div>

    <div class="bg-white rounded-2xl shadow p-5">
        <h2 class="font-bold text-gray-800 mb-3">معلموّي</h2>
        <div class="flex flex-wrap gap-2">
            @forelse($teachers as $row)
                <span class="px-3 py-1.5 bg-emerald-50 text-emerald-800 rounded-xl text-sm">{{ $row['teacher']->name }}</span>
            @empty
                <p class="text-gray-400 text-sm">لا يوجد معلمون</p>
            @endforelse
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow p-5 lg:col-span-2">
        <h2 class="font-bold text-gray-800 mb-3">واجباتي</h2>
        @forelse($homeworks->take(6) as $row)
            @php $hw = $row['homework']; $sub = $row['submission']; @endphp
            <div class="flex justify-between items-center py-2 border-b border-gray-50 last:border-0 text-sm">
                <div>
                    <span class="font-medium">{{ $hw->title }}</span>
                    <span class="text-gray-400 text-xs mr-2">ينتهي {{ $hw->due_date->format('Y-m-d') }}</span>
                </div>
                @if($sub && $sub->submitted_at)
                    <span class="text-xs font-bold text-blue-600">تم الإرسال</span>
                @elseif($sub && $sub->status === 'pending')
                    <a href="{{ route('student.homeworks') }}" class="text-xs font-bold text-amber-600">لم يُرسل بعد ←</a>
                @else
                    <span class="text-xs text-gray-400">—</span>
                @endif
            </div>
        @empty
            <p class="text-gray-400 text-sm">لا توجد واجبات</p>
        @endforelse
    </div>
</div>
@endsection
