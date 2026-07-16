@extends('layouts.app')

@section('title', 'الامتحانات')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.exams.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg inline-block">إنشاء اختبار</a>
</div>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <table class="w-full">
        <thead>
            <tr class="bg-gray-50 text-gray-600 text-sm">
                <th class="px-4 py-3 text-right">العنوان</th>
                <th class="px-4 py-3 text-right">المادة</th>
                <th class="px-4 py-3 text-right">الصف</th>
                <th class="px-4 py-3 text-right">الشعبة</th>
                <th class="px-4 py-3 text-right">التاريخ</th>
                <th class="px-4 py-3 text-right">الدرجة الكلية</th>
                <th class="px-4 py-3 text-right">عدد الدرجات</th>
                <th class="px-4 py-3 text-right">حذف</th>
            </tr>
        </thead>
        <tbody>
            @forelse($exams as $exam)
                <tr>
                    <td class="px-4 py-3 border-t font-bold">{{ $exam->title }}</td>
                    <td class="px-4 py-3 border-t">{{ $exam->subject?->name }}</td>
                    <td class="px-4 py-3 border-t">{{ $exam->classroom?->name }}</td>
                    <td class="px-4 py-3 border-t">{{ $exam->section?->name ?? 'كل الشعب' }}</td>
                    <td class="px-4 py-3 border-t">{{ $exam->exam_date->format('Y-m-d') }}</td>
                    <td class="px-4 py-3 border-t">{{ $exam->total_marks }}</td>
                    <td class="px-4 py-3 border-t">{{ $exam->grades_count }}</td>
                    <td class="px-4 py-3 border-t">
                        <form method="POST" action="{{ route('admin.exams.destroy', $exam) }}" onsubmit="return confirm('هل أنت متأكد؟')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline text-sm">حذف</button>
                        </form>
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

<div class="mt-4">
    {{ $exams->links() }}
</div>
@endsection
