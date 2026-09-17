@extends('layouts.app')

@section('title', 'الدورات الشرعية')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div>
        <h2 class="text-2xl font-extrabold text-gray-800">📚 الدورات الشرعية</h2>
        <p class="text-sm text-gray-500 mt-1">الدورات التي تشرف عليها أو تدرّس فيها</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600">
                        <th class="px-4 py-3 text-right">الدورة</th>
                        <th class="px-4 py-3 text-right">المشرف</th>
                        <th class="px-4 py-3 text-right">التواريخ</th>
                        <th class="px-4 py-3 text-right">الطلاب</th>
                        <th class="px-4 py-3 text-right">الدروس</th>
                        <th class="px-4 py-3 text-right">الحالة</th>
                        <th class="px-4 py-3 text-center">إجراء</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($courses as $course)
                    <tr class="border-t">
                        <td class="px-4 py-3 font-bold text-gray-800">
                            <a href="{{ route('teacher.sharia-courses.show', $course) }}" class="hover:text-emerald-700">{{ $course->name }}</a>
                            @if($course->location)<div class="text-xs text-gray-400 font-normal">{{ $course->location }}</div>@endif
                        </td>
                        <td class="px-4 py-3">
                            @forelse($course->supervisors as $supervisor)
                                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 mb-0.5">{{ $supervisor->name }}</span>
                            @empty
                                <span class="text-gray-300">—</span>
                            @endforelse
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500">
                            {{ $course->start_date?->format('Y-m-d') ?? '—' }} ← {{ $course->end_date?->format('Y-m-d') ?? '—' }}
                        </td>
                        <td class="px-4 py-3">{{ $course->students_count }}</td>
                        <td class="px-4 py-3">{{ $course->lessons_count }}</td>
                        <td class="px-4 py-3">
                            <span @class([
                                'px-2 py-0.5 rounded-full text-xs font-bold',
                                'bg-gray-100 text-gray-600' => $course->status->value === 'draft',
                                'bg-emerald-100 text-emerald-800' => $course->status->value === 'active',
                                'bg-sky-100 text-sky-800' => $course->status->value === 'completed',
                                'bg-red-100 text-red-800' => $course->status->value === 'cancelled',
                            ])>{{ $course->status->label() }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <a href="{{ route('teacher.sharia-courses.show', $course) }}" class="text-xs text-emerald-700 hover:underline font-bold">عرض / تحضير</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">لا توجد دورات شرعية ضمن نطاقك</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-100">{{ $courses->links() }}</div>
    </div>
</div>
@endsection
