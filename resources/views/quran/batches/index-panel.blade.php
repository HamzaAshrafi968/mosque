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
    $timeline = $timeline ?? collect();
    $memorizationProgress = $memorizationProgress ?? null;
    $coveredPages = $memorizationProgress['covered_pages'] ?? [];
    $memorizationDone = $currentBatch && $currentBatch->status->value !== 'pending_memorization';
    $nextBatchOpen = false;

    if ($currentBatch) {
        foreach ($states as $state) {
            if ($state['batch_number'] === $currentBatch->batch_number + 1) {
                $nextBatchOpen = $state['status']->value !== 'locked';
            }
        }
    }
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
                    @if ($memorizationProgress)
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
                    <div class="text-[11px] font-bold text-gray-500 mb-1">٣. الاختبار</div>
                    <div class="text-sm font-black text-gray-800">
                        @if ($currentBatch?->isNeedsRepeat())
                            راسب — يحتاج إعادة
                        @elseif ($currentBatch?->isReadyForTest())
                            مطلوب الآن
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
        </div>

        <div class="bg-white rounded-2xl shadow p-5 mb-6">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-800 text-xs font-black flex items-center justify-center">١</span>
                        <h2 class="font-bold text-gray-800">التسميع مع المعلم</h2>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">سجل موحّد لمتابعة الحفظ والأداء مع المعلم — تسميع حفظ جديد، مراجعة، أو استماع وتقييم تفصيلي من بوابة واحدة.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($sessionStartUrl)
                        <a href="{{ $sessionStartUrl($selectedStudent) }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-3 py-1.5 rounded-lg">+ التسميع مع المعلم</a>
                    @endif
                </div>
            </div>

            @if ($currentBatch && $memorizationProgress)
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
                                <span class="font-bold text-gray-700">الجزء {{ $juzRow['juz'] }} (صفحات {{ $juzRow['from'] }}–{{ $juzRow['to'] }})</span>
                                <span class="text-gray-500">{{ $juzRow['covered'] }} / {{ $juzRow['total'] }} صفحة — {{ $juzRow['percentage'] }}%</span>
                            </div>
                            <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden mb-2">
                                <div class="h-full bg-emerald-600" style="width: {{ min(100, $juzRow['percentage']) }}%"></div>
                            </div>
                            <div class="grid grid-cols-7 sm:grid-cols-10 gap-1">
                                @for ($page = $juzRow['from']; $page <= $juzRow['to']; $page++)
                                    <div @class([
                                        'text-[10px] text-center rounded border py-0.5',
                                        'border-emerald-300 bg-emerald-100 text-emerald-800 font-bold' => in_array($page, $coveredPages, true),
                                        'border-gray-200 bg-gray-50 text-gray-400' => ! in_array($page, $coveredPages, true),
                                    ]) title="صفحة {{ $page }}">{{ $page }}</div>
                                @endfor
                            </div>
                        </div>
                    @endforeach
                </div>
            @elseif ($currentBatch)
                <div class="text-sm text-gray-500 mb-4">لم يبدأ تسميع هذه الدفعة بعد — سجّل أول تسميع «جديد» داخل نطاق الدفعة.</div>
            @else
                <div class="text-sm text-emerald-700 mb-4">ما شاء الله — أكمل الطالب حفظ جميع الأجزاء.</div>
            @endif

            @php
                $timelineFilter = request('timeline_type');
                $timelineFilterUrl = function (?string $value) use ($indexRoute, $selectedStudent) {
                    $query = http_build_query(array_filter([
                        'student_id' => $selectedStudent->id,
                        'status' => request('status'),
                        'timeline_type' => $value,
                    ]));

                    return $query === '' ? $indexRoute : $indexRoute.'?'.$query;
                };
            @endphp
            <div class="flex flex-wrap items-center gap-2 mb-3">
                <a href="{{ $timelineFilterUrl(null) }}" @class([
                    'text-[11px] font-bold px-3 py-1 rounded-full',
                    'bg-emerald-700 text-white' => ! $timelineFilter,
                    'bg-gray-100 text-gray-600 hover:bg-gray-200' => (bool) $timelineFilter,
                ])>الكل</a>
                @foreach (\App\Services\QuranTeacherTimelineService::filters() as $filterValue => $filterLabel)
                    <a href="{{ $timelineFilterUrl($filterValue) }}" @class([
                        'text-[11px] font-bold px-3 py-1 rounded-full',
                        'bg-emerald-700 text-white' => $timelineFilter === $filterValue,
                        'bg-gray-100 text-gray-600 hover:bg-gray-200' => $timelineFilter !== $filterValue,
                    ])>{{ $filterLabel }}</a>
                @endforeach
            </div>

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
                    <h3 class="font-bold text-gray-700">المراجعة الخمسية</h3>
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
                ])
            </div>
        @endif

        @if ($plan)
            <div class="mb-6">
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-6 h-6 rounded-full bg-sky-100 text-sky-800 text-xs font-black flex items-center justify-center">٣</span>
                    <h3 class="font-bold text-gray-700">خطة الاستماع والاختبار</h3>
                </div>
                @include('quran.listening.plan-details', [
                    'plan' => $plan,
                    'memorizedJuz' => $memorizedJuz,
                    'listeningItems' => $listeningItems,
                    'listeningSessions' => $listeningSessions,
                    'reciters' => $reciters,
                    'canTest' => true,
                    'canListen' => true,
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
    @endif

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
