@props(['pages', 'statuses' => []])

@php
    $toArabicDigits = static fn (int $number): string => strtr((string) $number, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);
@endphp

<div class="space-y-4" data-preview-viewer data-preview-statuses="{{ json_encode($statuses, JSON_UNESCAPED_UNICODE) }}">
    <div class="status-legend">
        <span class="text-sm font-bold text-gray-700">⌨️ دليل الألوان والمفاتيح:</span>
        <span class="legend-item"><span class="legend-dot status-correct"></span> <span class="shortcut-key">1</span> صحيحة</span>
        <span class="legend-item"><span class="legend-dot status-incorrect"></span> <span class="shortcut-key">2</span> خطأ نطق</span>
        <span class="legend-item"><span class="legend-dot status-hesitation"></span> <span class="shortcut-key">3</span> تردد</span>
        <span class="legend-item"><span class="legend-dot status-tajweed_error"></span> <span class="shortcut-key">4</span> خطأ تجويد</span>
        <span class="legend-item"><span class="legend-dot status-added"></span> <span class="shortcut-key">5</span> زيادة</span>
        <span class="legend-item"><span class="legend-dot status-forgotten"></span> <span class="shortcut-key">6</span> نسيان</span>
    </div>

    @if($pages->count() > 1)
        <div data-preview-nav class="sticky top-0 z-10 bg-white/95 backdrop-blur rounded-2xl shadow-sm border border-gray-200 px-4 py-3">
            <div class="flex items-center justify-between gap-3">
                <button type="button" data-preview-prev onclick="tasmeePreviewStep(-1)"
                        class="px-4 py-2 rounded-xl bg-emerald-50 text-emerald-700 hover:bg-emerald-100 font-bold text-sm transition disabled:opacity-40 disabled:cursor-not-allowed">
                    → السابقة
                </button>
                <div class="text-center">
                    <div class="text-sm font-bold text-gray-700">
                        صفحة التسميع <span data-preview-current class="text-emerald-700">١</span> من <span data-preview-total>{{ $toArabicDigits($pages->count()) }}</span>
                    </div>
                    <div class="text-[11px] text-gray-400">صفحة المصحف <span data-preview-mushaf>{{ $toArabicDigits($pages->first()['page']) }}</span></div>
                </div>
                <button type="button" data-preview-next onclick="tasmeePreviewStep(1)"
                        class="px-4 py-2 rounded-xl bg-emerald-50 text-emerald-700 hover:bg-emerald-100 font-bold text-sm transition disabled:opacity-40 disabled:cursor-not-allowed">
                    التالية ←
                </button>
            </div>
            <div class="flex flex-wrap justify-center gap-1.5 mt-3">
                @foreach($pages as $i => $pageData)
                    <button type="button" data-preview-page-target="{{ $i }}" onclick="tasmeePreviewGoTo({{ $i }})"
                            class="w-8 h-8 rounded-lg text-xs font-bold transition {{ $i === 0 ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-emerald-50' }}">
                        {{ $toArabicDigits($pageData['page']) }}
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    <div class="space-y-6">
        @foreach($pages as $i => $pageData)
            <div data-preview-step="{{ $i }}" data-page="{{ $pageData['page'] }}" class="{{ $i === 0 ? '' : 'hidden' }}">
                <x-quran-review-page
                    :page="$pageData['page']"
                    :ayahs="$pageData['ayahs']"
                    :surah-starts="$pageData['surahStarts']"
                    interactive
                    word-handler="tasmeePreviewWord" />
            </div>
        @endforeach
    </div>

    <div class="review-stats">
        <div class="stat-card">
            <div class="text-gray-500 text-xs font-medium">الإجمالي</div>
            <div class="stat-value text-gray-700" data-preview-stat="total">0</div>
        </div>
        <div class="stat-card">
            <div class="text-gray-500 text-xs font-medium">✅ صحيحة</div>
            <div class="stat-value text-emerald-600" data-preview-stat="correct">0</div>
        </div>
        <div class="stat-card">
            <div class="text-gray-500 text-xs font-medium">❌ أخطاء النطق</div>
            <div class="stat-value text-red-600" data-preview-stat="incorrect">0</div>
        </div>
        <div class="stat-card">
            <div class="text-gray-500 text-xs font-medium">🟡 تردد</div>
            <div class="stat-value text-yellow-600" data-preview-stat="hesitation">0</div>
        </div>
        <div class="stat-card">
            <div class="text-gray-500 text-xs font-medium">🔵 أخطاء التجويد</div>
            <div class="stat-value text-blue-600" data-preview-stat="tajweed_error">0</div>
        </div>
        <div class="stat-card bg-emerald-50 border-emerald-200">
            <div class="text-emerald-700 text-xs font-bold">🎯 نسبة الإتقان</div>
            <div class="stat-value text-emerald-700" data-preview-stat="mastery">100%</div>
            <div class="mastery-bar mt-2">
                <div class="mastery-fill" data-preview-mastery-bar style="width: 100%"></div>
            </div>
        </div>
    </div>

    <div class="text-center text-sm font-bold text-gray-600">
        التقدير المقترح: <span data-preview-suggestion class="text-emerald-700">ممتاز</span>
    </div>

    <div data-preview-popup class="error-popup hidden" style="position: fixed;">
        <div class="text-sm font-bold text-gray-700 mb-2 pb-2 border-b">اختر نوع الخطأ:</div>
        <div class="grid grid-cols-2 gap-1.5">
            <button type="button" onclick="tasmeePreviewSetStatus('correct')" class="px-3 py-2.5 rounded-xl text-sm font-medium bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 transition">
                ✅ صحيحة
            </button>
            <button type="button" onclick="tasmeePreviewSetStatus('incorrect')" class="px-3 py-2.5 rounded-xl text-sm font-medium bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 transition">
                ❌ خطأ نطق
            </button>
            <button type="button" onclick="tasmeePreviewSetStatus('hesitation')" class="px-3 py-2.5 rounded-xl text-sm font-medium bg-yellow-50 hover:bg-yellow-100 text-yellow-700 border border-yellow-200 transition">
                🟡 تردد
            </button>
            <button type="button" onclick="tasmeePreviewSetStatus('tajweed_error')" class="px-3 py-2.5 rounded-xl text-sm font-medium bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 transition">
                🔵 خطأ تجويد
            </button>
            <button type="button" onclick="tasmeePreviewSetStatus('added')" class="px-3 py-2.5 rounded-xl text-sm font-medium bg-pink-50 hover:bg-pink-100 text-pink-700 border border-pink-200 transition">
                ➕ زيادة
            </button>
            <button type="button" onclick="tasmeePreviewSetStatus('forgotten')" class="px-3 py-2.5 rounded-xl text-sm font-medium bg-orange-50 hover:bg-orange-100 text-orange-700 border border-orange-200 transition">
                ➖ نسيان
            </button>
        </div>
        <button type="button" onclick="tasmeePreviewHidePopup()" class="mt-3 w-full text-center text-xs text-gray-400 hover:text-gray-600 py-1.5 bg-gray-50 rounded-lg transition">إلغاء | Esc</button>
    </div>
</div>
