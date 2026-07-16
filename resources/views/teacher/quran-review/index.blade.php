@extends('layouts.app')

@section('title', 'سجل مراجعات القرآن')

@section('content')
<div class="space-y-6 max-w-6xl mx-auto">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 animate-fade-in-up">
        <div>
            <p class="text-gray-500 text-sm">📖 سجل جميع جلسات مراجعة القرآن الكريم</p>
        </div>
        <a href="{{ route('teacher.quran-review.create') }}" class="bg-gradient-to-r from-emerald-600 to-emerald-500 text-white px-6 py-3 rounded-xl hover:from-emerald-700 hover:to-emerald-600 transition shadow-lg shadow-emerald-600/20 font-bold text-sm inline-flex items-center gap-2">
            <span class="text-lg">✚</span> مراجعة جديدة
        </a>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden animate-scale-in">
        @if($sessions->isEmpty())
            <div class="p-16 text-center">
                <div class="w-20 h-20 bg-emerald-50 rounded-2xl flex items-center justify-center mx-auto mb-5">
                    <span class="text-5xl">📖</span>
                </div>
                <p class="text-xl font-bold text-gray-600 mb-2">لا توجد مراجعات بعد</p>
                <p class="text-gray-400 mb-6">ابدأ أول مراجعة للقرآن الكريم الآن</p>
                <a href="{{ route('teacher.quran-review.create') }}" class="inline-flex items-center gap-2 bg-emerald-600 text-white px-6 py-2.5 rounded-xl hover:bg-emerald-700 transition font-medium">
                    ✨ ابدأ المراجعة
                </a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gradient-to-r from-gray-50 to-emerald-50/50 border-b border-gray-200">
                            <th class="px-5 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">👨‍🎓 الطالب</th>
                            <th class="px-5 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">📖 السورة</th>
                            <th class="px-5 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">الآيات</th>
                            <th class="px-5 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">🎯 نسبة الإتقان</th>
                            <th class="px-5 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">📅 التاريخ</th>
                            <th class="px-5 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">⚙️ إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($sessions as $session)
                            <tr class="hover:bg-emerald-50/30 transition-all duration-150">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-xs">
                                            {{ mb_substr($session->student->name, 0, 1) }}
                                        </div>
                                        <span class="font-semibold text-gray-800">{{ $session->student->name }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="px-3 py-1 bg-emerald-50 text-emerald-700 rounded-full text-xs font-medium">{{ $session->surah->name_arabic }}</span>
                                </td>
                                <td class="px-5 py-4 text-gray-500 text-xs font-mono">{{ $session->from_ayah }} — {{ $session->to_ayah }}</td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-2">
                                        <div class="w-20 h-2 bg-gray-200 rounded-full overflow-hidden">
                                            <div class="h-full rounded-full transition-all duration-500
                                                {{ $session->mastery_percentage >= 90 ? 'bg-emerald-500' : ($session->mastery_percentage >= 70 ? 'bg-yellow-500' : 'bg-red-500') }}"
                                                style="width: {{ $session->mastery_percentage }}%"></div>
                                        </div>
                                        <span class="font-bold text-xs {{ $session->mastery_percentage >= 90 ? 'text-emerald-600' : ($session->mastery_percentage >= 70 ? 'text-yellow-600' : 'text-red-600') }}">
                                            {{ $session->mastery_percentage }}%
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-gray-500 text-xs">{{ $session->date->format('Y-m-d') }}</td>
                                <td class="px-5 py-4 text-center">
                                    <div class="flex justify-center gap-1.5">
                                        <a href="{{ route('teacher.quran-review.show', $session->id) }}" class="px-3 py-1.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 rounded-lg text-xs font-medium transition">
                                            👁️ عرض
                                        </a>
                                        <a href="{{ route('teacher.quran-review.student-report', $session->student_id) }}" class="px-3 py-1.5 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg text-xs font-medium transition">
                                            📊 تقرير
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-5 border-t border-gray-100">
                {{ $sessions->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
