@php
    $progress = $review->progress();
    $statusClasses = [
        'pending' => 'bg-amber-100 text-amber-800',
        'completed' => 'bg-emerald-100 text-emerald-800',
        'cancelled' => 'bg-gray-200 text-gray-600',
    ];
    $embedded = $embedded ?? false;
    $readOnly = $readOnly ?? false;
    $reviewRoute = $reviewRoute ?? null;
    $reviewStartUrl = is_callable($reviewRoute) ? $reviewRoute($review) : $reviewRoute;
    $interactive = ! $readOnly && $reviewStartUrl && ! $review->isCompleted() && ! $review->isCancelled();
    $isRetake = $review->isRetake();
    $testUrl = (! $readOnly && $review->isCompleted() && ! $review->isCancelled()) ? ($testUrl ?? null) : null;
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            @if(! $embedded)
                <a href="{{ $indexRoute }}" class="text-sm text-emerald-700 hover:text-emerald-800">← دفعات الحفظ</a>
            @endif
            <div class="flex flex-wrap items-center gap-2 mt-1">
                <h2 class="text-2xl font-extrabold text-gray-800">مراجعة 5 — {{ $review->student?->name }}</h2>
                <span @class([
                    'px-2.5 py-1 rounded-full text-[11px] font-bold',
                    'bg-red-100 text-red-700 border border-red-200' => $isRetake,
                    'bg-emerald-100 text-emerald-800 border border-emerald-200' => ! $isRetake,
                ])>{{ $review->type->label() }}</span>
            </div>
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
            @if(! $readOnly && ! $review->isCompleted() && ! $review->isCancelled())
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

    @if($testUrl)
        <div @class([
            'flex flex-wrap items-center justify-between gap-3 rounded-2xl border px-4 py-3',
            'border-red-200 bg-red-50' => $isRetake,
            'border-emerald-200 bg-emerald-50' => ! $isRetake,
        ])>
            <p @class(['text-sm', 'text-red-900' => $isRetake, 'text-emerald-900' => ! $isRetake])>
                <span class="font-bold">{{ $isRetake ? 'اكتملت خمسات الإعادة' : 'اكتملت المراجعة' }}:</span>
                {{ $isRetake
                    ? 'سجّل نتيجة اختبار الإعادة (ناجح / يحتاج إعادة) لفتح الدفعة التالية.'
                    : 'سجّل نتيجة الاختبار التراكمي (ناجح / يحتاج إعادة) لفتح الدفعة التالية.' }}
            </p>
            <a href="{{ $testUrl }}" @class([
                'text-white text-sm font-bold px-5 py-2 rounded-xl',
                'bg-red-700 hover:bg-red-800' => $isRetake,
                'bg-emerald-700 hover:bg-emerald-800' => ! $isRetake,
            ])>
                {{ $isRetake ? 'تسجيل اختبار الإعادة' : 'تسجيل نتيجة الاختبار' }}
            </a>
        </div>
    @endif

    @if($review->notes)
        <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-900">{{ $review->notes }}</div>
    @endif

    @if(! $readOnly)
        @include('quran.khamsa.memorization-panel', [
            'student' => $review->student,
            'memorizedJuz' => $memorizedJuz,
            'storeRoute' => $memorizationStoreRoute,
            'destroyRoute' => $memorizationDestroyRoute,
        ])
    @endif

    @if($interactive)
        <form method="GET" action="{{ $reviewStartUrl }}" data-khamsa-select-form class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <h3 class="font-bold text-gray-800">الخمسات — حدد خمسات متتالية للمراجعة</h3>
                <p class="text-xs text-gray-500">افتح القرآن على الخمسات المحددة وسجّل الأخطاء كلمة بكلمة مثل التسميع.</p>
            </div>

            @if($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 text-red-700 text-sm font-medium px-4 py-3 mb-3">{{ $errors->first() }}</div>
            @endif

            @once
                @push('styles')
                    <style>
                        .khamsa-square { transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease; }
                        .khamsa-square .khamsa-check { opacity: 0; transform: scale(.6); transition: opacity .15s ease, transform .15s ease; }
                        .khamsa-square.is-selected { border-color: #10b981; background-color: #ecfdf5; box-shadow: 0 0 0 3px rgba(16, 185, 129, .18); }
                        .khamsa-square.is-selected .khamsa-check { opacity: 1; transform: scale(1); }
                        .khamsa-square.is-selected .khamsa-state { color: #047857; }
                    </style>
                @endpush
            @endonce

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                @foreach($review->items as $item)
                    @php $completed = $item->isCompleted(); @endphp
                    @if($completed)
                        <div class="rounded-xl border-2 border-emerald-300 bg-emerald-50 p-3">
                            <div class="font-bold text-sm text-gray-800">الجزء {{ $item->juz }} — الخمسة {{ $item->khamsa }}</div>
                            <div class="text-[11px] text-gray-400 mt-0.5">صفحات {{ $item->from_page }}–{{ $item->to_page }} ({{ $item->pagesCount() }} صفحات)</div>
                            <div class="text-[11px] mt-1 font-bold text-emerald-700">تمت</div>
                            @if($item->result)
                                <div class="text-[11px] text-gray-500 mt-1">{{ $item->result->label() }}</div>
                            @endif
                            <div class="text-[11px] text-gray-400 mt-1">
                                {{ $item->completed_at?->format('Y-m-d H:i') }} — {{ $item->completedBy?->name }}
                            </div>
                            @if($item->quranReviewSession)
                                <div class="text-[11px] text-emerald-700 mt-1">
                                    جلسة استماع {{ $item->quranReviewSession->date?->format('Y-m-d') }}
                                    (إتقان {{ (float) $item->quranReviewSession->mastery_percentage }}%)
                                </div>
                            @endif
                        </div>
                    @else
                        <label class="block cursor-pointer">
                            <input type="checkbox" name="items[]" value="{{ $item->id }}" class="sr-only"
                                data-khamsa-checkbox data-from="{{ $item->from_page }}" data-to="{{ $item->to_page }}">
                            <div data-khamsa-square class="khamsa-square rounded-xl border-2 border-gray-200 bg-white p-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="font-bold text-sm text-gray-800">الجزء {{ $item->juz }} — الخمسة {{ $item->khamsa }}</div>
                                    <span data-khamsa-check class="khamsa-check w-5 h-5 rounded-full bg-emerald-600 text-white text-[11px] font-black flex items-center justify-center shrink-0">✓</span>
                                </div>
                                <div class="text-[11px] text-gray-400 mt-0.5">صفحات {{ $item->from_page }}–{{ $item->to_page }} ({{ $item->pagesCount() }} صفحات)</div>
                                <div data-khamsa-state class="khamsa-state text-[11px] mt-1 font-bold text-amber-700">قيد المراجعة</div>
                            </div>
                        </label>
                    @endif
                @endforeach
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 mt-4">
                <span data-khamsa-count class="text-xs font-bold text-gray-500">لم تحدد أي خمسة بعد</span>
                <div class="flex flex-wrap items-center gap-3">
                    <span data-khamsa-error class="hidden text-xs font-bold text-red-600">التحديد يجب أن يكون خمسات متتالية بلا فجوات</span>
                    <button type="submit" data-khamsa-start disabled
                        class="px-5 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 disabled:opacity-40 disabled:cursor-not-allowed text-white text-sm font-bold">
                        ▶ بدء المراجعة مع تسجيل الأخطاء
                    </button>
                </div>
            </div>
        </form>

        @push('scripts')
            <script>
                (function () {
                    const form = document.querySelector('[data-khamsa-select-form]');

                    if (!form) {
                        return;
                    }

                    const boxes = Array.from(form.querySelectorAll('[data-khamsa-checkbox]'));
                    const button = form.querySelector('[data-khamsa-start]');
                    const counter = form.querySelector('[data-khamsa-count]');
                    const error = form.querySelector('[data-khamsa-error]');

                    function refresh() {
                        boxes.forEach(function (box) {
                            const label = box.closest('label');
                            const square = label ? label.querySelector('[data-khamsa-square]') : null;

                            if (!square) {
                                return;
                            }

                            square.classList.toggle('is-selected', box.checked);

                            const state = square.querySelector('[data-khamsa-state]');

                            if (state) {
                                state.textContent = box.checked ? 'محددة للمراجعة' : 'قيد المراجعة';
                            }
                        });

                        const selected = boxes.filter(function (box) { return box.checked; })
                            .map(function (box) {
                                return { from: parseInt(box.dataset.from, 10), to: parseInt(box.dataset.to, 10) };
                            })
                            .sort(function (a, b) { return a.from - b.from; });

                        let contiguous = true;

                        for (let i = 1; i < selected.length; i++) {
                            if (selected[i].from !== selected[i - 1].to + 1) {
                                contiguous = false;
                                break;
                            }
                        }

                        if (counter) {
                            counter.textContent = selected.length
                                ? 'المحدد: ' + selected.length + ' خمسة'
                                : 'لم تحدد أي خمسة بعد';
                        }

                        if (error) {
                            error.classList.toggle('hidden', selected.length === 0 || contiguous);
                        }

                        if (button) {
                            button.disabled = selected.length === 0 || !contiguous;
                        }
                    }

                    boxes.forEach(function (box) { box.addEventListener('change', refresh); });
                    refresh();
                })();
            </script>
        @endpush
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            @foreach($review->items as $item)
                @php $completed = $item->isCompleted(); @endphp
                <div @class([
                    'rounded-xl border-2 p-3',
                    'border-emerald-300 bg-emerald-50' => $completed,
                    'border-amber-300 bg-amber-50' => ! $completed,
                ])>
                    <div class="font-bold text-sm text-gray-800">الجزء {{ $item->juz }} — الخمسة {{ $item->khamsa }}</div>
                    <div class="text-[11px] text-gray-400 mt-0.5">صفحات {{ $item->from_page }}–{{ $item->to_page }} ({{ $item->pagesCount() }} صفحات)</div>
                    <div class="text-[11px] mt-1 font-bold {{ $completed ? 'text-emerald-700' : 'text-amber-700' }}">
                        {{ $completed ? 'تمت' : 'قيد المراجعة' }}
                    </div>
                    @if($completed)
                        @if($item->result)
                            <div class="text-[11px] text-gray-500 mt-1">{{ $item->result->label() }}</div>
                        @endif
                        @if($item->quranReviewSession)
                            <div class="text-[11px] text-emerald-700 mt-1">
                                جلسة استماع {{ $item->quranReviewSession->date?->format('Y-m-d') }}
                                (إتقان {{ (float) $item->quranReviewSession->mastery_percentage }}%)
                            </div>
                        @endif
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
