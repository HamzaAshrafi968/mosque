@extends('layouts.app')

@section('title', 'ملف الطالب — '.$child->name)

@section('content')
@include('guardian.children.partials.header')

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-1 space-y-6">
        <div class="bg-white rounded-2xl shadow p-5">
            <h3 class="font-bold text-gray-800 mb-3">البيانات الشخصية</h3>
            <dl class="text-sm space-y-2">
                <div class="flex justify-between"><dt class="text-gray-500">الاسم</dt><dd class="font-bold text-gray-800">{{ $child->name }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">الصف</dt><dd>{{ $child->classroom?->name ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">الشعبة</dt><dd>{{ $child->section?->name ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">الجنس</dt><dd>{{ $child->gender === 'male' ? 'ذكر' : 'أنثى' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">تاريخ الميلاد</dt><dd>{{ $child->birth_date?->format('Y-m-d') ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">الحالة</dt><dd>{{ $child->status === 'active' ? 'نشط' : $child->status }}</dd></div>
            </dl>
        </div>

        <div class="bg-white rounded-2xl shadow p-5">
            <h3 class="font-bold text-gray-800 mb-3">معلمو {{ $child->name }}</h3>
            @forelse($teachers->take(5) as $row)
                <div class="text-sm py-1 border-b border-gray-50 last:border-0 flex justify-between">
                    <span class="font-medium">{{ $row['teacher']->name }}</span>
                    <span class="text-gray-500">{{ $row['subject']?->name ?? 'مشرف الشعبة' }}</span>
                </div>
            @empty
                <p class="text-gray-400 text-sm">لا يوجد معلمون مرتبطون بعد</p>
            @endforelse
        </div>
    </div>

    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-2xl shadow p-5">
            <h3 class="font-bold text-gray-800 mb-4">ملخص الحضور</h3>
            <x-attendance-summary :summary="$attendance" />
        </div>

        <div class="bg-white rounded-2xl shadow p-5">
            <h3 class="font-bold text-gray-800 mb-4">الامتحانات القادمة</h3>
            @forelse($upcomingExams as $exam)
                <div class="flex justify-between items-center py-2 border-b border-gray-50 last:border-0 text-sm">
                    <span class="font-medium">{{ $exam->title }} — {{ $exam->subject?->name }}</span>
                    <span class="text-gray-500">{{ $exam->exam_date->format('Y-m-d') }}</span>
                </div>
            @empty
                <p class="text-gray-400 text-sm">لا توجد امتحانات قادمة</p>
            @endforelse
        </div>

        <div class="bg-white rounded-2xl shadow p-5">
            <h3 class="font-bold text-gray-800 mb-4">الواجبات الحالية</h3>
            @forelse($homeworks->take(5) as $row)
                <div class="flex justify-between items-center py-2 border-b border-gray-50 last:border-0 text-sm">
                    <div>
                        <span class="font-medium">{{ $row['homework']->title }}</span>
                        <span class="text-gray-400 text-xs mr-2">ينتهي {{ $row['homework']->due_date->format('Y-m-d') }}</span>
                    </div>
                    @if($row['submission'])
                        <span class="text-xs px-2 py-1 rounded-lg {{ $row['submission']->status === 'graded' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                            {{ $row['submission']->status === 'graded' ? 'تم التصحيح' : ($row['submission']->submitted_at ? 'تم الإرسال' : 'قيد الإنجاز') }}
                        </span>
                    @else
                        <span class="text-xs px-2 py-1 rounded-lg bg-gray-100 text-gray-600">—</span>
                    @endif
                </div>
            @empty
                <p class="text-gray-400 text-sm">لا توجد واجبات</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
