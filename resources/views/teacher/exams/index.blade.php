@extends('layouts.app')

@section('title', 'الامتحانات')

@section('content')
<div class="mb-4">
    <a href="{{ route('teacher.exams.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">إنشاء اختبار</a>
</div>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50 text-gray-600 text-sm">
                    <th class="px-4 py-3 text-right whitespace-nowrap">العنوان</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">المادة</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">الصف</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">الشعبة</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">التاريخ</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">الدرجة الكلية</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">الدرجات المدخلة</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($exams as $exam)
                    <tr>
                        <td class="px-4 py-3 border-t">{{ $exam->title }}</td>
                        <td class="px-4 py-3 border-t">{{ $exam->subject?->name }}</td>
                        <td class="px-4 py-3 border-t">{{ $exam->classroom?->name }}</td>
                        <td class="px-4 py-3 border-t">{{ $exam->section?->name ?? 'كل الشعب' }}</td>
                        <td class="px-4 py-3 border-t">{{ $exam->exam_date->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 border-t">{{ $exam->total_marks }}</td>
                        <td class="px-4 py-3 border-t">{{ $exam->grades_count }}</td>
                        <td class="px-4 py-3 border-t">
                            <a href="{{ route('teacher.grades.edit', $exam) }}" class="text-emerald-700 hover:underline font-bold whitespace-nowrap">إدخال الدرجات</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-gray-500">لا توجد امتحانات</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4">
        {{ $exams->links() }}
    </div>
</div>
@endsection
