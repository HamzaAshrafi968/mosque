@extends('layouts.app')

@section('title', 'مراجعة الخمسات')

<x-quran-review-styles />
<x-quran-preview-scripts />

@section('content')
<div class="max-w-5xl mx-auto space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-800">مراجعة الخمسات مع تسجيل الأخطاء</h2>
            <p class="text-sm text-gray-500 mt-1">
                {{ $review->student?->name }} — {{ $selectedItems->count() }} خمسة — صفحة {{ $fromPage }} → صفحة {{ $toPage }}
            </p>
        </div>
        <a href="{{ $backRoute }}" class="text-sm text-emerald-700 hover:text-emerald-800">← رجوع للمراجعة</a>
    </div>

    <div class="flex flex-wrap gap-2">
        @foreach($selectedItems as $item)
            <span class="px-2.5 py-1 rounded-lg bg-gold-100 text-gold-800 text-xs font-bold border border-gold-300/60">
                {{ $item->label() }} — صفحات {{ $item->from_page }}–{{ $item->to_page }}
            </span>
        @endforeach
    </div>

    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 text-red-700 text-sm font-medium px-4 py-3 space-y-1">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ $storeRoute }}" data-quran-preview-form class="space-y-5">
        @csrf
        @foreach($selectedItems as $item)
            <input type="hidden" name="items[]" value="{{ $item->id }}">
        @endforeach

        <div data-preview-root>
            <x-quran-review-viewer :pages="$pages" :statuses="$statuses" />
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">التاريخ</label>
                    <input type="date" name="date" value="{{ $date }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">النتيجة</label>
                    <select name="result" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">— تُعبَّأ تلقائياً حسب نسبة الإتقان —</option>
                        @foreach($results as $result)
                            <option value="{{ $result->value }}">{{ $result->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">ملاحظات</label>
                    <input type="text" name="notes" maxlength="2000" placeholder="ملاحظات عامة (اختياري)"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
            <div class="flex justify-end mt-5">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-8 py-2.5 rounded-xl">
                    💾 حفظ المراجعة وإنهاء الخمسات
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
