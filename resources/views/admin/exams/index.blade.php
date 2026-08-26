@extends('layouts.app')

@section('title', 'الامتحانات')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.exams.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg inline-block">إنشاء اختبار</a>
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
                    <th class="px-4 py-3 text-right whitespace-nowrap">علامة النجاح</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">عدد الدرجات</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">حذف</th>
                </tr>
            </thead>
            <tbody>
                @forelse($exams as $exam)
                    <tr>
                        <td class="px-4 py-3 border-t font-bold whitespace-nowrap">{{ $exam->title }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $exam->subject?->name }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $exam->classroom?->name }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $exam->section?->name ?? 'كل الشعب' }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $exam->exam_date->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $exam->total_marks }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $exam->pass_marks }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $exam->grades_count }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">
                            <form method="POST" action="{{ route('admin.exams.destroy', $exam) }}" onsubmit="return confirm('هل أنت متأكد؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline text-sm">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-6 text-center text-gray-500">لا توجد امتحانات</td>
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
