@php
    $threshold = rtrim(rtrim(number_format((float) ($minimumPassingPercentage ?? 80), 2, '.', ''), '0'), '.');
    $lastTest = $batch->lastTest;
    $isRetest = $lastTest && ! $lastTest->isPass();
    $failedJuz = $lastTest && ! $lastTest->isPass()
        ? $lastTest->items->where('result', \App\Enums\QuranListeningTestResult::Fail)->pluck('juz')->sort()->values()->all()
        : [];
@endphp

<div id="batch-test" class="bg-white rounded-2xl shadow p-5 scroll-mt-6">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <div class="flex items-center gap-2">
            <span class="w-6 h-6 rounded-full bg-sky-100 text-sky-800 text-xs font-black flex items-center justify-center">٣</span>
            <h3 class="font-bold text-gray-700">اختبار {{ $batch->label() }}</h3>
        </div>
        <span class="text-[11px] font-bold text-gray-400">حد النجاح {{ $threshold }}%</span>
    </div>

    @error('results')
        <p class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2 mb-3">{{ $message }}</p>
    @enderror

    @if ($batch->isReadyForTest())
        @if ($lastTest)
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-800 mb-3">
                المحاولة السابقة: <b>{{ rtrim(rtrim(number_format((float) $lastTest->score, 2, '.', ''), '0'), '.') }}%</b>
                — الأجزاء الراسبة: <b>{{ $failedJuz === [] ? '—' : implode('، ', $failedJuz) }}</b>
                — أعد تسميعها ثم سجّل الاختبار من جديد.
            </div>
        @endif

        <p class="text-xs text-gray-500 mb-3">
            سجّل نتيجة كل جزء (ناجح / يحتاج إعادة). الأجزاء الراسبة ترجع لحالة «يحتاج إعادة تسميع» ولا تُفتح الدفعة التالية حتى النجاح.
        </p>

        <form method="POST" action="{{ $actions['test']($batch) }}" class="space-y-2">
            @csrf
            @foreach ($batch->juzNumbers() as $juz)
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
    @elseif ($batch->isNeedsRepeat())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
            رسب في الأجزاء: <b>{{ $failedJuz === [] ? '—' : implode('، ', $failedJuz) }}</b>
            — الاختبار مقفل حتى إعادة تسميع الأجزاء الراسبة (زر «فتح الصفحات وتسجيل الأخطاء» أعلاه).
        </div>
    @else
        <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
            لا يُفتح الاختبار قبل اكتمال تسميع أجزاء الدفعة الخمسة كاملة.
        </p>
    @endif
</div>
