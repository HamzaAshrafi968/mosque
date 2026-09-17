@php
    $selectedStudent = $selectedStudent ?? null;
    $states = $states ?? collect();
    $currentBatch = $currentBatch ?? null;
    $plan = $plan ?? null;
    $review = $review ?? null;
    $retakeReview = $retakeReview ?? null;
    $failedJuz = $failedJuz ?? [];
    $testScopeJuz = $testScopeJuz ?? [];
    $listeningItems = $listeningItems ?? collect();
    $memorizedJuz = $memorizedJuz ?? [];
    $listeningSessions = $listeningSessions ?? collect();
    $timeline = $timeline ?? collect();
    $memorizationProgress = $memorizationProgress ?? null;
    $placementTestAllowed = $placementTestAllowed ?? false;
    $cycleBlockedReason = $cycleBlockedReason ?? null;
    $placementTestUrl = $placementTestUrl ?? null;
    $reciters = $reciters ?? collect();
    $tasmeeResults = $tasmeeResults ?? \App\Enums\QuranTasmeeResult::cases();
    $minimumPassingPercentage = $minimumPassingPercentage ?? app(\App\Services\QuranSettingsService::class)->minimumPassingPercentage();

    $indexRoute = $indexRoute ?? null;
    $repeatUrl = $repeatUrl ?? null;
    $planUrl = $planUrl ?? null;
    $khamsaUrl = $khamsaUrl ?? null;
    $journeyUrl = $journeyUrl ?? null;
    $sessionStartUrl = $sessionStartUrl ?? null;
    $reviewCompleteUrl = $reviewCompleteUrl ?? null;
    $reviewCancelUrl = $reviewCancelUrl ?? null;
    $retakeCancelUrl = $retakeCancelUrl ?? null;
    $memorizationStoreUrl = $memorizationStoreUrl ?? null;
    $memorizationDestroyUrl = $memorizationDestroyUrl ?? null;
    $planListenUrl = $planListenUrl ?? null;
    $planAudioUrl = $planAudioUrl ?? null;
    $planProgressUrl = $planProgressUrl ?? null;
    $planTestUrl = $planTestUrl ?? null;
    $planCancelUrl = $planCancelUrl ?? null;
    $khamsaReviewUrl = $khamsaReviewUrl ?? null;
    $batchTestUrl = $batchTestUrl ?? null;
    $batchRetakeUrl = $batchRetakeUrl ?? null;

    $memorizationDone = $currentBatch && $currentBatch->status->value !== 'pending_memorization';
    $testAnchor = $currentBatch && $currentBatch->isReadyForTest() ? '#batch-test' : null;
    $nextBatchOpen = false;

    if ($currentBatch) {
        foreach ($states as $state) {
            if ($state['batch_number'] === $currentBatch->batch_number + 1) {
                $nextBatchOpen = $state['status']->value !== 'locked';
            }
        }
    }
@endphp

@if ($selectedStudent)
    <div class="bg-white rounded-2xl shadow p-5 mb-6">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
            <div>
                <h2 class="font-bold text-gray-800">{{ $selectedStudent->name }}</h2>
                <div class="text-xs text-gray-400 mt-1">
                    @if ($currentBatch)
                        الدفعة الحالية: <span class="font-bold text-emerald-700">{{ $currentBatch->label() }}</span>
                        — {{ $currentBatch->status->label() }}
                    @else
                        أكمل جميع الدفعات
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if ($journeyUrl)
                    <a href="{{ $journeyUrl($selectedStudent) }}" class="text-xs font-bold text-emerald-700 hover:underline">فتح رحلة الطالب</a>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-4">
            <div @class([
                'rounded-xl border p-3',
                'border-emerald-300 bg-emerald-50' => $memorizationDone,
                'border-sky-200 bg-sky-50' => ! $memorizationDone && $memorizationProgress,
                'border-gray-200 bg-gray-50' => ! $memorizationProgress,
            ])>
                <div class="text-[11px] font-bold text-gray-500 mb-1">١. الحفظ الجديد (التسميع)</div>
                @if ($memorizationProgress && $memorizationDone && $memorizationProgress['covered'] === 0)
                    <div class="text-sm font-black text-gray-800">✓ محفوظ مسبقاً</div>
                    <div class="text-[11px] text-gray-500 mt-1">مسجّل من ملف الطالب (الجزآن {{ $currentBatch?->from_juz }}–{{ $currentBatch?->to_juz }})</div>
                @elseif ($memorizationProgress)
                    <div class="text-sm font-black text-gray-800">{{ $memorizationProgress['covered'] }} / {{ $memorizationProgress['total'] }} صفحة</div>
                    <div class="mt-2 h-1.5 rounded-full bg-white/70 overflow-hidden">
                        <div class="h-full bg-emerald-600" style="width: {{ min(100, $memorizationProgress['percentage']) }}%"></div>
                    </div>
                    <div class="text-[11px] text-gray-500 mt-1">
                        {{ $memorizationDone ? '✓ اكتمل حفظ الجزأين' : ($memorizationProgress['next_page'] ? 'الصفحة التالية: '.$memorizationProgress['next_page'] : 'قيد الحفظ') }}
                    </div>
                @else
                    <div class="text-sm font-black text-gray-800">{{ $currentBatch ? 'بانتظار التسميع' : 'اكتمل الحفظ' }}</div>
                @endif
            </div>

            <div @class([
                'rounded-xl border p-3',
                'border-emerald-300 bg-emerald-50' => $review?->isCompleted(),
                'border-amber-300 bg-amber-50' => $review && ! $review->isCompleted(),
                'border-gray-200 bg-gray-50' => ! $review,
            ])>
                <div class="text-[11px] font-bold text-gray-500 mb-1">٢. مراجعة 5</div>
                @php $reviewProgress = $review?->progress(); @endphp
                <div class="text-sm font-black text-gray-800">
                    @if ($review?->isCompleted())
                        ✓ مكتملة ({{ $reviewProgress['completed'] }} / {{ $reviewProgress['total'] }})
                    @elseif ($review)
                        جارية ({{ $reviewProgress['completed'] }} / {{ $reviewProgress['total'] }}) — {{ $reviewProgress['percentage'] }}%
                    @else
                        {{ $memorizationDone ? 'قيد التجهيز' : 'بانتظار اكتمال الحفظ' }}
                    @endif
                </div>
            </div>

            <div @class([
                'rounded-xl border p-3',
                'border-emerald-300 bg-emerald-50' => $currentBatch?->isReadyForTest(),
                'border-red-300 bg-red-50' => $currentBatch?->isNeedsRepeat(),
                'border-gray-200 bg-gray-50' => ! $currentBatch?->isReadyForTest() && ! $currentBatch?->isNeedsRepeat(),
            ])>
                <div class="text-[11px] font-bold text-gray-500 mb-1">٣. الاختبار التراكمي</div>
                <div class="text-sm font-black text-gray-800">
                    @if ($currentBatch?->isNeedsRepeat())
                        <a href="#batch-test" class="text-red-700 hover:underline">
                            راسب — الأجزاء: {{ $failedJuz === [] ? '—' : implode('، ', $failedJuz) }}
                        </a>
                    @elseif ($currentBatch?->isReadyForTest())
                        <a href="#batch-test" class="text-emerald-700 hover:underline">
                            {{ $currentBatch->lastTest ? 'إعادة الاختبار مطلوبة' : 'مطلوب الآن — سجّل النتيجة' }}
                        </a>
                    @elseif ($placementTestAllowed)
                        <a href="#placement-test" class="text-sky-700 hover:underline">اختبار مباشر متاح — الأجزاء المحفوظة</a>
                    @elseif ($currentBatch?->lastTest)
                        {{ rtrim(rtrim(number_format((float) $currentBatch->lastTest->score, 2, '.', ''), '0'), '.') }}%
                    @else
                        بانتظار اكتمال مراجعة 5
                    @endif
                </div>
            </div>

            <div @class([
                'rounded-xl border p-3',
                'border-emerald-300 bg-emerald-50' => $nextBatchOpen,
                'border-gray-200 bg-gray-50' => ! $nextBatchOpen,
            ])>
                <div class="text-[11px] font-bold text-gray-500 mb-1">٤. الدفعة التالية</div>
                <div class="text-sm font-black text-gray-800">
                    @if (! $currentBatch)
                        اكتمل الحفظ
                    @elseif ($nextBatchOpen)
                        🟢 مفتوحة للحفظ
                    @elseif ($currentBatch->batch_number === \App\Models\QuranMemorizationBatch::TOTAL_BATCHES)
                        آخر دفعة — بانتظار الإتمام
                    @else
                        🔒 مقفلة حتى الاجتياز
                    @endif
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
            @foreach ($states as $state)
                <div @class([
                    'rounded-xl border px-3 py-2',
                    'border-emerald-300 bg-emerald-50' => $state['status']->value === 'passed',
                    'border-amber-300 bg-amber-50' => in_array($state['status']->value, ['pending_review_5', 'ready_for_test', 'needs_repeat'], true),
                    'border-sky-200 bg-sky-50' => $state['status']->value === 'pending_memorization',
                    'border-gray-200 bg-gray-50' => $state['status']->value === 'locked',
                ])>
                    <div class="text-xs font-bold text-gray-700">{{ $state['from_juz'] }}–{{ $state['to_juz'] }}</div>
                    <div class="text-[11px] mt-1 text-gray-500">{{ $state['status']->label() }}</div>
                    @if ($state['batch'] && $state['status']->value === 'needs_repeat' && $repeatUrl)
                        <form method="POST" action="{{ $repeatUrl($state['batch']) }}" class="mt-2">
                            @csrf
                            <button type="submit" class="w-full text-[11px] font-bold bg-amber-600 hover:bg-amber-700 text-white rounded-lg px-2 py-1">مراجعة 5 جديدة</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($memorizationStoreUrl && $memorizationDestroyUrl)
            <div class="mt-4">
                @include('quran.khamsa.memorization-panel', [
                    'student' => $selectedStudent,
                    'memorizedJuz' => $memorizedJuz,
                    'storeRoute' => $memorizationStoreUrl,
                    'destroyRoute' => $memorizationDestroyUrl,
                ])
            </div>
        @endif
    </div>

    @if ($cycleBlockedReason)
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="font-bold text-amber-900 text-sm">تعذّر تجهيز دورة الدفعة تلقائياً</div>
                <div class="text-xs text-amber-800 mt-1">{{ $cycleBlockedReason }}</div>
            </div>
            @if ($currentBatch && $repeatUrl)
                <form method="POST" action="{{ $repeatUrl($currentBatch) }}">
                    @csrf
                    <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold px-4 py-2 rounded-lg">توليد مراجعة 5 والخطة الآن</button>
                </form>
            @endif
        </div>
    @endif

    <div class="bg-white rounded-2xl shadow p-5 mb-6">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-800 text-xs font-black flex items-center justify-center">١</span>
                    <h2 class="font-bold text-gray-800">التسميع مع المعلم</h2>
                </div>
                <p class="text-xs text-gray-400 mt-1">سجل جلسات الاستماع والتقييم التفصيلي (كلمة بكلمة) مع المعلم.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($sessionStartUrl)
                    <a href="{{ $sessionStartUrl($selectedStudent) }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-3 py-1.5 rounded-lg">+ التسميع مع المعلم</a>
                @endif
            </div>
        </div>

        @if ($currentBatch && $memorizationProgress && $memorizationDone && $memorizationProgress['covered'] === 0)
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 mb-4 text-xs text-emerald-800">
                ✓ الجزآن {{ $currentBatch->from_juz }}–{{ $currentBatch->to_juz }} مسجّلان محفوظين من ملف الطالب — لا توجد جلسات تسميع «جديد» داخل هذه الدفعة بعد.
                يمكن تسجيل الاختبار المباشر من قسم «الاختبار التراكمي» أدناه.
            </div>
        @elseif ($currentBatch && $memorizationProgress)
            <div class="rounded-xl border border-gray-200 p-3 mb-4">
                <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500 mb-2">
                    <span>
                        تقدم الدفعة: <b class="text-gray-700">{{ $memorizationProgress['covered'] }} من {{ $memorizationProgress['total'] }} صفحة</b>
                        — المتبقي: <b class="text-amber-700">{{ $memorizationProgress['remaining'] }}</b>
                    </span>
                    <span class="font-black text-emerald-700">{{ $memorizationProgress['percentage'] }}%</span>
                </div>
                <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden">
                    <div class="h-full bg-emerald-600" style="width: {{ min(100, $memorizationProgress['percentage']) }}%"></div>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2 text-[11px] text-gray-400 mt-1">
                    <span>نطاق الدفعة: صفحات {{ $memorizationProgress['from'] }}–{{ $memorizationProgress['to'] }}</span>
                    @if ($memorizationProgress['next_page'] && ! $memorizationDone)
                        <span>الصفحة التالية: <b class="text-gray-600">{{ $memorizationProgress['next_page'] }}</b></span>
                    @endif
                </div>
            </div>

            <div class="space-y-3 mb-4">
                @foreach ($memorizationProgress['juz'] as $juzRow)
                    <div class="rounded-xl border border-gray-200 p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2 text-xs mb-2">
                            <span class="font-bold text-gray-700">الجزء {{ $juzRow['juz'] }}</span>
                            <span class="text-gray-500">{{ $juzRow['covered'] }} / {{ $juzRow['total'] }} صفحة — {{ $juzRow['percentage'] }}%</span>
                        </div>
                        <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full bg-emerald-600" style="width: {{ min(100, $juzRow['percentage']) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @elseif ($currentBatch)
            <div class="text-sm text-gray-500 mb-4">لم يبدأ تسميع هذه الدفعة بعد — سجّل أول تسميع «جديد» داخل نطاق الدفعة.</div>
        @else
            <div class="text-sm text-emerald-700 mb-4">ما شاء الله — أكمل الطالب حفظ جميع الأجزاء.</div>
        @endif

        <x-quran-teacher-timeline :items="$timeline" />
    </div>

    @if (! $plan && ! $review)
        <div class="bg-white rounded-2xl shadow p-5 mb-6 text-sm text-gray-500">
            لا توجد دورة جارية لهذه الدفعة بعد — عند اكتمال حفظ الجزأين تُولَّد «مراجعة 5» وخطة الاختبار تلقائياً.
        </div>
    @endif

    @if ($review)
        <div class="mb-6">
            <div class="flex items-center gap-2 mb-2">
                <span class="w-6 h-6 rounded-full bg-amber-100 text-amber-800 text-xs font-black flex items-center justify-center">٢</span>
                <h3 class="font-bold text-gray-700">المراجعة الخمسية (خمسات ما بعد الحفظ)</h3>
            </div>
            @include('quran.khamsa.review-details', [
                'review' => $review,
                'memorizedJuz' => $memorizedJuz,
                'listeningSessions' => $listeningSessions,
                'results' => $tasmeeResults,
                'embedded' => true,
                'indexRoute' => $indexRoute,
                'completeRoute' => $reviewCompleteUrl,
                'cancelRoute' => $reviewCancelUrl,
                'memorizationStoreRoute' => $memorizationStoreUrl,
                'memorizationDestroyRoute' => $memorizationDestroyUrl,
                'reviewRoute' => $khamsaReviewUrl,
                'testUrl' => $testAnchor,
            ])
        </div>
    @endif

    @if ($retakeReview)
        <div class="mb-6">
            <div class="flex items-center gap-2 mb-2">
                <span class="w-6 h-6 rounded-full bg-red-100 text-red-700 text-xs font-black flex items-center justify-center">٢.ب</span>
                <h3 class="font-bold text-red-700">خمسات إعادة رسوب الاختبار</h3>
            </div>
            <p class="text-xs text-gray-500 mb-2">الأجزاء الراسبة: <b class="text-red-700">{{ $failedJuz === [] ? '—' : implode('، ', $failedJuz) }}</b> — أُنشئت تلقائياً من نتيجة الاختبار التراكمي.</p>
            @include('quran.khamsa.review-details', [
                'review' => $retakeReview,
                'memorizedJuz' => $memorizedJuz,
                'listeningSessions' => $listeningSessions,
                'results' => $tasmeeResults,
                'embedded' => true,
                'indexRoute' => $indexRoute,
                'completeRoute' => $reviewCompleteUrl,
                'cancelRoute' => $retakeCancelUrl,
                'memorizationStoreRoute' => $memorizationStoreUrl,
                'memorizationDestroyRoute' => $memorizationDestroyUrl,
                'reviewRoute' => $khamsaReviewUrl,
                'testUrl' => $testAnchor,
            ])
        </div>
    @endif

    @if ($plan)
        <div class="mb-6">
            <div class="flex items-center gap-2 mb-2">
                <span class="w-6 h-6 rounded-full bg-sky-100 text-sky-800 text-xs font-black flex items-center justify-center">٣</span>
                <h3 class="font-bold text-gray-700">خطة الاستماع</h3>
            </div>
            @include('quran.listening.plan-details', [
                'plan' => $plan,
                'memorizedJuz' => $memorizedJuz,
                'listeningItems' => $listeningItems,
                'listeningSessions' => $listeningSessions,
                'reciters' => $reciters,
                'canTest' => false,
                'canListen' => false,
                'embedded' => true,
                'indexRoute' => $indexRoute,
                'listenRoute' => $planListenUrl,
                'audioRoute' => $planAudioUrl,
                'progressRoute' => $planProgressUrl,
                'testRoute' => $planTestUrl,
                'cancelRoute' => $planCancelUrl,
                'khamsaRoute' => $khamsaUrl,
            ])
        </div>
    @endif

    @if ($currentBatch)
        @include('quran.batches.test-form', [
            'currentBatch' => $currentBatch,
            'retakeReview' => $retakeReview,
            'failedJuz' => $failedJuz,
            'testScopeJuz' => $testScopeJuz,
            'batchTestRoute' => $batchTestUrl,
            'batchRetakeRoute' => $batchRetakeUrl,
            'khamsaRoute' => $khamsaUrl,
            'minimumPassingPercentage' => $minimumPassingPercentage,
            'placementTestRoute' => $placementTestUrl,
            'placementTestAllowed' => $placementTestAllowed,
        ])
    @endif
@endif
