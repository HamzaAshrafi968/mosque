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
                    <th class="px-4 py-3 text-right whitespace-nowrap">النوع</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">الحالة</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">المادة</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">الفئة المستهدفة</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">التاريخ</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">الدرجة الكلية</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">الأسئلة</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">المحاولات</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">الدرجات</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">حذف</th>
                </tr>
            </thead>
            <tbody>
                @forelse($exams as $exam)
                    <tr>
                        <td class="px-4 py-3 border-t font-bold whitespace-nowrap">
                            <a href="{{ route('admin.exams.show', $exam) }}" class="text-emerald-700 hover:underline">{{ $exam->title }}</a>
                        </td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $exam->kind?->label() }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">
                            <span class="text-xs px-2 py-0.5 rounded-lg
                                {{ $exam->status === \App\Enums\ExamStatus::Published ? 'bg-emerald-100 text-emerald-700' : ($exam->status === \App\Enums\ExamStatus::Closed ? 'bg-gray-200 text-gray-600' : 'bg-amber-100 text-amber-700') }}">
                                {{ $exam->status?->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $exam->subject?->name }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $exam->targetLabel() }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $exam->exam_date->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $exam->total_marks }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $exam->questions_count }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $exam->attempts_count }}</td>
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
                        <td colspan="11" class="px-4 py-6 text-center text-gray-500">لا توجد امتحانات</td>
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
