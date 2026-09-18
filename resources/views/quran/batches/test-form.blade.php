@php
    $batchTestRoute = $batchTestRoute ?? null;
    $batchRetakeRoute = $batchRetakeRoute ?? null;
    $testScopeJuz = $testScopeJuz ?? [];
    $failedJuz = $failedJuz ?? [];
    $retakeReview = $retakeReview ?? null;
    $khamsaRoute = $khamsaRoute ?? null;
    $placementTestRoute = $placementTestRoute ?? null;
    $placementTestAllowed = $placementTestAllowed ?? false;
    $placementTestScope = $placementTestScope ?? [];
    $placementTestJuz = $placementTestJuz ?? [];
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
        @unless ($placementTestAllowed)
            <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                لا يُفتح الاختبار التراكمي قبل إنهاء جميع خمسات الدفعة (الجزأين معاً) — أكمل «مراجعة 5» أولاً.
            </p>
        @endunless
    @endif

    @if ($placementTestAllowed && $placementTestRoute && $currentBatch)
        @php
            $placementScope = $placementTestScope;

            if ($placementScope === []) {
                $placementScope = [[
                    'batch_number' => $currentBatch->batch_number,
                    'from_juz' => $currentBatch->from_juz,
                    'to_juz' => $currentBatch->to_juz,
                    'batch' => $currentBatch,
                ]];
            }

            $placementJuz = $placementTestJuz !== []
                ? array_values($placementTestJuz)
                : collect($placementScope)->flatMap(fn (array $entry) => [$entry['from_juz'], $entry['to_juz']])->unique()->sort()->values()->all();

            $placementJuzLabel = count($placementJuz) > 1 && $placementJuz === range($placementJuz[0], end($placementJuz))
                ? $placementJuz[0].'–'.end($placementJuz)
                : implode('، ', $placementJuz);
        @endphp
        <div id="placement-test" class="mt-5 pt-5 border-t border-gray-100 scroll-mt-6">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-sky-100 text-sky-800 text-xs font-black flex items-center justify-center">↯</span>
                    <h4 class="font-bold text-gray-700">اختبار مباشر للأجزاء المحفوظة مسبقاً</h4>
                </div>
                <span class="text-[11px] font-bold text-gray-400">حد النجاح {{ $threshold }}%</span>
            </div>
            <p class="text-xs text-gray-500 mb-3">
                الطالب مسجّل بحفظ الأجزاء <b class="text-gray-700">{{ $placementJuzLabel }}</b> ({{ count($placementScope) }} دفعة) من ملفه — يمكن اختباره مباشرة دون إنهاء «مراجعة 5».
                سجّل نتيجة كل جزء: النجاح يثبّت الدفعة، والرسوب في أي جزء يُنشئ «خمسات إعادة رسوب الاختبار» للجزء الراسب فقط.
            </p>

            @error('results') <p class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2 mb-3">{{ $message }}</p> @enderror

            <form method="POST" action="{{ $placementTestRoute }}" class="space-y-3" data-placement-test-form>
                @csrf
                @foreach ($placementScope as $entry)
                    <fieldset class="rounded-xl border border-gray-200 overflow-hidden" data-placement-batch>
                        <legend class="sr-only">الدفعة {{ $entry['batch_number'] }} (الجزآن {{ $entry['from_juz'] }}–{{ $entry['to_juz'] }})</legend>
                        <div class="flex flex-wrap items-center justify-between gap-2 bg-gray-50 px-3 py-2 border-b border-gray-100">
                            <span class="text-xs font-black text-gray-700">الدفعة {{ $entry['batch_number'] }} (الجزآن {{ $entry['from_juz'] }}–{{ $entry['to_juz'] }})</span>
                            <span data-placement-batch-state class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">سيُثبَّت ✓</span>
                        </div>
                        <div class="grid gap-2 p-3 md:grid-cols-2">
                            @foreach (range($entry['from_juz'], $entry['to_juz']) as $juz)
                                @php $juzRange = \App\Support\QuranJuzMap::pageRange($juz); @endphp
                                <div class="rounded-lg border border-gray-200 p-3">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <div class="font-bold text-gray-800 text-sm">الجزء {{ $juz }}</div>
                                            <div class="text-[11px] text-gray-400">صفحات {{ $juzRange['from'] }}–{{ $juzRange['to'] }}</div>
                                        </div>
                                        <div class="flex items-center gap-3 text-sm">
                                            <label class="flex items-center gap-1.5 cursor-pointer">
                                                <input type="radio" name="results[{{ $juz }}]" value="pass" checked
                                                       class="text-emerald-600 focus:ring-emerald-500" data-placement-result="pass">
                                                <span class="font-bold text-emerald-700">ناجح</span>
                                            </label>
                                            <label class="flex items-center gap-1.5 cursor-pointer">
                                                <input type="radio" name="results[{{ $juz }}]" value="fail"
                                                       class="text-red-600 focus:ring-red-500" data-placement-result="fail">
                                                <span class="font-bold text-red-700">يحتاج إعادة</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach

                <textarea name="notes" rows="2" placeholder="ملاحظات عامة على الاختبار المباشر (اختياري)"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>

                <div class="sticky bottom-0 z-10 -mx-5 px-5 py-3 bg-white/95 backdrop-blur border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
                    <div class="text-xs font-bold text-gray-500" data-placement-summary aria-live="polite">
                        {{ count($placementJuz) }} أجزاء — ناجح {{ count($placementJuz) }} / إعادة 0
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" data-placement-all-pass
                                class="text-xs font-bold text-emerald-700 border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 px-3 py-2 rounded-lg">
                            تعليم الكل ناجح
                        </button>
                        <button type="submit" data-placement-submit
                                class="bg-sky-700 hover:bg-sky-800 text-white font-bold px-6 py-2 rounded-xl">
                            تسجيل نتيجة الاختبار المباشر ({{ count($placementJuz) }} أجزاء)
                        </button>
                    </div>
                </div>
            </form>
        </div>

        @push('scripts')
            <script>
                (function () {
                    const form = document.querySelector('[data-placement-test-form]');

                    if (!form) {
                        return;
                    }

                    const summary = form.querySelector('[data-placement-summary]');
                    const allPass = form.querySelector('[data-placement-all-pass]');
                    const submit = form.querySelector('[data-placement-submit]');
                    const results = Array.from(form.querySelectorAll('input[type="radio"][data-placement-result]'));
                    const batches = Array.from(form.querySelectorAll('[data-placement-batch]'));

                    function refresh() {
                        let pass = 0;
                        let fail = 0;

                        results.forEach(function (box) {
                            if (!box.checked) {
                                return;
                            }

                            if (box.value === 'pass') {
                                pass++;
                            } else {
                                fail++;
                            }
                        });

                        if (summary) {
                            summary.textContent = (pass + fail) + ' أجزاء — ناجح ' + pass + ' / إعادة ' + fail;
                        }

                        batches.forEach(function (batch) {
                            const state = batch.querySelector('[data-placement-batch-state]');

                            if (!state) {
                                return;
                            }

                            const hasFail = Array.from(batch.querySelectorAll('input[type="radio"][data-placement-result="fail"]'))
                                .some(function (box) { return box.checked; });

                            state.textContent = hasFail ? 'سيُعاد ↺' : 'سيُثبَّت ✓';
                            state.className = hasFail
                                ? 'text-[11px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-700'
                                : 'text-[11px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800';
                        });
                    }

                    results.forEach(function (box) { box.addEventListener('change', refresh); });

                    if (allPass) {
                        allPass.addEventListener('click', function () {
                            form.querySelectorAll('input[type="radio"][data-placement-result="pass"]')
                                .forEach(function (box) { box.checked = true; });

                            refresh();
                        });
                    }

                    form.addEventListener('submit', function (event) {
                        const failed = Array.from(form.querySelectorAll('input[type="radio"][data-placement-result="fail"]'))
                            .filter(function (box) { return box.checked; })
                            .map(function (box) { return box.name.replace(/[^0-9]/g, ''); })
                            .filter(function (value, index, self) { return self.indexOf(value) === index; });

                        if (failed.length && !window.confirm('سيتم تسجيل رسوب في الأجزاء: ' + failed.join('، ') + ' وإنشاء خمسات إعادة للأجزاء الراسبة. متابعة؟')) {
                            event.preventDefault();

                            return;
                        }

                        if (submit) {
                            submit.disabled = true;
                            submit.textContent = 'جارٍ التسجيل…';
                        }
                    });

                    refresh();
                })();
            </script>
        @endpush
    @endif
</div>
