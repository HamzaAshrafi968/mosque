@extends('layouts.app')

@section('title', 'ملفي القرآني')

@section('content')
@php
    $statusForJuz = function (int $juz) use ($states, $memorizedJuz): string {
        if (in_array($juz, $memorizedJuz, true)) {
            return 'memorized';
        }

        foreach ($states as $state) {
            if ($juz >= $state['from_juz'] && $juz <= $state['to_juz']) {
                return $state['status']->value === 'locked' ? 'locked' : 'pending';
            }
        }

        return 'locked';
    };

    $currentBatch = $currentBatch ?? ($currentState['batch'] ?? null);
    $currentStatus = $currentState['status'] ?? null;
    $review = $review ?? $currentBatch?->review5;
    $retakeReview = $retakeReview ?? $currentBatch?->retakeReview5;
    $failedJuz = $failedJuz ?? [];
    $plan = $plan ?? $currentBatch?->plan;
    $lastTest = $lastTest ?? $currentBatch?->lastTest;
    $reviewProgress = $review?->progress();
    $memorizationDone = $currentStatus && $currentStatus->value !== 'pending_memorization';
    $threshold = rtrim(rtrim(number_format($minimumPassingPercentage, 2, '.', ''), '0'), '.');
    $memorizationProgress = $memorizationProgress ?? null;
    $timeline = $timeline ?? collect();
@endphp

<div class="max-w-5xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-2">ملفي القرآني</h1>
    <p class="text-sm text-gray-500 mb-6">رحلتك في الحفظ والمراجعة والاختبارات — كل شيء في مكان واحد.</p>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow p-5">
            <div class="text-xs text-gray-400 mb-1">المحفوظ</div>
            <div class="text-2xl font-black text-emerald-700">{{ count($memorizedJuz) }} <span class="text-sm font-bold text-gray-500">من 30 جزءاً</span></div>
        </div>
        <div class="bg-white rounded-2xl shadow p-5">
            <div class="text-xs text-gray-400 mb-1">الدفعة الحالية</div>
            <div class="text-lg font-black text-gray-800">{{ $currentBatch?->label() ?? 'أكملت جميع الدفعات' }}</div>
        </div>
        <div class="bg-white rounded-2xl shadow p-5">
            <div class="text-xs text-gray-400 mb-1">حالة الرحلة</div>
            <div class="text-lg font-black text-gold-700">{{ $journey['stage_label'] }}</div>
        </div>
    </div>

    @if ($currentBatch)
        <div class="bg-white rounded-2xl shadow p-5 mb-6">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                <h2 class="font-bold text-gray-800">الدورة الحالية — {{ $currentBatch->label() }}</h2>
                <span @class([
                    'px-3 py-1 rounded-full text-xs font-bold',
                    'bg-sky-100 text-sky-800' => $currentStatus->value === 'pending_memorization',
                    'bg-amber-100 text-amber-800' => in_array($currentStatus->value, ['pending_review_5', 'needs_repeat'], true),
                    'bg-emerald-100 text-emerald-800' => $currentStatus->value === 'ready_for_test',
                ])>{{ $currentStatus->label() }}</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                <div @class([
                    'rounded-xl border p-4',
                    'border-emerald-300 bg-emerald-50' => $memorizationDone,
                    'border-gray-200 bg-gray-50' => ! $memorizationDone,
                ])>
                    <div class="text-xs font-bold text-gray-500 mb-1">1. الحفظ الجديد (التسميع)</div>
                    <div class="text-sm font-black text-gray-800">
                        @if ($memorizationProgress)
                            {{ $memorizationProgress['covered'] }} / {{ $memorizationProgress['total'] }} صفحة ({{ $memorizationProgress['percentage'] }}%)
                        @elseif ($memorizationDone)
                            ✓ اكتمل حفظ الجزأين
                        @else
                            قيد الحفظ — بانتظار إتمام الجزأين
                        @endif
                    </div>
                    @if ($memorizationProgress)
                        <div class="mt-2 h-1.5 rounded-full bg-white/70 overflow-hidden">
                            <div class="h-full bg-emerald-600" style="width: {{ min(100, $memorizationProgress['percentage']) }}%"></div>
                        </div>
                        @if ($memorizationDone)
                            <div class="text-[11px] text-emerald-700 mt-1">✓ اكتمل حفظ الجزأين</div>
                        @elseif ($memorizationProgress['next_page'])
                            <div class="text-[11px] text-gray-500 mt-1">الصفحة التالية: {{ $memorizationProgress['next_page'] }}</div>
                        @endif
                    @endif
                </div>

                <div @class([
                    'rounded-xl border p-4',
                    'border-emerald-300 bg-emerald-50' => $review?->isCompleted(),
                    'border-amber-300 bg-amber-50' => $review && ! $review->isCompleted(),
                    'border-gray-200 bg-gray-50' => ! $review,
                ])>
                    <div class="text-xs font-bold text-gray-500 mb-1">2. مراجعة 5</div>
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
                    'rounded-xl border p-4',
                    'border-emerald-300 bg-emerald-50' => $currentStatus->value === 'ready_for_test',
                    'border-red-300 bg-red-50' => $currentStatus->value === 'needs_repeat',
                    'border-gray-200 bg-gray-50' => ! in_array($currentStatus->value, ['ready_for_test', 'needs_repeat'], true),
                ])>
                    <div class="text-xs font-bold text-gray-500 mb-1">3. الاختبار التراكمي</div>
                    <div class="text-sm font-black text-gray-800">
                        @if ($currentStatus->value === 'needs_repeat')
                            راسب في الأجزاء: {{ $failedJuz === [] ? '—' : implode('، ', $failedJuz) }}
                        @elseif ($currentStatus->value === 'ready_for_test')
                            {{ $lastTest && ! $lastTest->isPass() ? 'إعادة الاختبار مطلوبة' : 'مطلوب الآن' }}
                        @elseif ($lastTest)
                            {{ rtrim(rtrim(number_format((float) $lastTest->score, 2, '.', ''), '0'), '.') }}%
                        @else
                            بانتظار اكتمال مراجعة 5
                        @endif
                    </div>
                    <div class="text-[11px] text-gray-400 mt-1">حد النجاح: {{ $threshold }}%</div>
                </div>
            </div>

            @if ($lastTest)
                <div @class([
                    'rounded-xl px-4 py-3 text-xs font-bold mb-4',
                    'bg-emerald-50 border border-emerald-200 text-emerald-800' => $lastTest->isPass(),
                    'bg-red-50 border border-red-200 text-red-800' => ! $lastTest->isPass(),
                ])>
                    آخر اختبار: {{ rtrim(rtrim(number_format((float) $lastTest->score, 2, '.', ''), '0'), '.') }}%
                    (حد النجاح وقتها {{ rtrim(rtrim(number_format((float) $lastTest->passing_percentage, 2, '.', ''), '0'), '.') }}%)
                    — {{ $lastTest->isPass() ? 'ناجح' : 'يحتاج إعادة' }}
                </div>
            @endif

            <div class="flex flex-wrap gap-2">
                @if ($plan && $plan->isActive())
                    <a href="{{ route('student.quran-listening.show', $plan) }}" class="inline-block bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">فتح صفحة الخطة</a>
                @endif
            </div>

            @if ($memorizationProgress)
                <div class="mt-4 pt-4 border-t border-gray-100 space-y-3">
                    <div class="text-xs font-bold text-gray-500">صفحات دفعتك الحالية — المغطى بتسميع «الحفظ الجديد»</div>
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
            @endif
        </div>
    @else
        <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-5 mb-6 text-emerald-900">
            <div class="font-black mb-1">ما شاء الله! أكملت جميع دفعات الحفظ.</div>
            <div class="text-sm">{{ $journey['is_hafiz'] ? 'أنت الآن من الحفاظ — وفقك الله في البرنامج التأهيلي.' : 'بانتظار تأكيد الإدارة لإتمام الحفظ وبدء البرنامج التأهيلي.' }}</div>
        </div>
    @endif

    @if ($plan)
        <div class="mb-6">
            @include('quran.listening.plan-details', [
                'plan' => $plan,
                'memorizedJuz' => $memorizedJuz,
                'listeningItems' => $listeningItems,
                'listeningSessions' => collect(),
                'reciters' => $reciters,
                'canTest' => false,
                'canListen' => true,
                'showPlayer' => true,
                'embedded' => true,
                'indexRoute' => route('student.quran-profile'),
                'listenRoute' => fn ($item) => route('student.quran-listening.items.listen', $item),
                'audioRoute' => fn ($item) => route('student.quran-listening.items.audio', $item),
                'progressRoute' => fn ($item) => route('student.quran-listening.items.progress', $item),
                'testRoute' => null,
                'cancelRoute' => null,
                'khamsaRoute' => null,
            ])
        </div>
    @endif

    @if ($review)
        <div class="mb-6">
            @include('quran.khamsa.review-details', [
                'review' => $review,
                'memorizedJuz' => $memorizedJuz,
                'listeningSessions' => collect(),
                'results' => [],
                'embedded' => true,
                'readOnly' => true,
                'indexRoute' => route('student.quran-profile'),
                'completeRoute' => null,
                'cancelRoute' => null,
                'memorizationStoreRoute' => null,
                'memorizationDestroyRoute' => null,
            ])
        </div>
    @endif

    @if ($retakeReview)
        <div class="mb-6">
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 mb-3">
                <span class="font-bold">خمسات إعادة رسوب الاختبار:</span>
                رسبت في الأجزاء {{ $failedJuz === [] ? '—' : implode('، ', $failedJuz) }}
                — أكمل خمسات الإعادة، ثم يُفتح اختبار الإعادة على نفس الأجزاء.
            </div>
            @include('quran.khamsa.review-details', [
                'review' => $retakeReview,
                'memorizedJuz' => $memorizedJuz,
                'listeningSessions' => collect(),
                'results' => [],
                'embedded' => true,
                'readOnly' => true,
                'indexRoute' => route('student.quran-profile'),
                'completeRoute' => null,
                'cancelRoute' => null,
                'memorizationStoreRoute' => null,
                'memorizationDestroyRoute' => null,
            ])
        </div>
    @endif

    <div class="bg-white rounded-2xl shadow p-5 mb-6">
        <h2 class="font-bold text-gray-800 mb-4">إنجاز الحفظ — الأجزاء الثلاثون</h2>
        <div class="grid grid-cols-5 md:grid-cols-10 gap-2">
            @foreach (range(1, 30) as $juz)
                @php $status = $statusForJuz($juz); @endphp
                <div @class([
                    'rounded-xl border py-2 text-center',
                    'border-emerald-300 bg-emerald-50' => $status === 'memorized',
                    'border-amber-300 bg-amber-50' => $status === 'pending',
                    'border-gray-200 bg-gray-50' => $status === 'locked',
                ])>
                    <div class="text-sm font-black text-gray-700">{{ $juz }}</div>
                    <div class="text-[11px]">{{ $status === 'memorized' ? '✓' : ($status === 'pending' ? '⏳' : '🔒') }}</div>
                </div>
            @endforeach
        </div>
        <div class="flex flex-wrap gap-4 mt-3 text-[11px] text-gray-500">
            <span>✓ محفوظ</span>
            <span>⏳ قيد الحفظ (دفعتك الحالية)</span>
            <span>🔒 مقفل حتى اجتياز الدفعة السابقة</span>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white rounded-2xl shadow p-5">
            <h2 class="font-bold text-gray-800 mb-3">خطط الاستماع</h2>
            <div class="space-y-2">
                @forelse ($plans as $planItem)
                    @php $progress = $planItem->progress(); @endphp
                    <a href="{{ route('student.quran-listening.show', $planItem) }}" class="block rounded-xl border border-gray-200 px-3 py-2 hover:border-emerald-300">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-bold text-gray-700">{{ $planItem->title ?: 'خطة استماع' }}</span>
                            <span class="text-[11px] font-bold text-gray-400">{{ $planItem->status->label() }}</span>
                        </div>
                        <div class="text-[11px] text-gray-400 mt-1">{{ $progress['passed'] }} / {{ $progress['total'] }} عنصراً</div>
                    </a>
                @empty
                    <div class="text-sm text-gray-400">لا توجد خطط استماع بعد.</div>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow p-5">
            <h2 class="font-bold text-gray-800 mb-3">مراجعات الخمسات</h2>
            <div class="space-y-2">
                @forelse ($reviews as $reviewItem)
                    @php $reviewProgress = $reviewItem->progress(); @endphp
                    <div class="rounded-xl border border-gray-200 px-3 py-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-bold text-gray-700">{{ $reviewItem->assigned_at?->format('Y-m-d') ?? '—' }}</span>
                            <span class="text-[11px] font-bold text-gray-400">{{ $reviewItem->status->label() }}</span>
                        </div>
                        <div class="text-[11px] mt-1">
                            <span @class([
                                'px-2 py-0.5 rounded-full font-bold',
                                'bg-red-100 text-red-700' => $reviewItem->isRetake(),
                                'bg-emerald-100 text-emerald-800' => ! $reviewItem->isRetake(),
                            ])>{{ $reviewItem->type->label() }}</span>
                        </div>
                        <div class="text-[11px] text-gray-400 mt-1">{{ $reviewProgress['completed'] }} / {{ $reviewProgress['total'] }} خمسة — الأستاذ: {{ $reviewItem->teacher?->name ?? '—' }}</div>
                    </div>
                @empty
                    <div class="text-sm text-gray-400">لا توجد مراجعات بعد.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow p-5 mt-4">
        <h2 class="font-bold text-gray-800 mb-1">سجل التسميع مع المعلم</h2>
        <p class="text-xs text-gray-400 mb-4">جلسات التسميع (الحكم العام) وجلسات الاستماع التفصيلي مع المعلم — الأحدث أولاً.</p>
        <x-quran-teacher-timeline :items="$timeline" :show-actions="false" />
    </div>
</div>
@endsection
