@extends('layouts.app')

@section('title', 'تقرير الطالب: ' . $student->name)

@section('content')
<div class="space-y-6">
    <a href="{{ route('teacher.quran-review.index') }}" class="text-emerald-700 hover:underline text-sm">&larr; العودة إلى المراجعات</a>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-2">{{ $student->name }}</h2>
        <p class="text-gray-500 text-sm">عدد المراجعات: {{ $sessions->count() }}</p>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <div class="text-3xl font-bold {{ $avgMastery >= 90 ? 'text-green-600' : ($avgMastery >= 70 ? 'text-yellow-600' : 'text-red-600') }}">
                {{ $avgMastery }}%
            </div>
            <div class="text-sm text-gray-500 mt-1">متوسط الإتقان</div>
        </div>
        @foreach($errorStats as $type => $count)
            @if($count > 0)
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
                    <div class="text-3xl font-bold
                        {{ $type === 'incorrect' ? 'text-red-600' : '' }}
                        {{ $type === 'hesitation' ? 'text-yellow-600' : '' }}
                        {{ $type === 'tajweed_error' ? 'text-blue-600' : '' }}
                        {{ $type === 'added' ? 'text-pink-600' : '' }}
                        {{ $type === 'forgotten' ? 'text-orange-600' : '' }}
                    ">{{ $count }}</div>
                    <div class="text-sm text-gray-500 mt-1">
                        {{ $type === 'incorrect' ? 'أخطاء النطق' : '' }}
                        {{ $type === 'hesitation' ? 'تردد' : '' }}
                        {{ $type === 'tajweed_error' ? 'أخطاء التجويد' : '' }}
                        {{ $type === 'added' ? 'زيادة' : '' }}
                        {{ $type === 'forgotten' ? 'نسيان' : '' }}
                    </div>
                </div>
            @endif
        @endforeach
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">سجل المراجعات</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-2 text-right">السورة</th>
                        <th class="px-4 py-2 text-right">الآيات</th>
                        <th class="px-4 py-2 text-right">نسبة الإتقان</th>
                        <th class="px-4 py-2 text-right">التاريخ</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($sessions as $session)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2">{{ $session->surah->name_arabic }}</td>
                            <td class="px-4 py-2">{{ $session->from_ayah }} - {{ $session->to_ayah }}</td>
                            <td class="px-4 py-2">
                                <span class="font-bold {{ $session->mastery_percentage >= 90 ? 'text-green-600' : ($session->mastery_percentage >= 70 ? 'text-yellow-600' : 'text-red-600') }}">
                                    {{ $session->mastery_percentage }}%
                                </span>
                            </td>
                            <td class="px-4 py-2 text-gray-500">{{ $session->date->format('Y-m-d') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($allWords->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">كلمات تحتاج إلى مراجعة</h3>
        <div class="space-y-3 text-right" style="direction: rtl; font-size: 1.3rem; font-family: 'Traditional Arabic', 'Scheherazade New', 'Amiri', serif;">
            @foreach($allWords as $word)
                <div class="flex items-center gap-3 bg-gray-50 rounded-lg p-3">
                    <span class="font-bold text-2xl px-3 py-1 rounded-lg
                        {{ $word->status === 'incorrect' ? 'bg-red-100 text-red-800' : '' }}
                        {{ $word->status === 'hesitation' ? 'bg-yellow-100 text-yellow-800' : '' }}
                        {{ $word->status === 'tajweed_error' ? 'bg-blue-100 text-blue-800' : '' }}
                        {{ $word->status === 'added' ? 'bg-pink-100 text-pink-800' : '' }}
                        {{ $word->status === 'forgotten' ? 'bg-orange-100 text-orange-800' : '' }}
                    ">{{ $word->word_text }}</span>
                    <div class="flex-1">
                        <div class="text-sm text-gray-500">
                            {{ $word->reviewSession->surah->name_arabic }} - آية {{ $word->ayah->ayah_number }}
                        </div>
                        <div class="text-xs text-gray-400">
                            {{ $word->reviewSession->date->format('Y-m-d') }}
                        </div>
                    </div>
                    <span class="text-xs px-2 py-1 rounded-full
                        {{ $word->status === 'incorrect' ? 'bg-red-100 text-red-700' : '' }}
                        {{ $word->status === 'hesitation' ? 'bg-yellow-100 text-yellow-700' : '' }}
                        {{ $word->status === 'tajweed_error' ? 'bg-blue-100 text-blue-700' : '' }}
                        {{ $word->status === 'added' ? 'bg-pink-100 text-pink-700' : '' }}
                        {{ $word->status === 'forgotten' ? 'bg-orange-100 text-orange-700' : '' }}
                    ">
                        {{ $word->status === 'incorrect' ? 'خطأ نطق' : '' }}
                        {{ $word->status === 'hesitation' ? 'تردد' : '' }}
                        {{ $word->status === 'tajweed_error' ? 'خطأ تجويد' : '' }}
                        {{ $word->status === 'added' ? 'زيادة' : '' }}
                        {{ $word->status === 'forgotten' ? 'نسيان' : '' }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <a href="{{ route('teacher.quran-review.create', ['student_id' => $student->id]) }}" class="inline-block bg-emerald-700 text-white px-5 py-2.5 rounded-lg hover:bg-emerald-800 transition">
        مراجعة جديدة لهذا الطالب
    </a>
</div>
@endsection
