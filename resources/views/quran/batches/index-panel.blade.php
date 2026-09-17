@php
    $repeatUrl = $repeatRoute ?? null;
    $planUrl = $planRoute ?? null;
    $khamsaUrl = $khamsaRoute ?? null;
    $journeyUrl = $journeyRoute ?? null;
    $sessionStartUrl = $sessionStartRoute ?? null;
    $reviewCompleteUrl = $reviewCompleteRoute ?? null;
    $reviewCancelUrl = $reviewCancelRoute ?? null;
    $memorizationStoreUrl = $memorizationStoreRoute ?? null;
    $memorizationDestroyUrl = $memorizationDestroyRoute ?? null;
    $planListenUrl = $planListenRoute ?? null;
    $planAudioUrl = $planAudioRoute ?? null;
    $planProgressUrl = $planProgressRoute ?? null;
    $planTestUrl = $planTestRoute ?? null;
    $planCancelUrl = $planCancelRoute ?? null;
    $khamsaReviewUrl = $khamsaReviewRoute ?? null;
    $batchTestUrl = $batchTestRoute ?? null;
    $batchRetakeUrl = $batchRetakeRoute ?? null;
    $retakeCancelUrl = $retakeCancelRoute ?? null;

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
    $placementTestUrl = $placementTestRoute ?? null;
    $reciters = $reciters ?? collect();
    $tasmeeResults = $tasmeeResults ?? \App\Enums\QuranTasmeeResult::cases();
    $minimumPassingPercentage = $minimumPassingPercentage ?? app(\App\Services\QuranSettingsService::class)->minimumPassingPercentage();
@endphp

<div class="max-w-6xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-2">دفعات الحفظ</h1>
    <p class="text-sm text-gray-500 mb-6">
        المركز الموحّد للقرآن: كل جزأين = دفعة (1–2، 3–4، ...، 29–30)، ودورة الدفعة كاملة بالترتيب
        (التسميع مع المعلم ← مراجعة 5 ← الاستماع والاختبار) في صفحة واحدة.
        لا تُفتح الدفعة التالية إلا باجتياز اختبار الدفعة الحالية وفق حد النجاح
        <span class="font-bold text-emerald-700">{{ rtrim(rtrim(number_format($minimumPassingPercentage, 2, '.', ''), '0'), '.') }}%</span>.
    </p>

    @if (session('success'))
        <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm font-bold">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm font-bold">{{ $errors->first() }}</div>
    @endif

    <form method="GET" action="{{ $indexRoute }}" class="bg-white rounded-2xl shadow p-4 mb-6 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">الطالب</label>
            <select name="student_id" class="rounded-lg border-gray-300 text-sm">
                <option value="">كل الطلاب</option>
                @foreach ($students as $student)
                    <option value="{{ $student->id }}" @selected($selectedStudent && $selectedStudent->id === $student->id)>{{ $student->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">الحالة</label>
            <select name="status" class="rounded-lg border-gray-300 text-sm">
                <option value="">كل الحالات</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">تصفية</button>
    </form>

    @include('quran.batches.cycle', [
        'selectedStudent' => $selectedStudent,
        'states' => $states,
        'currentBatch' => $currentBatch,
        'plan' => $plan,
        'review' => $review,
        'retakeReview' => $retakeReview,
        'failedJuz' => $failedJuz,
        'testScopeJuz' => $testScopeJuz,
        'listeningItems' => $listeningItems,
        'memorizedJuz' => $memorizedJuz,
        'listeningSessions' => $listeningSessions,
        'timeline' => $timeline,
        'memorizationProgress' => $memorizationProgress,
        'placementTestAllowed' => $placementTestAllowed,
        'cycleBlockedReason' => $cycleBlockedReason,
        'placementTestUrl' => $placementTestUrl,
        'reciters' => $reciters,
        'tasmeeResults' => $tasmeeResults,
        'minimumPassingPercentage' => $minimumPassingPercentage,
        'indexRoute' => $indexRoute,
        'repeatUrl' => $repeatUrl,
        'planUrl' => $planUrl,
        'khamsaUrl' => $khamsaUrl,
        'journeyUrl' => $journeyUrl,
        'sessionStartUrl' => $sessionStartUrl,
        'reviewCompleteUrl' => $reviewCompleteUrl,
        'reviewCancelUrl' => $reviewCancelUrl,
        'retakeCancelUrl' => $retakeCancelUrl,
        'memorizationStoreUrl' => $memorizationStoreUrl,
        'memorizationDestroyUrl' => $memorizationDestroyUrl,
        'planListenUrl' => $planListenUrl,
        'planAudioUrl' => $planAudioUrl,
        'planProgressUrl' => $planProgressUrl,
        'planTestUrl' => $planTestUrl,
        'planCancelUrl' => $planCancelUrl,
        'khamsaReviewUrl' => $khamsaReviewUrl,
        'batchTestUrl' => $batchTestUrl,
        'batchRetakeUrl' => $batchRetakeUrl,
    ])

    <div class="bg-white rounded-2xl shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs">
                <tr>
                    <th class="text-right px-4 py-3">الطالب</th>
                    <th class="text-right px-4 py-3">الدفعة</th>
                    <th class="text-right px-4 py-3">الحالة</th>
                    <th class="text-right px-4 py-3">آخر اختبار</th>
                    <th class="text-right px-4 py-3">الخطة / المراجعة</th>
                    <th class="text-right px-4 py-3">إجراء</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($batches as $batch)
                    <tr>
                        <td class="px-4 py-3 font-bold text-gray-800">{{ $batch->student?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $batch->label() }}</td>
                        <td class="px-4 py-3">
                            <span @class([
                                'px-2 py-0.5 rounded-full text-[11px] font-bold',
                                'bg-emerald-100 text-emerald-800' => $batch->status->value === 'passed',
                                'bg-amber-100 text-amber-800' => in_array($batch->status->value, ['pending_review_5', 'ready_for_test', 'needs_repeat'], true),
                                'bg-sky-100 text-sky-800' => $batch->status->value === 'pending_memorization',
                                'bg-gray-100 text-gray-600' => $batch->status->value === 'locked',
                            ])>{{ $batch->status->label() }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs">
                            @if ($batch->lastTest)
                                {{ rtrim(rtrim(number_format((float) $batch->lastTest->score, 2, '.', ''), '0'), '.') }}%
                                — {{ $batch->lastTest->isPass() ? 'ناجح' : 'يحتاج إعادة' }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs">
                            @if ($batch->plan && $planUrl)
                                <a href="{{ $planUrl($batch->plan) }}" class="text-emerald-700 font-bold hover:underline">خطة الاستماع</a>
                            @endif
                            @if ($batch->review5 && $khamsaUrl)
                                <a href="{{ $khamsaUrl($batch->review5) }}" class="text-emerald-700 font-bold hover:underline ms-2">مراجعة 5</a>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($batch->status->value === 'needs_repeat' && $repeatUrl)
                                <form method="POST" action="{{ $repeatUrl($batch) }}">
                                    @csrf
                                    <button type="submit" class="text-[11px] font-bold bg-amber-600 hover:bg-amber-700 text-white rounded-lg px-2 py-1">مراجعة 5 جديدة</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400">لا توجد دفعات بعد — تبدأ الدفعة الأولى عند حفظ الجزأين 1–2.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $batches->links() }}</div>
</div>
