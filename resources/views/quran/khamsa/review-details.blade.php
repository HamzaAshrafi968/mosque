@php
    $progress = $review->progress();
    $statusClasses = [
        'pending' => 'bg-amber-100 text-amber-800',
        'completed' => 'bg-emerald-100 text-emerald-800',
        'cancelled' => 'bg-gray-200 text-gray-600',
    ];
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ $indexRoute }}" class="text-sm text-emerald-700 hover:text-emerald-800">← كل المراجعات</a>
            <h2 class="text-2xl font-extrabold text-gray-800 mt-1">مراجعة 5 — {{ $review->student?->name }}</h2>
            <div class="flex flex-wrap items-center gap-2 mt-2 text-sm text-gray-500">
                <span>الأستاذ: <b class="text-gray-700">{{ $review->teacher?->name ?? '—' }}</b></span>
                <span>• الدوام: <b class="text-gray-700">{{ $review->studySession?->name ?? '—' }}</b></span>
                <span>• التخصيص: {{ $review->assigned_at?->format('Y-m-d') }}</span>
                @if($review->due_date)
                    <span>• الاستحقاق: {{ $review->due_date->format('Y-m-d') }}</span>
                @endif
                @if($review->assignedBy)
                    <span>• بواسطة: {{ $review->assignedBy->name }}</span>
                @endif
            </div>
        </div>
        <div class="flex items-center gap-3">
            <span class="px-3 py-1 rounded-full text-xs font-bold {{ $statusClasses[$review->status->value] ?? 'bg-gray-100 text-gray-600' }}">
                {{ $review->status->label() }}
            </span>
            @if(! $review->isCompleted() && ! $review->isCancelled())
                <form method="POST" action="{{ $cancelRoute }}" onsubmit="return confirm('إلغاء هذه المراجعة؟')">
                    @csrf
                    <button class="text-sm text-red-600 hover:underline">إلغاء المراجعة</button>
                </form>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 p-4">
        <div class="flex items-center justify-between text-sm mb-2">
            <span class="font-bold text-gray-700">التقدم: {{ $progress['completed'] }} من {{ $progress['total'] }} خمسة</span>
            <span class="text-gray-500">{{ $review->pagesCount() }} صفحة</span>
        </div>
        <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
            <div class="h-full bg-emerald-600" style="width: {{ $progress['percentage'] }}%"></div>
        </div>
    </div>

    @if($review->notes)
        <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-900">{{ $review->notes }}</div>
    @endif

    @include('quran.khamsa.memorization-panel', [
        'student' => $review->student,
        'memorizedJuz' => $memorizedJuz,
        'storeRoute' => $memorizationStoreRoute,
        'destroyRoute' => $memorizationDestroyRoute,
    ])

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600">
                        <th class="px-4 py-3 text-right">الخمسة</th>
                        <th class="px-4 py-3 text-right">الحالة</th>
                        <th class="px-4 py-3 text-right">الإنهاء</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($review->items as $item)
                    <tr class="border-t align-top">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="font-bold text-gray-800">الجزء {{ $item->juz }} — الخمسة {{ $item->khamsa }}</div>
                            <div class="text-xs text-gray-400">صفحات {{ $item->from_page }}–{{ $item->to_page }} ({{ $item->pagesCount() }} صفحات)</div>
                        </td>
                        <td class="px-4 py-3">
                            @if($item->isCompleted())
                                <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">تمت</span>
                                @if($item->result)
                                    <div class="text-xs text-gray-600 mt-1">{{ $item->result->label() }}</div>
                                @endif
                                <div class="text-xs text-gray-400 mt-1">
                                    {{ $item->completed_at?->format('Y-m-d H:i') }} — {{ $item->completedBy?->name }}
                                </div>
                                @if($item->quranReviewSession)
                                    <div class="text-xs text-emerald-700 mt-1">
                                        مرتبطة بجلسة استماع {{ $item->quranReviewSession->date?->format('Y-m-d') }}
                                        (إتقان {{ (float) $item->quranReviewSession->mastery_percentage }}%)
                                    </div>
                                @endif
                                @if($item->notes)
                                    <div class="text-xs text-gray-500 mt-1">{{ $item->notes }}</div>
                                @endif
                            @else
                                <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">قيد المراجعة</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if(! $item->isCompleted() && ! $review->isCancelled())
                                <form method="POST" action="{{ $completeRoute($item) }}" class="space-y-2 min-w-56">
                                    @csrf
                                    <select name="result" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                                        <option value="">النتيجة (اختياري)</option>
                                        @foreach($results as $result)
                                            <option value="{{ $result->value }}">{{ $result->label() }}</option>
                                        @endforeach
                                    </select>
                                    @if($listeningSessions->isNotEmpty())
                                        <select name="quran_review_session_id" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                                            <option value="">ربط جلسة استماع (اختياري)</option>
                                            @foreach($listeningSessions as $session)
                                                <option value="{{ $session->id }}">
                                                    {{ $session->date?->format('Y-m-d') }} — صفحات {{ $session->from_page }}–{{ $session->to_page }}
                                                    (إتقان {{ (float) $session->mastery_percentage }}%)
                                                </option>
                                            @endforeach
                                        </select>
                                    @endif
                                    <input type="text" name="notes" placeholder="ملاحظات (اختياري)" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                                    <button type="submit" class="w-full bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">تمت المراجعة</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
