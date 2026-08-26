@extends('layouts.app')

@section('title', 'الصفحة الرئيسية')

@section('content')
<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow p-4 text-center">
        <div class="text-3xl font-bold text-emerald-700">{{ $stats['students_count'] ?? 0 }}</div>
        <div class="text-sm text-gray-600 mt-1">عدد الطلاب</div>
    </div>
    <div class="bg-white rounded-xl shadow p-4 text-center">
        <div class="text-3xl font-bold text-blue-700">{{ $stats['male_students_count'] ?? 0 }}</div>
        <div class="text-sm text-gray-600 mt-1">الطلاب الذكور</div>
    </div>
    <div class="bg-white rounded-xl shadow p-4 text-center">
        <div class="text-3xl font-bold text-pink-700">{{ $stats['female_students_count'] ?? 0 }}</div>
        <div class="text-sm text-gray-600 mt-1">الطالبات</div>
    </div>
    <div class="bg-white rounded-xl shadow p-4 text-center">
        <div class="text-3xl font-bold text-emerald-700">{{ $stats['teachers_count'] ?? 0 }}</div>
        <div class="text-sm text-gray-600 mt-1">عدد المعلمين</div>
    </div>
    <div class="bg-white rounded-xl shadow p-4 text-center">
        <div class="text-3xl font-bold text-emerald-700">{{ $stats['classrooms_count'] ?? 0 }}</div>
        <div class="text-sm text-gray-600 mt-1">عدد الصفوف</div>
    </div>
    <div class="bg-white rounded-xl shadow p-4 text-center">
        <div class="text-3xl font-bold text-emerald-700">{{ $stats['sections_count'] ?? 0 }}</div>
        <div class="text-sm text-gray-600 mt-1">عدد الشعب</div>
    </div>
    <div class="bg-white rounded-xl shadow p-4 text-center">
        <div class="text-3xl font-bold text-green-700">{{ $stats['attendance_present_today'] ?? 0 }}</div>
        <div class="text-sm text-gray-600 mt-1">حضور اليوم</div>
    </div>
    <div class="bg-white rounded-xl shadow p-4 text-center">
        <div class="text-3xl font-bold text-red-700">{{ $stats['attendance_absent_today'] ?? 0 }}</div>
        <div class="text-sm text-gray-600 mt-1">غياب اليوم</div>
    </div>
    <div class="bg-white rounded-xl shadow p-4 text-center">
        <div class="text-3xl font-bold text-yellow-600">{{ $stats['attendance_late_today'] ?? 0 }}</div>
        <div class="text-sm text-gray-600 mt-1">متأخرون اليوم</div>
    </div>
    <div class="bg-white rounded-xl shadow p-4 text-center">
        <div class="text-3xl font-bold text-emerald-700">{{ isset($stats['attendance_rate_today']) ? $stats['attendance_rate_today'] . '%' : '—' }}</div>
        <div class="text-sm text-gray-600 mt-1">نسبة الحضور اليوم</div>
    </div>
    <div class="bg-white rounded-xl shadow p-4 text-center">
        <div class="text-3xl font-bold text-green-700">{{ $stats['exam_passed_count'] ?? 0 }}</div>
        <div class="text-sm text-gray-600 mt-1">ناجحون بالامتحانات</div>
    </div>
    <div class="bg-white rounded-xl shadow p-4 text-center">
        <div class="text-3xl font-bold text-red-700">{{ $stats['exam_failed_count'] ?? 0 }}</div>
        <div class="text-sm text-gray-600 mt-1">راسبون بالامتحانات</div>
    </div>
    <div class="bg-white rounded-xl shadow p-4 text-center">
        <div class="text-3xl font-bold text-blue-700">{{ isset($stats['exam_pass_rate']) ? $stats['exam_pass_rate'] . '%' : '—' }}</div>
        <div class="text-sm text-gray-600 mt-1">نسبة النجاح بالامتحانات</div>
    </div>
    <div class="bg-white rounded-xl shadow p-4 text-center">
        <div class="text-3xl font-bold text-green-700">{{ $stats['homework_passed_count'] ?? 0 }}</div>
        <div class="text-sm text-gray-600 mt-1">ناجحون بالواجبات</div>
    </div>
    <div class="bg-white rounded-xl shadow p-4 text-center">
        <div class="text-3xl font-bold text-red-700">{{ $stats['homework_failed_count'] ?? 0 }}</div>
        <div class="text-sm text-gray-600 mt-1">راسبون بالواجبات</div>
    </div>
    <div class="bg-white rounded-xl shadow p-4 text-center">
        <div class="text-3xl font-bold text-blue-700">{{ isset($stats['homework_pass_rate']) ? $stats['homework_pass_rate'] . '%' : '—' }}</div>
        <div class="text-sm text-gray-600 mt-1">نسبة النجاح بالواجبات</div>
    </div>
</div>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <div class="px-4 py-3 bg-emerald-700 text-white font-bold">آخر الإعلانات</div>
    @if($announcements->isEmpty())
        <div class="px-4 py-6 text-center text-gray-500">لا توجد إعلانات</div>
    @else
        <div class="divide-y">
            @foreach($announcements as $announcement)
                <div class="px-4 py-3">
                    <div class="flex justify-between items-start">
                        <h3 class="font-bold text-gray-800">{{ $announcement->title }}</h3>
                        <span class="text-xs text-gray-500 whitespace-nowrap mr-2">
                            {{ $announcement->author?->name }}
                            @if($announcement->published_at)
                                &bull; {{ $announcement->published_at->format('Y-m-d') }}
                            @endif
                        </span>
                    </div>
                    <p class="text-gray-600 text-sm mt-1">{{ $announcement->body }}</p>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
