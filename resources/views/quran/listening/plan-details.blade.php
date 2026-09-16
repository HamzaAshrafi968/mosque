@php
    $progress = $plan->progress();
    $statusClasses = [
        'locked' => 'bg-gray-200 text-gray-600',
        'available' => 'bg-sky-100 text-sky-800',
        'listened' => 'bg-amber-100 text-amber-800',
        'needs_repeat' => 'bg-red-100 text-red-700',
        'passed' => 'bg-emerald-100 text-emerald-800',
    ];
    $planStatusClasses = [
        'active' => 'bg-sky-100 text-sky-800',
        'completed' => 'bg-emerald-100 text-emerald-800',
        'cancelled' => 'bg-gray-200 text-gray-600',
    ];
    $sessions = $listeningSessions ?? collect();
    $khamsaRoute = $khamsaRoute ?? null;
    $embedded = $embedded ?? false;
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            @if(! $embedded)
                <a href="{{ $indexRoute }}" class="text-sm text-emerald-700 hover:text-emerald-800">← دفعات الحفظ</a>
            @endif
            <h2 class="text-2xl font-extrabold text-gray-800 mt-1">
                {{ $plan->title ?: 'خطة استماع — '.($plan->student?->name ?? '') }}
            </h2>
            <div class="flex flex-wrap items-center gap-2 mt-2 text-sm text-gray-500">
                <span>الطالب: <b class="text-gray-700">{{ $plan->student?->name ?? '—' }}</b></span>
                <span>• الأستاذ: <b class="text-gray-700">{{ $plan->teacher?->name ?? '—' }}</b></span>
                <span>• الدوام: <b class="text-gray-700">{{ $plan->studySession?->name ?? '—' }}</b></span>
                <span>• العناصر المفتوحة معاً: <b class="text-gray-700">{{ $plan->gate_size }}</b></span>
                @if($plan->khamsaReview)
                    <span>• مراجعة 5 المرتبطة:
                        @if($khamsaRoute)
                            <a href="{{ $khamsaRoute($plan->khamsaReview) }}" class="text-emerald-700 hover:underline font-bold">{{ $plan->khamsaReview->status->label() }}</a>
                        @else
                            <b class="text-gray-700">{{ $plan->khamsaReview->status->label() }}</b>
                        @endif
                    </span>
                @endif
            </div>
        </div>
        <div class="flex items-center gap-3">
            <span class="px-3 py-1 rounded-full text-xs font-bold {{ $planStatusClasses[$plan->status->value] ?? 'bg-gray-100 text-gray-600' }}">
                {{ $plan->status->label() }}
            </span>
            @if($cancelRoute && $plan->isActive())
                <form method="POST" action="{{ $cancelRoute }}" onsubmit="return confirm('إلغاء خطة الاستماع؟')">
                    @csrf
                    <button class="text-sm text-red-600 hover:underline">إلغاء الخطة</button>
                </form>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 p-4">
        <div class="flex items-center justify-between text-sm mb-2">
            <span class="font-bold text-gray-700">التقدم: {{ $progress['passed'] }} من {{ $progress['total'] }} عنصراً</span>
            <span class="text-gray-500">{{ $plan->pagesCount() }} صفحة</span>
        </div>
        <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
            <div class="h-full bg-emerald-600" style="width: {{ $progress['percentage'] }}%"></div>
        </div>
        @if(! empty($memorizedJuz))
            <div class="flex flex-wrap items-center gap-1.5 mt-3 text-xs">
                <span class="text-gray-500 font-bold">الأجزاء المحفوظة ({{ count($memorizedJuz) }}):</span>
                @foreach($memorizedJuz as $juz)
                    <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200 font-bold">{{ $juz }}</span>
                @endforeach
            </div>
        @endif
    </div>

    @if($plan->notes)
        <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-900">{{ $plan->notes }}</div>
    @endif

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600">
                        <th class="px-4 py-3 text-right">العنصر والصفحات</th>
                        <th class="px-4 py-3 text-right">الحالة</th>
                        <th class="px-4 py-3 text-right">المحاولات</th>
                        <th class="px-4 py-3 text-right">الاستماع / التشغيل</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($plan->items as $item)
                    <tr class="border-t align-top">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <span @class([
                                    'px-2 py-0.5 rounded-full text-[11px] font-bold',
                                    'bg-sky-100 text-sky-800' => ! $item->isReview(),
                                    'bg-gold-100 text-gold-800' => $item->isReview(),
                                ])>{{ $item->isReview() ? 'مراجعة 5' : 'جديد' }}</span>
                                <span class="font-bold text-gray-800">{{ $item->isReview() ? $item->label() : 'الجزء '.$item->juz }}</span>
                            </div>
                            <div class="text-xs text-gray-400 mt-0.5">صفحات {{ $item->from_page }}–{{ $item->to_page }} ({{ $item->pagesCount() }} صفحات)</div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold {{ $statusClasses[$item->status->value] ?? 'bg-gray-100 text-gray-600' }}">
                                {{ $item->status->label() }}
                            </span>
                            @if($item->last_result)
                                <div class="text-[11px] text-gray-400 mt-1">آخر نتيجة: {{ $item->last_result->label() }}</div>
                            @endif
                            @if($item->passed_at)
                                <div class="text-[11px] text-gray-400 mt-1">{{ $item->passed_at->format('Y-m-d') }} — {{ $item->passedBy?->name }}</div>
                            @endif
                            @if($item->isReview() && $item->khamsaReviewItem)
                                <div class="text-[11px] mt-1 {{ $item->khamsaReviewItem->isCompleted() ? 'text-emerald-600' : 'text-gray-400' }}">
                                    مراجعة 5: {{ $item->khamsaReviewItem->status->label() }}
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ $item->attempts }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap items-center gap-2">
                                @if(! $item->isLocked())
                                    <button type="button" data-player-start
                                        data-audio-url="{{ $audioRoute($item) }}"
                                        data-progress-url="{{ $progressRoute($item) }}"
                                        data-item-label="{{ $item->label() }}"
                                        class="px-3 py-1.5 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold">▶ تشغيل التلاوة</button>
                                @else
                                    <span class="text-xs text-gray-400">🔒 يُفتح بعد نجاح الأجزاء السابقة</span>
                                @endif

                                @if($canListen && $item->canBeListened())
                                    <form method="POST" action="{{ $listenRoute($item) }}" class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        @if($sessions->isNotEmpty())
                                            <select name="quran_review_session_id" class="border border-gray-300 rounded-lg px-2 py-1 text-[11px] max-w-[14rem]">
                                                <option value="">ربط جلسة «الاستماع مع المعلم» (اختياري)</option>
                                                @foreach($sessions as $session)
                                                    <option value="{{ $session->id }}">
                                                        {{ $session->date?->format('Y-m-d') }} — صفحات {{ $session->from_page }}–{{ $session->to_page }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        @endif
                                        <button class="px-3 py-1.5 rounded-lg bg-gold-500 hover:bg-gold-600 text-pine-950 text-xs font-bold">
                                            {{ $item->isNeedsRepeat() ? 'أعدت الاستماع' : 'تم الاستماع' }}
                                        </button>
                                    </form>
                                @endif

                                @if($item->listeningSession)
                                    <span class="text-[11px] text-emerald-700">الاستماع مع المعلم: {{ $item->listeningSession->date?->format('Y-m-d') }}</span>
                                @endif

                                @if($item->listened_at && ! $item->isPassed())
                                    <span class="text-[11px] text-gray-400">استُمع {{ $item->listened_at->format('Y-m-d') }}</span>
                                @endif
                                @if($item->listen_seconds > 0)
                                    <span class="text-[11px] text-gray-400">({{ intdiv($item->listen_seconds, 60) }} دقيقة تشغيل)</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($canTest && $plan->isActive())
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 space-y-4">
            <div>
                <h3 class="font-bold text-gray-800">تسجيل اختبار الدفعة</h3>
                <p class="text-xs text-gray-500 mt-1">كل عنصر مُستمع يظهر هنا: حدّد «ناجح» أو «يحتاج إعادة» — ولا يُفتح العنصر التالي حتى تنجح كل العناصر المفتوحة، ونجاح «مراجعة 5» يُنهي الخمسة المرتبطة تلقائياً.</p>
            </div>

            @error('results') <p class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{{ $message }}</p> @enderror

            @if($listeningItems->isNotEmpty())
                <form method="POST" action="{{ $testRoute }}" class="space-y-3">
                    @csrf
                    @foreach($listeningItems as $item)
                        <div class="rounded-xl border border-gray-200 p-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="font-bold text-gray-800 text-sm">{{ $item->label() }}</div>
                                <div class="flex items-center gap-4 text-sm">
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="radio" name="results[{{ $item->id }}][result]" value="pass" checked
                                            class="text-emerald-600 focus:ring-emerald-500">
                                        <span class="font-bold text-emerald-700">ناجح</span>
                                    </label>
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="radio" name="results[{{ $item->id }}][result]" value="fail"
                                            class="text-red-600 focus:ring-red-500">
                                        <span class="font-bold text-red-700">يحتاج إعادة</span>
                                    </label>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mt-2">
                                <input type="text" name="results[{{ $item->id }}][notes]" maxlength="1000"
                                    placeholder="ملاحظات الجزء (اختياري)"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
                                @if($sessions->isNotEmpty())
                                    <select name="results[{{ $item->id }}][quran_review_session_id]"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
                                        <option value="">ربط جلسة «الاستماع مع المعلم» (اختياري)</option>
                                        @foreach($sessions as $session)
                                            <option value="{{ $session->id }}">
                                                {{ $session->date?->format('Y-m-d') }} — صفحات {{ $session->from_page }}–{{ $session->to_page }}
                                                (إتقان {{ (float) $session->mastery_percentage }}%)
                                            </option>
                                        @endforeach
                                    </select>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    <textarea name="notes" rows="2" placeholder="ملاحظات عامة على الاختبار (اختياري)"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>

                    <div class="flex justify-end">
                        <button class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-6 py-2 rounded-xl">تسجيل نتيجة الاختبار</button>
                    </div>
                </form>
            @else
                <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                    لا توجد أجزاء جاهزة للاختبار — يجب تسجيل الاستماع أولاً.
                </p>
            @endif
        </div>
    @endif

    @if($plan->tests->isNotEmpty())
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 font-bold text-gray-800">سجل الاختبارات</div>
            <div class="divide-y divide-gray-100">
                @foreach($plan->tests as $test)
                    <div class="p-4 space-y-2">
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <span @class([
                                'px-2 py-0.5 rounded-full text-xs font-bold',
                                'bg-emerald-100 text-emerald-800' => $test->isPass(),
                                'bg-red-100 text-red-700' => ! $test->isPass(),
                            ])>{{ $test->result->label() }}</span>
                            <span class="text-gray-500">{{ $test->tested_at?->format('Y-m-d H:i') }}</span>
                            @if($test->testedBy)
                                <span class="text-gray-400">بواسطة {{ $test->testedBy->name }}</span>
                            @endif
                            @if($test->notes)
                                <span class="text-gray-500">— {{ $test->notes }}</span>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach($test->items as $result)
                                <span @class([
                                    'px-2 py-1 rounded-lg text-[11px] font-bold border',
                                    'border-emerald-200 bg-emerald-50 text-emerald-800' => $result->result->value === 'pass',
                                    'border-red-200 bg-red-50 text-red-700' => $result->result->value !== 'pass',
                                ])>
                                    الجزء {{ $result->juz }} ({{ $result->from_page }}–{{ $result->to_page }})
                                    — {{ $result->result->label() }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @include('quran.listening.player')
</div>
