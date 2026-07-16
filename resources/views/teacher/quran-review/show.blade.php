@extends('layouts.app')

@section('title', 'نتيجة المراجعة - ' . $session->surah->name_arabic)

@section('content')
<div class="space-y-6">
    <a href="{{ route('teacher.quran-review.index') }}" class="text-emerald-700 hover:underline text-sm">&larr; العودة إلى المراجعات</a>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <div class="text-sm text-gray-500">الطالب</div>
                <div class="font-bold text-gray-800">{{ $session->student->name }}</div>
            </div>
            <div>
                <div class="text-sm text-gray-500">الأستاذ</div>
                <div class="font-bold text-gray-800">{{ $session->teacher->name }}</div>
            </div>
            <div>
                <div class="text-sm text-gray-500">السورة</div>
                <div class="font-bold text-gray-800">{{ $session->surah->name_arabic }}</div>
            </div>
            <div>
                <div class="text-sm text-gray-500">التاريخ</div>
                <div class="font-bold text-gray-800">{{ $session->date->format('Y-m-d') }}</div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <div class="text-3xl font-bold text-emerald-600">{{ $session->mastery_percentage }}%</div>
            <div class="text-sm text-gray-500 mt-1">نسبة الإتقان</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <div class="text-3xl font-bold text-green-600">{{ $session->correct_words }}</div>
            <div class="text-sm text-gray-500 mt-1">كلمات صحيحة</div>
        </div>
        @if($session->incorrect_words > 0)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <div class="text-3xl font-bold text-red-600">{{ $session->incorrect_words }}</div>
            <div class="text-sm text-gray-500 mt-1">أخطاء النطق</div>
        </div>
        @endif
        @if($session->hesitation_words > 0)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <div class="text-3xl font-bold text-yellow-600">{{ $session->hesitation_words }}</div>
            <div class="text-sm text-gray-500 mt-1">تردد</div>
        </div>
        @endif
        @if($session->tajweed_error_words > 0)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <div class="text-3xl font-bold text-blue-600">{{ $session->tajweed_error_words }}</div>
            <div class="text-sm text-gray-500 mt-1">أخطاء التجويد</div>
        </div>
        @endif
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">تفاصيل التسميع</h3>
        <div class="space-y-4 text-right" style="direction: rtl; line-height: 3; font-size: 1.5rem; font-family: 'Traditional Arabic', 'Scheherazade New', 'Amiri', serif;">
            @php $currentAyahId = null; @endphp
            @foreach($session->words as $word)
                @if($currentAyahId !== $word->ayah_id)
                    @if($currentAyahId !== null) </div> @endif
                    <div class="bg-gray-50 rounded-lg p-4">
                        <span class="text-sm text-gray-400 ml-2">{{ $word->ayah->ayah_number }}.</span>
                    @php $currentAyahId = $word->ayah_id; @endphp
                @endif
                <span class="inline-block px-2 py-1 mx-0.5 rounded-lg
                    {{ $word->status === 'correct' ? 'bg-green-100 text-green-800' : '' }}
                    {{ $word->status === 'incorrect' ? 'bg-red-100 text-red-800' : '' }}
                    {{ $word->status === 'hesitation' ? 'bg-yellow-100 text-yellow-800' : '' }}
                    {{ $word->status === 'tajweed_error' ? 'bg-blue-100 text-blue-800' : '' }}
                    {{ $word->status === 'added' ? 'bg-pink-100 text-pink-800' : '' }}
                    {{ $word->status === 'forgotten' ? 'bg-orange-100 text-orange-800' : '' }}
                    {{ $word->status === 'unreviewed' ? 'bg-gray-100 text-gray-500' : '' }}
                ">{{ $word->word_text }}</span>
            @endforeach
            </div>
        </div>
    </div>

    <div class="flex gap-3">
        <a href="{{ route('teacher.quran-review.student-report', $session->student_id) }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-sm">
            تقرير الطالب
        </a>
        <a href="{{ route('teacher.quran-review.create', ['surah_id' => $session->surah_id, 'student_id' => $session->student_id, 'from_ayah' => $session->from_ayah, 'to_ayah' => $session->to_ayah]) }}" class="bg-emerald-700 text-white px-4 py-2 rounded-lg hover:bg-emerald-800 transition text-sm">
            إعادة المراجعة
        </a>
    </div>
</div>
@endsection
