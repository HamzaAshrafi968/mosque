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
    $summaries = $summaries ?? [];
    $classrooms = $classrooms ?? collect();
    $sections = $sections ?? collect();
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
    $placementTestScope = $placementTestScope ?? [];
    $placementTestJuz = $placementTestJuz ?? [];
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

    <form method="GET" action="{{ $indexRoute }}" class="bg-white rounded-2xl shadow p-4 mb-6 grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">بحث</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="اسم الطالب أو ولي الأمر..."
                   class="w-full rounded-lg border-gray-300 text-sm">
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">الصف</label>
            <select name="classroom_id" data-searchable class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">كل الصفوف</option>
                @foreach ($classrooms as $classroom)
                    <option value="{{ $classroom->id }}" @selected(request('classroom_id') == $classroom->id)>{{ $classroom->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">الشعبة</label>
            <select name="section_id" data-searchable class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">كل الشعب</option>
                @foreach ($sections as $section)
                    <option value="{{ $section->id }}" @selected(request('section_id') == $section->id)>{{ $section->classroom?->name }} - {{ $section->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">حالة الدفعة الحالية</label>
            <select name="status" class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">كل الحالات</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
                <option value="completed" @selected(request('status') === 'completed')>أكمل الحفظ</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">تصفية</button>
            @if (request()->hasAny(['q', 'classroom_id', 'section_id', 'status']))
                <a href="{{ $indexRoute }}" class="text-sm font-bold text-gray-500 hover:underline px-2 py-2">مسح</a>
            @endif
        </div>
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
        'placementTestScope' => $placementTestScope,
        'placementTestJuz' => $placementTestJuz,
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
        <button type="button" data-collapse-toggle aria-expanded="true"
                class="w-full px-4 py-3 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2 cursor-pointer hover:bg-gray-50 transition text-right">
            <div>
                <h2 class="font-bold text-gray-800 text-sm">الطلاب المسجّلون ({{ $students->total() }})</h2>
                @if (config('app.current_study_session_id'))
                    <p class="text-[11px] text-gray-400 mt-1">يُعرض طلاب الدوام المحدد في الأعلى — بدّل الدوام من الأعلى أو اختر «كل الدوامات» لعرض الجميع.</p>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <span class="text-[11px] text-gray-400">اضغط على اسم الطالب لعرض دورة دفعاته كاملة.</span>
                <span data-collapse-chevron class="w-7 h-7 rounded-lg bg-gray-50 border border-gray-200 grid place-items-center text-gray-500 transition-transform shrink-0">
                    <x-icon name="chevron" class="w-4 h-4" />
                </span>
            </div>
        </button>

        <div data-collapse-panel>
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs">
                    <tr>
                        <th class="text-right px-4 py-3">الطالب</th>
                        <th class="text-right px-4 py-3">الدوام</th>
                        <th class="text-right px-4 py-3">الصف / الشعبة</th>
                        <th class="text-right px-4 py-3">الدفعة الحالية</th>
                        <th class="text-right px-4 py-3">الحالة</th>
                        <th class="text-right px-4 py-3">آخر اختبار</th>
                        <th class="text-right px-4 py-3">إجراء</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($students as $student)
                        @php
                            $summary = $summaries[$student->id] ?? null;
                            $openUrl = $indexRoute.'?'.http_build_query(array_merge(request()->query(), ['student_id' => $student->id]));
                        @endphp
                        <tr @class(['bg-emerald-50/50' => $selectedStudent && $selectedStudent->id === $student->id])>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <a href="{{ $openUrl }}" class="font-bold text-emerald-700 hover:underline">{{ $student->name }}</a>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">{{ $student->studySession?->display_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                {{ $student->classroom?->name ?? '—' }}@if ($student->section) / {{ $student->section->name }}@endif
                            </td>
                            <td class="px-4 py-3 text-xs whitespace-nowrap">
                                @if ($summary && $summary['completed'])
                                    <span class="font-bold text-emerald-700">أكمل جميع الدفعات</span>
                                @elseif ($summary)
                                    <span class="font-bold text-gray-800">{{ $summary['label'] }}</span>
                                    <span class="block text-[11px] text-gray-400 mt-0.5">أنجز {{ $summary['passed'] }} من {{ \App\Models\QuranMemorizationBatch::TOTAL_BATCHES }} دفعة</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($summary)
                                    <span @class([
                                        'px-2 py-0.5 rounded-full text-[11px] font-bold',
                                        'bg-emerald-100 text-emerald-800' => $summary['completed'] || $summary['status']->value === 'passed',
                                        'bg-amber-100 text-amber-800' => in_array($summary['status']->value, ['pending_review_5', 'ready_for_test', 'needs_repeat'], true),
                                        'bg-sky-100 text-sky-800' => $summary['status']->value === 'pending_memorization',
                                        'bg-gray-100 text-gray-600' => $summary['status']->value === 'locked',
                                    ])>{{ $summary['completed'] ? 'أكمل الحفظ' : $summary['status']->label() }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">
                                @if ($summary && $summary['last_test'])
                                    {{ rtrim(rtrim(number_format((float) $summary['last_test']->score, 2, '.', ''), '0'), '.') }}%
                                    — {{ $summary['last_test']->isPass() ? 'ناجح' : 'يحتاج إعادة' }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <a href="{{ $openUrl }}" class="text-[11px] font-bold text-emerald-700 hover:underline">فتح الدورة</a>
                                @if ($summary && $summary['batch'] && $summary['status']->value === 'needs_repeat' && $repeatUrl)
                                    <form method="POST" action="{{ $repeatUrl($summary['batch']) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-[11px] font-bold bg-amber-600 hover:bg-amber-700 text-white rounded-lg px-2 py-1 ms-2">مراجعة 5 جديدة</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-400">لا يوجد طلاب مطابقون للتصفية — أضف الطلاب من صفحة «الطلاب» أو امسح التصفية.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>

            @if ($students->hasPages())
                <div class="px-4 py-4 border-t border-gray-100">{{ $students->links() }}</div>
            @endif
        </div>
    </div>
</div>
