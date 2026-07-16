@extends('layouts.app')

@section('title', 'إحصائيات مراجعة القرآن')

@section('content')
<div class="space-y-6">
    <a href="{{ route('admin.quran-review.index') }}" class="text-emerald-700 hover:underline text-sm">&larr; العودة إلى المراجعات</a>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 text-center">
            <div class="text-4xl font-bold text-emerald-600">{{ $totalSessions }}</div>
            <div class="text-gray-500 mt-2">إجمالي المراجعات</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 text-center">
            <div class="text-4xl font-bold text-blue-600">{{ $totalStudents }}</div>
            <div class="text-gray-500 mt-2">عدد الطلاب المراجَعين</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 text-center">
            <div class="text-4xl font-bold {{ $avgMastery >= 80 ? 'text-green-600' : 'text-yellow-600' }}">{{ $avgMastery }}%</div>
            <div class="text-gray-500 mt-2">متوسط الإتقان العام</div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">إحصائيات الأخطاء حسب النوع</h3>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
            <div class="bg-red-50 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-red-600">{{ $errorTotals['incorrect'] }}</div>
                <div class="text-sm text-red-500 mt-1">أخطاء النطق</div>
            </div>
            <div class="bg-yellow-50 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-yellow-600">{{ $errorTotals['hesitation'] }}</div>
                <div class="text-sm text-yellow-500 mt-1">تردد</div>
            </div>
            <div class="bg-blue-50 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-blue-600">{{ $errorTotals['tajweed_error'] }}</div>
                <div class="text-sm text-blue-500 mt-1">أخطاء التجويد</div>
            </div>
            <div class="bg-pink-50 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-pink-600">{{ $errorTotals['added'] }}</div>
                <div class="text-sm text-pink-500 mt-1">زيادة</div>
            </div>
            <div class="bg-orange-50 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-orange-600">{{ $errorTotals['forgotten'] }}</div>
                <div class="text-sm text-orange-500 mt-1">نسيان</div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">ترتيب الطلاب حسب الإتقان</h3>
        @if($studentRankings->isEmpty())
            <p class="text-gray-400 text-center py-8">لا توجد بيانات</p>
        @else
            <div class="space-y-2">
                @foreach($studentRankings as $index => $student)
                    <div class="flex items-center gap-3 bg-gray-50 rounded-lg p-3">
                        <span class="text-xl font-bold text-gray-400 w-8 text-center">{{ $index + 1 }}</span>
                        <div class="flex-1 font-medium">{{ $student->name }}</div>
                        <div class="text-sm text-gray-500">{{ $student->session_count }} مراجعة</div>
                        <div class="font-bold {{ $student->avg_mastery >= 90 ? 'text-green-600' : ($student->avg_mastery >= 70 ? 'text-yellow-600' : 'text-red-600') }}">
                            {{ round($student->avg_mastery, 1) }}%
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">السور الأكثر مراجعة</h3>
        @if($topSurahs->isEmpty())
            <p class="text-gray-400 text-center py-8">لا توجد بيانات</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-4 py-2 text-right">السورة</th>
                            <th class="px-4 py-2 text-right">عدد المراجعات</th>
                            <th class="px-4 py-2 text-right">متوسط الإتقان</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($topSurahs as $s)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-medium">{{ $s->name_arabic }}</td>
                                <td class="px-4 py-2">{{ $s->session_count }}</td>
                                <td class="px-4 py-2 font-bold {{ $s->avg_mastery >= 90 ? 'text-green-600' : ($s->avg_mastery >= 70 ? 'text-yellow-600' : 'text-red-600') }}">
                                    {{ round($s->avg_mastery, 1) }}%
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
