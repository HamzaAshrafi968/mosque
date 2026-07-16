@extends('layouts.app')

@section('title', 'تقرير الطالب: ' . $student->name)

@push('styles')
<style>
    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
    }
</style>
@endpush

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">
    <a href="{{ route('admin.quran-review.index') }}" class="inline-flex items-center gap-1.5 text-emerald-600 hover:text-emerald-800 text-sm font-medium transition animate-fade-in-up">
        ← العودة إلى المراجعات
    </a>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 animate-scale-in">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-600 text-white flex items-center justify-center font-bold text-2xl shadow-lg shadow-emerald-500/20">
                {{ mb_substr($student->name, 0, 1) }}
            </div>
            <div>
                <h2 class="text-2xl font-bold text-gray-800">{{ $student->name }}</h2>
                <p class="text-gray-500 text-sm">عدد جلسات المراجعة: <span class="font-bold text-emerald-600">{{ $sessions->count() }}</span></p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 animate-stagger">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 text-center card-hover">
            <div class="stat-icon bg-gradient-to-br from-emerald-100 to-emerald-50 mx-auto mb-3">🎯</div>
            <div class="text-3xl font-extrabold {{ $avgMastery >= 90 ? 'text-emerald-600' : ($avgMastery >= 70 ? 'text-yellow-600' : 'text-red-600') }}">
                {{ $avgMastery }}%
            </div>
            <div class="text-sm text-gray-500 mt-1 font-medium">متوسط الإتقان</div>
            <div class="mastery-bar mt-3 mx-4">
                <div class="h-full rounded-full {{ $avgMastery >= 90 ? 'bg-emerald-500' : ($avgMastery >= 70 ? 'bg-yellow-500' : 'bg-red-500') }}" style="width: {{ $avgMastery }}%"></div>
            </div>
        </div>
        @foreach($errorStats as $type => $count)
            @if($count > 0)
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 text-center card-hover">
                    <div class="stat-icon mx-auto mb-3
                        {{ $type === 'incorrect' ? 'bg-red-100' : '' }}
                        {{ $type === 'hesitation' ? 'bg-yellow-100' : '' }}
                        {{ $type === 'tajweed_error' ? 'bg-blue-100' : '' }}
                        {{ $type === 'added' ? 'bg-pink-100' : '' }}
                        {{ $type === 'forgotten' ? 'bg-orange-100' : '' }}">
                        @if($type === 'incorrect') ❌
                        @elseif($type === 'hesitation') 🟡
                        @elseif($type === 'tajweed_error') 🔵
                        @elseif($type === 'added') ➕
                        @elseif($type === 'forgotten') ➖
                        @endif
                    </div>
                    <div class="text-3xl font-extrabold
                        {{ $type === 'incorrect' ? 'text-red-600' : '' }}
                        {{ $type === 'hesitation' ? 'text-yellow-600' : '' }}
                        {{ $type === 'tajweed_error' ? 'text-blue-600' : '' }}
                        {{ $type === 'added' ? 'text-pink-600' : '' }}
                        {{ $type === 'forgotten' ? 'text-orange-600' : '' }}
                    ">{{ $count }}</div>
                    <div class="text-sm text-gray-500 mt-1 font-medium">
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

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden animate-fade-in-up">
        <div class="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
            <h3 class="text-lg font-bold text-gray-800">📋 سجل المراجعات</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50/80">
                    <tr>
                        <th class="px-5 py-3 text-right text-xs font-bold text-gray-500 uppercase">📖 السورة</th>
                        <th class="px-5 py-3 text-right text-xs font-bold text-gray-500 uppercase">الآيات</th>
                        <th class="px-5 py-3 text-right text-xs font-bold text-gray-500 uppercase">🎯 نسبة الإتقان</th>
                        <th class="px-5 py-3 text-right text-xs font-bold text-gray-500 uppercase">📅 التاريخ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($sessions as $session)
                        <tr class="hover:bg-emerald-50/20 transition">
                            <td class="px-5 py-3.5">
                                <span class="px-3 py-1 bg-emerald-50 text-emerald-700 rounded-full text-xs font-medium">{{ $session->surah->name_arabic }}</span>
                            </td>
                            <td class="px-5 py-3.5 text-gray-500 text-xs">{{ $session->from_ayah }} — {{ $session->to_ayah }}</td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2">
                                    <div class="w-16 h-2 bg-gray-200 rounded-full overflow-hidden">
                                        <div class="h-full {{ $session->mastery_percentage >= 90 ? 'bg-emerald-500' : ($session->mastery_percentage >= 70 ? 'bg-yellow-500' : 'bg-red-500') }} rounded-full" style="width: {{ $session->mastery_percentage }}%"></div>
                                    </div>
                                    <span class="font-bold text-xs {{ $session->mastery_percentage >= 90 ? 'text-emerald-600' : ($session->mastery_percentage >= 70 ? 'text-yellow-600' : 'text-red-600') }}">
                                        {{ $session->mastery_percentage }}%
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-gray-500 text-xs">{{ $session->date->format('Y-m-d') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($allWords->isNotEmpty())
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 animate-fade-in-up">
        <div class="flex items-center gap-2 mb-5">
            <span class="text-2xl">🔍</span>
            <h3 class="text-lg font-bold text-gray-800">كلمات تحتاج إلى مراجعة</h3>
        </div>
        <div class="space-y-3 text-right quran-font" style="direction: rtl; font-size: 1.3rem;">
            @foreach($allWords as $word)
                <div class="flex items-center gap-3 bg-gray-50/80 rounded-xl p-3.5 hover:bg-gray-100/80 transition">
                    <span class="font-bold text-2xl px-4 py-1.5 rounded-xl
                        {{ $word->status === 'incorrect' ? 'bg-red-100 text-red-800 border border-red-200' : '' }}
                        {{ $word->status === 'hesitation' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' : '' }}
                        {{ $word->status === 'tajweed_error' ? 'bg-blue-100 text-blue-800 border border-blue-200' : '' }}
                        {{ $word->status === 'added' ? 'bg-pink-100 text-pink-800 border border-pink-200' : '' }}
                        {{ $word->status === 'forgotten' ? 'bg-orange-100 text-orange-800 border border-orange-200' : '' }}
                    ">{{ $word->word_text }}</span>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm text-gray-600 font-medium">
                            {{ $word->reviewSession->surah->name_arabic }} — آية {{ $word->ayah->ayah_number }}
                        </div>
                        <div class="text-xs text-gray-400 mt-0.5">
                            {{ $word->reviewSession->date->format('Y-m-d') }}
                        </div>
                    </div>
                    <span class="text-xs px-3 py-1.5 rounded-full font-medium shrink-0
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

    <div class="flex justify-center animate-fade-in-up">
        <a href="{{ route('admin.quran-review.index') }}" class="bg-gradient-to-r from-emerald-600 to-emerald-500 text-white px-8 py-3 rounded-xl hover:from-emerald-700 hover:to-emerald-600 transition font-bold shadow-lg shadow-emerald-600/20 inline-flex items-center gap-2">
            📋 العودة لقائمة المراجعات
        </a>
    </div>
</div>
@endsection
