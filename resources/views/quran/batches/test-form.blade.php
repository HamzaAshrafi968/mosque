@php
    $batchTestRoute = $batchTestRoute ?? null;
    $batchRetakeRoute = $batchRetakeRoute ?? null;
    $testScopeJuz = $testScopeJuz ?? [];
    $failedJuz = $failedJuz ?? [];
    $retakeReview = $retakeReview ?? null;
    $khamsaRoute = $khamsaRoute ?? null;
    $threshold = rtrim(rtrim(number_format($minimumPassingPercentage, 2, '.', ''), '0'), '.');
    $lastTest = $currentBatch?->lastTest;
    $isRetest = $lastTest && ! $lastTest->isPass();
    $retakePending = $retakeReview && ! $retakeReview->isCompleted() && ! $retakeReview->isCancelled();
@endphp

<div id="batch-test" class="bg-white rounded-2xl shadow p-5 mb-6 scroll-mt-6">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <div class="flex items-center gap-2">
            <span class="w-6 h-6 rounded-full bg-sky-100 text-sky-800 text-xs font-black flex items-center justify-center">٤</span>
            <h3 class="font-bold text-gray-700">{{ $isRetest ? 'اختبار الإعادة' : 'الاختبار التراكمي' }}</h3>
        </div>
        <span class="text-[11px] font-bold text-gray-400">حد النجاح {{ $threshold }}%</span>
    </div>

    @if (! $currentBatch)
        <p class="text-sm text-emerald-700">ما شاء الله — أكمل الطالب جميع الدفعات.</p>
    @elseif ($currentBatch->isPassed())
        <p class="text-sm text-emerald-700">
            ✓ اجتاز الطالب هذه الدفعة بنسبة
            {{ rtrim(rtrim(number_format((float) $lastTest?->score, 2, '.', ''), '0'), '.') }}%
            — الدفعة التالية مفتوحة للحفظ.
        </p>
    @elseif ($currentBatch->isReadyForTest())
        @if ($lastTest)
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-800 mb-3">
                المحاولة السابقة: <b>{{ rtrim(rtrim(number_format((float) $lastTest->score, 2, '.', ''), '0'), '.') }}%</b>
                — الأجزاء الراسبة: <b>{{ $failedJuz === [] ? '—' : implode('، ', $failedJuz) }}</b>
                — هذه محاولة إعادة، وتشمل الأجزاء: <b>{{ implode('، ', $testScopeJuz) }}</b>.
            </div>
        @endif

        <p class="text-xs text-gray-500 mb-3">
            سجّل نتيجة كل جزء (ناجح / يحتاج إعادة). يشمل الاختبار الأجزاء: <b class="text-gray-700">{{ implode('، ', $testScopeJuz) }}</b>
            — والرسوب في أي جزء يحوّله إلى «خمسات إعادة رسوب الاختبار» ولا تُفتح أجزاء جديدة.
        </p>

        @error('results') <p class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2 mb-3">{{ $message }}</p> @enderror

        <form method="POST" action="{{ $batchTestRoute }}" class="space-y-2">
            @csrf
            @foreach ($testScopeJuz as $juz)
                @php $juzRange = \App\Support\QuranJuzMap::pageRange($juz); @endphp
                <div class="rounded-xl border border-gray-200 p-3 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <div class="font-bold text-gray-800 text-sm">الجزء {{ $juz }}</div>
                        <div class="text-[11px] text-gray-400">صفحات {{ $juzRange['from'] }}–{{ $juzRange['to'] }}</div>
                    </div>
                    <div class="flex items-center gap-4 text-sm">
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" name="results[{{ $juz }}]" value="pass" checked class="text-emerald-600 focus:ring-emerald-500">
                            <span class="font-bold text-emerald-700">ناجح</span>
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" name="results[{{ $juz }}]" value="fail" class="text-red-600 focus:ring-red-500">
                            <span class="font-bold text-red-700">يحتاج إعادة</span>
                        </label>
                    </div>
                </div>
            @endforeach

            <textarea name="notes" rows="2" placeholder="ملاحظات عامة على الاختبار (اختياري)"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>

            <div class="flex justify-end">
                <button class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-6 py-2 rounded-xl">
                    {{ $isRetest ? 'تسجيل نتيجة اختبار الإعادة' : 'تسجيل نتيجة الاختبار' }}
                </button>
            </div>
        </form>
    @elseif ($currentBatch->isNeedsRepeat())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 mb-3">
            رسب الطالب في الأجزاء: <b>{{ $failedJuz === [] ? '—' : implode('، ', $failedJuz) }}</b>
            — الاختبار مقفل حتى إنهاء «خمسات إعادة رسوب الاختبار».
        </div>

        @if ($retakePending)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 mb-3">
                <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                    <span class="font-bold text-amber-900">خمسات الإعادة الجارية: {{ $retakeReview->progress()['completed'] }} / {{ $retakeReview->progress()['total'] }} خمسة</span>
                    @if ($khamsaRoute)
                        <a href="{{ $khamsaRoute($retakeReview) }}" class="font-bold text-amber-800 hover:underline">فتح خمسات الإعادة</a>
                    @endif
                </div>
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-2">
            <form method="POST" action="{{ $batchRetakeRoute }}">
                @csrf
                <input type="hidden" name="mode" value="failed">
                <button class="bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold px-4 py-2 rounded-lg">
                    إعادة الأجزاء الراسبة فقط
                </button>
            </form>
            <form method="POST" action="{{ $batchRetakeRoute }}" onsubmit="return confirm('إنشاء خمسات إعادة لكل أجزاء الدفعة (1–{{ $currentBatch->to_juz }})؟')">
                @csrf
                <input type="hidden" name="mode" value="full">
                <button class="bg-red-700 hover:bg-red-800 text-white text-xs font-bold px-4 py-2 rounded-lg">
                    إعادة الاختبار كاملًا (1–{{ $currentBatch->to_juz }})
                </button>
            </form>
        </div>
    @else
        <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
            لا يُفتح الاختبار التراكمي قبل إنهاء جميع خمسات الدفعة (الجزأين معاً) — أكمل «مراجعة 5» أولاً.
        </p>
    @endif
</div>
