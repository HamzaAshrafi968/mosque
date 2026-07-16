@extends('layouts.app')

@section('title', 'الدرجات')

@section('content')
<div class="bg-white rounded-xl shadow overflow-hidden">
    <table class="w-full">
        <thead>
            <tr class="bg-gray-50 text-gray-600 text-sm">
                <th class="px-4 py-3 text-right">الاختبار</th>
                <th class="px-4 py-3 text-right">المادة</th>
                <th class="px-4 py-3 text-right">الصف</th>
                <th class="px-4 py-3 text-right">التاريخ</th>
                <th class="px-4 py-3 text-right">عدد الدرجات</th>
                <th class="px-4 py-3 text-right">بانتظار الاعتماد</th>
                <th class="px-4 py-3 text-right">معتمدة</th>
                <th class="px-4 py-3 text-right">عرض</th>
            </tr>
        </thead>
        <tbody>
            @forelse($exams as $exam)
                <tr>
                    <td class="px-4 py-3 border-t font-bold">{{ $exam->title }}</td>
                    <td class="px-4 py-3 border-t">{{ $exam->subject?->name }}</td>
                    <td class="px-4 py-3 border-t">{{ $exam->classroom?->name }}</td>
                    <td class="px-4 py-3 border-t">{{ $exam->exam_date->format('Y-m-d') }}</td>
                    <td class="px-4 py-3 border-t">{{ $exam->grades_count }}</td>
                    <td class="px-4 py-3 border-t">{{ $exam->submitted_grades_count }}</td>
                    <td class="px-4 py-3 border-t">{{ $exam->approved_grades_count }}</td>
                    <td class="px-4 py-3 border-t">
                        <a href="{{ route('admin.grades.show', $exam) }}" class="text-emerald-700 hover:underline font-bold text-sm">عرض</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-4 py-6 text-center text-gray-500">لا توجد اختبارات</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $exams->links() }}
</div>
@endsection
