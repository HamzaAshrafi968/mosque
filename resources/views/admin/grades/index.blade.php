@extends('layouts.app')

@section('title', 'الدرجات')

@section('content')
<div class="bg-white rounded-xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50 text-gray-600 text-sm">
                    <th class="px-4 py-3 text-right whitespace-nowrap">الاختبار</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">المادة</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">الصف</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">التاريخ</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">عدد الدرجات</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">بانتظار الاعتماد</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">معتمدة</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">عرض</th>
                </tr>
            </thead>
            <tbody>
                @forelse($exams as $exam)
                    <tr>
                        <td class="px-4 py-3 border-t font-bold whitespace-nowrap">{{ $exam->title }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $exam->subject?->name }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $exam->classroom?->name }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $exam->exam_date->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $exam->grades_count }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $exam->submitted_grades_count }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $exam->approved_grades_count }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">
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
</div>

<div class="mt-4">
    {{ $exams->links() }}
</div>
@endsection
