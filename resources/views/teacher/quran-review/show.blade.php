@extends('layouts.app')

@section('title', 'نتيجة المراجعة')

@push('styles')
<style>
    .result-card {
        background: white;
        border-radius: 16px;
        padding: 20px;
        text-align: center;
        border: 1px solid #e5e7eb;
        transition: all 0.3s ease;
    }
    .result-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.06); }
    .word-badge {
        display: inline-block;
        padding: 4px 12px;
        margin: 2px;
        border-radius: 10px;
        font-size: 1.4rem;
        font-family: 'Amiri', 'Scheherazade New', 'Traditional Arabic', serif;
        line-height: 2.4;
        transition: all 0.2s ease;
    }
    .word-badge:hover { transform: scale(1.06); }
    .mastery-circle {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
        font-size: 1.8rem;
        font-weight: 800;
    }
</style>
@endpush

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">
    <a href="{{ route('teacher.quran-review.index') }}" class="inline-flex items-center gap-1.5 text-emerald-600 hover:text-emerald-800 text-sm font-medium transition animate-fade-in-up">
        ← العودة إلى المراجعات
    </a>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 animate-scale-in">
        <div class="flex flex-wrap items-center gap-6">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-lg">
                    {{ mb_substr($session->student->name, 0, 1) }}
                </div>
                <div>
                    <div class="text-xs text-gray-400">الطالب</div>
                    <div class="font-bold text-gray-800 text-lg">{{ $session->student->name }}</div>
                </div>
            </div>
            <div class="w-px h-10 bg-gray-200 hidden sm:block"></div>
            <div>
                <div class="text-xs text-gray-400">السورة</div>
                <div class="font-bold text-gray-800">{{ $session->surah->name_arabic }}</div>
            </div>
            <div class="w-px h-10 bg-gray-200 hidden sm:block"></div>
            <div>
                <div class="text-xs text-gray-400">الآيات</div>
                <div class="font-bold text-gray-800">{{ $session->from_ayah }} — {{ $session->to_ayah }}</div>
            </div>
            <div class="w-px h-10 bg-gray-200 hidden sm:block"></div>
            <div>
                <div class="text-xs text-gray-400">الأستاذ</div>
                <div class="font-bold text-gray-800">{{ $session->teacher->name }}</div>
            </div>
            <div class="w-px h-10 bg-gray-200 hidden sm:block"></div>
            <div>
                <div class="text-xs text-gray-400">التاريخ</div>
                <div class="font-bold text-gray-800">{{ $session->date->format('Y-m-d') }}</div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 animate-stagger">
        <div class="result-card bg-gradient-to-br from-emerald-50 to-white border-emerald-200">
            <div class="mastery-circle bg-gradient-to-br from-emerald-500 to-emerald-600 text-white mx-auto mb-2">
                {{ $session->mastery_percentage }}%
            </div>
            <div class="text-sm font-medium text-emerald-700">نسبة الإتقان</div>
        </div>
        <div class="result-card">
            <div class="text-4xl font-extrabold text-emerald-600 mb-1">{{ $session->correct_words }}</div>
            <div class="text-sm text-gray-500">✅ كلمات صحيحة</div>
        </div>
        @if($session->incorrect_words > 0)
        <div class="result-card">
            <div class="text-4xl font-extrabold text-red-600 mb-1">{{ $session->incorrect_words }}</div>
            <div class="text-sm text-gray-500">❌ أخطاء النطق</div>
        </div>
        @endif
        @if($session->hesitation_words > 0)
        <div class="result-card">
            <div class="text-4xl font-extrabold text-yellow-600 mb-1">{{ $session->hesitation_words }}</div>
            <div class="text-sm text-gray-500">🟡 تردد</div>
        </div>
        @endif
        @if($session->tajweed_error_words > 0)
        <div class="result-card">
            <div class="text-4xl font-extrabold text-blue-600 mb-1">{{ $session->tajweed_error_words }}</div>
            <div class="text-sm text-gray-500">🔵 أخطاء التجويد</div>
        </div>
        @endif
        @if($session->added_words > 0)
        <div class="result-card">
            <div class="text-4xl font-extrabold text-pink-600 mb-1">{{ $session->added_words }}</div>
            <div class="text-sm text-gray-500">➕ زيادة</div>
        </div>
        @endif
        @if($session->forgotten_words > 0)
        <div class="result-card">
            <div class="text-4xl font-extrabold text-orange-600 mb-1">{{ $session->forgotten_words }}</div>
            <div class="text-sm text-gray-500">➖ نسيان</div>
        </div>
        @endif
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 animate-fade-in-up">
        <h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-2">
            📝 تفاصيل التسميع
        </h3>
        <div class="space-y-5 text-right quran-font" style="direction: rtl; line-height: 3; font-size: 1.6rem;">
            @php $currentAyahId = null; $ayahOpen = false; @endphp
            @foreach($session->words as $word)
                @if($currentAyahId !== $word->ayah_id)
                    @if($ayahOpen) </div> @endif
                    <div class="bg-gradient-to-r from-gray-50 to-white rounded-xl p-5 border border-gray-100">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="inline-flex items-center justify-center w-8 h-8 bg-emerald-500 text-white rounded-full text-sm font-bold">
                                {{ $word->ayah->ayah_number }}
                            </span>
                            <span class="text-xs text-gray-400">الآية {{ $word->ayah->ayah_number }}</span>
                        </div>
                    @php $currentAyahId = $word->ayah_id; $ayahOpen = true; @endphp
                @endif
                <span class="word-badge
                    {{ $word->status === 'correct' ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : '' }}
                    {{ $word->status === 'incorrect' ? 'bg-red-100 text-red-800 border border-red-200' : '' }}
                    {{ $word->status === 'hesitation' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' : '' }}
                    {{ $word->status === 'tajweed_error' ? 'bg-blue-100 text-blue-800 border border-blue-200' : '' }}
                    {{ $word->status === 'added' ? 'bg-pink-100 text-pink-800 border border-pink-200' : '' }}
                    {{ $word->status === 'forgotten' ? 'bg-orange-100 text-orange-800 border border-orange-200' : '' }}
                    {{ $word->status === 'unreviewed' ? 'bg-gray-50 text-gray-400 border border-gray-100' : '' }}
                ">{{ $word->word_text }}</span>
            @endforeach
            @if($ayahOpen) </div> @endif
            @if($session->words->isEmpty())
                <div class="text-center py-6 text-gray-400">لا توجد كلمات مسجلة لهذه الجلسة</div>
            @endif
        </div>
    </div>

    <div class="flex flex-wrap gap-3 animate-fade-in-up">
        <a href="{{ route('teacher.quran-review.student-report', $session->student_id) }}" class="bg-blue-600 text-white px-5 py-2.5 rounded-xl hover:bg-blue-700 transition font-medium shadow-lg shadow-blue-600/20 text-sm inline-flex items-center gap-2">
            📊 تقرير الطالب
        </a>
        <a href="{{ route('teacher.quran-review.create', ['surah_id' => $session->surah_id, 'student_id' => $session->student_id, 'from_ayah' => $session->from_ayah, 'to_ayah' => $session->to_ayah]) }}" class="bg-gradient-to-r from-emerald-600 to-emerald-500 text-white px-5 py-2.5 rounded-xl hover:from-emerald-700 hover:to-emerald-600 transition font-medium shadow-lg shadow-emerald-600/20 text-sm inline-flex items-center gap-2">
            🔄 إعادة المراجعة
        </a>
    </div>
</div>
@endsection
