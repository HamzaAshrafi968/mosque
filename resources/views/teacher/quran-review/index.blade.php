@extends('layouts.app')

@section('title', 'سجل مراجعات القرآن')

@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-center">
        <div></div>
        <a href="{{ route('teacher.quran-review.create') }}" class="bg-emerald-700 text-white px-5 py-2.5 rounded-lg hover:bg-emerald-800 transition">
            + مراجعة جديدة
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        @if($sessions->isEmpty())
            <div class="p-12 text-center text-gray-400">
                <div class="text-4xl mb-3">📖</div>
                <p class="text-lg">لا توجد مراجعات بعد</p>
                <p class="text-sm mt-1">ابدأ أول مراجعة للقرآن الكريم الآن</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-right text-gray-600">الطالب</th>
                            <th class="px-4 py-3 text-right text-gray-600">السورة</th>
                            <th class="px-4 py-3 text-right text-gray-600">الآيات</th>
                            <th class="px-4 py-3 text-right text-gray-600">نسبة الإتقان</th>
                            <th class="px-4 py-3 text-right text-gray-600">التاريخ</th>
                            <th class="px-4 py-3 text-center text-gray-600">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($sessions as $session)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-3 font-medium">{{ $session->student->name }}</td>
                                <td class="px-4 py-3">{{ $session->surah->name_arabic }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $session->from_ayah }} - {{ $session->to_ayah }}</td>
                                <td class="px-4 py-3">
                                    <span class="font-bold {{ $session->mastery_percentage >= 90 ? 'text-green-600' : ($session->mastery_percentage >= 70 ? 'text-yellow-600' : 'text-red-600') }}">
                                        {{ $session->mastery_percentage }}%
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-500">{{ $session->date->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex justify-center gap-2">
                                        <a href="{{ route('teacher.quran-review.show', $session->id) }}" class="text-emerald-600 hover:text-emerald-800 text-sm">
                                            عرض
                                        </a>
                                        <a href="{{ route('teacher.quran-review.student-report', $session->student_id) }}" class="text-blue-600 hover:text-blue-800 text-sm">
                                            تقرير
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4">
                {{ $sessions->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
