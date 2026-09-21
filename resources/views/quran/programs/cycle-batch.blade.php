@php
    use App\Enums\QuranListeningBatchStatus;
    use App\Enums\QuranListeningItemStatus;
    use App\Enums\QuranListeningTestResult;
    use App\Support\QuranJuzMap;

    $scoreLabel = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    $threshold = rtrim(rtrim(number_format((float) ($minimumPassingPercentage ?? 80), 2, '.', ''), '0'), '.');
    $lastTest = $currentBatch?->lastTest;
    $isRetest = $lastTest && ! $lastTest->isPass();

    $programItemLabel = fn (?QuranListeningItemStatus $status) => match ($status) {
        QuranListeningItemStatus::Available => 'قيد التسميع',
        QuranListeningItemStatus::Listened => 'تم التسميع',
        default => $status?->label() ?? '—',
    };

    $programBatchLabel = fn (QuranListeningBatchStatus $status) => $status === QuranListeningBatchStatus::Listening
        ? 'قيد التسميع'
        : $status->label();

    $batchClasses = fn (QuranListeningBatchStatus $status) => match ($status) {
        QuranListeningBatchStatus::Locked => 'border-gray-200 bg-gray-50 text-gray-500',
        QuranListeningBatchStatus::Listening => 'border-sky-200 bg-sky-50 text-sky-900',
        QuranListeningBatchStatus::ReadyForTest => 'border-amber-200 bg-amber-50 text-amber-900',
        QuranListeningBatchStatus::NeedsRepeat => 'border-red-200 bg-red-50 text-red-900',
        QuranListeningBatchStatus::Passed => 'border-emerald-200 bg-emerald-50 text-emerald-900',
    };

    $itemClasses = fn (?QuranListeningItemStatus $status) => match ($status) {
        QuranListeningItemStatus::Locked => 'border-gray-200 bg-gray-50 text-gray-400',
        QuranListeningItemStatus::Available => 'border-sky-200 bg-sky-50 text-sky-900',
        QuranListeningItemStatus::Listened => 'border-indigo-200 bg-indigo-50 text-indigo-900',
        QuranListeningItemStatus::NeedsRepeat => 'border-red-200 bg-red-50 text-red-900',
        QuranListeningItemStatus::Passed => 'border-emerald-200 bg-emerald-50 text-emerald-900',
        default => 'border-gray-200 bg-white text-gray-500',
    };

    $nextBatchOpen = false;

    if ($currentBatch) {
        foreach ($states as $state) {
            if ($state['batch_number'] === $currentBatch->batch_number + 1) {
                $nextBatchOpen = $state['status'] !== QuranListeningBatchStatus::Locked;
            }
        }
    }
@endphp

<div class="space-y-6">
    {{-- ترويسة البرنامج --}}
    <div class="bg-white rounded-2xl shadow p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-xl font-black text-gray-800">{{ $program->displayLabel() }}</h2>
                    <span class="text-[11px] font-bold px-2 py-0.5 rounded-full {{ $program->isActive() ? 'bg-emerald-100 text-emerald-800' : ($program->isCompleted() ? 'bg-sky-100 text-sky-800' : 'bg-gray-100 text-gray-600') }}">
                        {{ $program->status->label() }}
                    </span>
                </div>
                <p class="text-sm text-gray-500 mt-1">
                    الطالب: <b class="text-gray-700">{{ $program->student?->name }}</b>
                    @if ($program->enrollment)
                        — الالتحاق: <b class="text-gray-700">{{ $program->enrollment->program_type->label() }}</b>
                    @endif
                </p>
                <p class="text-xs text-gray-400 mt-1">
                    الدورة: تسميع كل جزء مع تسجيل الأخطاء ← اختبار تراكمي من الجزء 1 إلى آخر جزء في الدفعة ← إعادة الأجزاء الراسبة فقط.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if (($actions['show'] ?? null) && $program)
                    <a href="{{ $actions['show']($program) }}"
                        class="text-xs font-bold text-emerald-700 border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 px-3 py-2 rounded-lg">
                        صفحة الدورة
                    </a>
                @endif
                @if ($canCancel && $program->isActive() && ($actions['cancel'] ?? null))
                    <form method="POST" action="{{ $actions['cancel']($program) }}"
                        onsubmit="return confirm('إلغاء {{ $program->displayLabel() }}؟')">
                        @csrf
                        <button class="text-xs font-bold text-red-700 border border-red-200 bg-red-50 hover:bg-red-100 px-3 py-2 rounded-lg">
                            إلغاء البرنامج
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- خطوات الدورة --}}
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <div @class([
            'rounded-2xl border p-4',
            'border-emerald-300 bg-emerald-50' => $coverage && $coverage['remaining'] === 0,
            'border-sky-200 bg-sky-50' => $coverage && $coverage['remaining'] > 0,
            'border-gray-200 bg-gray-50' => ! $coverage,
        ])>
            <div class="text-[11px] font-bold text-gray-500 mb-1">١. التسميع مع المعلم</div>
            @if ($coverage)
                <div class="text-sm font-black text-gray-800">{{ $coverage['covered'] }} / {{ $coverage['total'] }} صفحة</div>
                <div class="mt-2 h-1.5 rounded-full bg-white/70 overflow-hidden">
                    <div class="h-full bg-emerald-600" style="width: {{ min(100, $coverage['percentage']) }}%"></div>
                </div>
                <div class="text-[11px] text-gray-500 mt-1">
                    {{ $coverage['remaining'] === 0 ? '✓ اكتمل تسميع أجزاء الدفعة' : 'المتبقي: '.$coverage['remaining'].' صفحة' }}
                </div>
            @else
                <div class="text-sm font-black text-gray-800">اكتمل التسميع</div>
            @endif
        </div>

        <div @class([
            'rounded-2xl border p-4',
            'border-emerald-300 bg-emerald-50' => $currentBatch?->isPassed(),
            'border-red-300 bg-red-50' => $currentBatch?->isNeedsRepeat(),
            'border-amber-300 bg-amber-50' => $currentBatch?->isReadyForTest(),
            'border-gray-200 bg-gray-50' => $currentBatch && ! $currentBatch->isReadyForTest() && ! $currentBatch->isNeedsRepeat(),
        ])>
            <div class="text-[11px] font-bold text-gray-500 mb-1">٢. الاختبار التراكمي</div>
            <div class="text-sm font-black text-gray-800">
                @if ($currentBatch?->isNeedsRepeat())
                    <a href="#batch-test" class="text-red-700 hover:underline">
                        رسب — الأجزاء: {{ $failedJuz === [] ? '—' : implode('، ', $failedJuz) }}
                    </a>
                @elseif ($currentBatch?->isReadyForTest())
                    <a href="#batch-test" class="text-amber-800 hover:underline">
                        {{ $lastTest ? 'إعادة الاختبار مطلوبة' : 'مطلوب الآن — سجّل النتيجة' }}
                    </a>
                @elseif ($placementTestAllowed)
                    <a href="#placement-test" class="text-sky-700 hover:underline">اختبار مباشر متاح</a>
                @elseif ($lastTest)
                    {{ $scoreLabel($lastTest->score) }}%
                @else
                    بانتظار اكتمال التسميع
                @endif
            </div>
        </div>

        <div @class([
            'rounded-2xl border p-4',
            'border-emerald-300 bg-emerald-50' => $nextBatchOpen,
            'border-gray-200 bg-gray-50' => ! $nextBatchOpen,
        ])>
            <div class="text-[11px] font-bold text-gray-500 mb-1">٣. الدفعة التالية</div>
            <div class="text-sm font-black text-gray-800">
                @if (! $currentBatch)
                    اكتملت الدورة
                @elseif ($nextBatchOpen)
                    🟢 مفتوحة للتسميع
                @elseif ($currentBatch->batch_number === \App\Models\QuranListeningProgramBatch::TOTAL_BATCHES)
                    آخر دفعة — بانتظار الإتمام
                @else
                    🔒 مقفلة حتى الاجتياز
                @endif
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-4">
            <div class="text-[11px] font-bold text-gray-400">الملخص</div>
            <div class="text-sm font-black text-gray-800">{{ $summary['passed_juz'] ?? 0 }} / {{ $summary['total_juz'] ?? 30 }} جزء ناجح</div>
            <div class="text-[11px] text-gray-400 mt-1">الدفعات الناجحة: {{ $summary['passed_batches'] ?? 0 }} / {{ $summary['total_batches'] ?? 6 }}</div>
        </div>
    </div>

    {{-- شبكة الدفعات الست --}}
    <div class="bg-white rounded-2xl shadow p-5">
        <h3 class="font-bold text-gray-700 mb-3">الدفعات الست (كل دفعة 5 أجزاء)</h3>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
            @foreach ($states as $state)
                @php $batch = $state['batch']; @endphp
                <div class="rounded-xl border p-3 {{ $batchClasses($state['status']) }}">
                    <div class="text-[11px] font-bold opacity-70">الدفعة {{ $state['batch_number'] }}</div>
                    <div class="font-black text-sm mt-0.5">الأجزاء {{ $state['from_juz'] }}–{{ $state['to_juz'] }}</div>
                    <div class="text-[11px] font-bold mt-1">{{ $programBatchLabel($state['status']) }}</div>
                    @if ($batch->lastTest)
                        <div class="text-[11px] mt-1 opacity-80">
                            آخر اختبار: {{ $scoreLabel($batch->lastTest->score) }}%
                            {{ $batch->lastTest->isPass() ? '✓' : '↺' }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- خريطة الأجزاء الثلاثين --}}
    <div class="bg-white rounded-2xl shadow p-5">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <h3 class="font-bold text-gray-700">خريطة الأجزاء الثلاثين</h3>
            <div class="flex flex-wrap items-center gap-3 text-[11px] font-bold text-gray-500">
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-sky-100 border border-sky-200"></span> قيد التسميع</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-indigo-100 border border-indigo-200"></span> تم التسميع</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-red-100 border border-red-200"></span> يحتاج إعادة</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-emerald-100 border border-emerald-200"></span> ناجح</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-gray-100 border border-gray-200"></span> مقفل</span>
            </div>
        </div>
        <div class="grid grid-cols-5 gap-2 md:grid-cols-10">
            @foreach ($juzGrid as $cell)
                <div class="rounded-lg border p-2 text-center {{ $itemClasses($cell['status']) }}">
                    <div class="font-black text-sm">{{ $cell['juz'] }}</div>
                    <div class="text-[10px] leading-tight">{{ $cell['status'] ? $programItemLabel($cell['status']) : '—' }}</div>
                    @if ($cell['batch_number'])
                        <div class="text-[9px] opacity-60">د{{ $cell['batch_number'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- ١. التسميع مع المعلم --}}
    <div class="bg-white rounded-2xl shadow p-5">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <div class="flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-800 text-xs font-black flex items-center justify-center">١</span>
                <h3 class="font-bold text-gray-700">التسميع مع المعلم — {{ $currentBatch?->label() ?? 'لا دفعة مفتوحة' }}</h3>
            </div>
            @if ($currentBatch)
                <span class="text-[11px] font-bold text-gray-400">
                    {{ $currentBatch->items->filter(fn ($item) => $item->isListened() || $item->isPassed())->count() }} / {{ $currentBatch->items->count() }} جزء مكتمل
                </span>
            @endif
        </div>

        @if (! $currentBatch)
            <p class="text-sm text-emerald-700">ما شاء الله — اكتملت جميع الدفعات.</p>
        @else
            @if ($coverage)
                <div class="rounded-xl border border-gray-200 p-3 mb-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500 mb-2">
                        <span>
                            تقدم الدفعة: <b class="text-gray-700">{{ $coverage['covered'] }} من {{ $coverage['total'] }} صفحة</b>
                            — المتبقي: <b class="text-amber-700">{{ $coverage['remaining'] }}</b>
                        </span>
                        <span class="font-black text-emerald-700">{{ $coverage['percentage'] }}%</span>
                    </div>
                    <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden">
                        <div class="h-full bg-emerald-600" style="width: {{ min(100, $coverage['percentage']) }}%"></div>
                    </div>
                    <div class="text-[11px] text-gray-400 mt-1">نطاق الدفعة: صفحات {{ $coverage['from'] }}–{{ $coverage['to'] }}</div>
                </div>

                <div class="space-y-2 mb-4">
                    @foreach ($currentBatch->items as $item)
                        @php
                            $juzRow = $coverage['juz'][$item->juz] ?? null;
                            $retakeRow = $retakeCoverage[$item->juz] ?? null;
                            $coveredPagesForJuz = $coveredPages[$item->juz] ?? [];
                            $nextPage = collect(range($item->from_page, $item->to_page))
                                ->first(fn (int $page) => ! in_array($page, $coveredPagesForJuz, true)) ?? $item->from_page;
                        @endphp
                        <div data-program-item="{{ $item->id }}" class="rounded-xl border p-3 {{ $item->isNeedsRepeat() ? 'border-red-200 bg-red-50/60' : 'border-gray-200' }}">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="min-w-40">
                                    <div class="font-bold text-gray-800 text-sm">{{ $item->label() }}</div>
                                    <div class="text-[11px] text-gray-400">
                                        {{ $programItemLabel($item->status) }}
                                        @if ($juzRow)
                                            — {{ $juzRow['covered'] }}/{{ $juzRow['total'] }} صفحة
                                        @endif
                                        @if ($item->listened_at)
                                            — سجّله: {{ $item->listenedBy?->name ?? '—' }} ({{ $item->listened_at->format('Y-m-d') }})
                                        @endif
                                        @if ($item->quranRecitationSession?->result)
                                            — التقدير: {{ $item->quranRecitationSession->result->label() }}
                                        @endif
                                    </div>
                                    @if ($retakeRow)
                                        <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                            <div class="h-1.5 w-28 rounded-full bg-red-100 overflow-hidden">
                                                <div class="h-full bg-red-500" style="width: {{ min(100, $retakeRow['percentage']) }}%"></div>
                                            </div>
                                            <span class="text-[11px] font-bold text-red-700">
                                                أُعيد تسميع {{ $retakeRow['covered'] }} من {{ $retakeRow['total'] }} صفحة بعد الرسوب
                                                {{ $retakeRow['complete'] ? '✓' : '' }}
                                            </span>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($program->isActive() && $item->canBeListened() && ($actions['tasmee'] ?? null))
                                        <a href="{{ $actions['tasmee']($item) }}"
                                            class="text-xs font-bold text-white bg-emerald-700 hover:bg-emerald-800 px-3 py-2 rounded-lg">
                                            فتح الصفحات وتسجيل الأخطاء
                                        </a>
                                        @if ($actions['partial'] ?? null)
                                            <button type="button" data-partial-toggle="{{ $item->id }}"
                                                class="text-xs font-bold text-sky-800 bg-sky-100 hover:bg-sky-200 border border-sky-200 px-3 py-2 rounded-lg">
                                                تسجيل استماع جزئي
                                            </button>
                                        @endif
                                    @elseif ($item->isPassed())
                                        <span class="text-[11px] font-bold text-emerald-700">✓ ناجح</span>
                                    @elseif ($item->isNeedsRepeat())
                                        <span class="text-[11px] font-bold text-red-700">يحتاج إعادة تسميع</span>
                                    @elseif ($item->isListened())
                                        <span class="text-[11px] font-bold text-indigo-700">بانتظار الاختبار</span>
                                    @else
                                        <span class="text-[11px] font-bold text-gray-400">مقفل</span>
                                    @endif
                                </div>
                            </div>

                            @if ($program->isActive() && $item->canBeListened() && ($actions['partial'] ?? null))
                                <form method="POST" action="{{ $actions['partial']($item) }}"
                                    class="hidden mt-3 rounded-xl border border-sky-200 bg-sky-50/70 p-3 space-y-2"
                                    data-partial-form="{{ $item->id }}">
                                    @csrf
                                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                        <div>
                                            <label class="block text-xs font-bold text-gray-600 mb-1">من صفحة</label>
                                            <input type="number" name="from_page" min="{{ $item->from_page }}" max="{{ $item->to_page }}"
                                                value="{{ $nextPage }}" data-partial-from
                                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-gray-600 mb-1">إلى صفحة</label>
                                            <input type="number" name="to_page" min="{{ $item->from_page }}" max="{{ $item->to_page }}"
                                                value="{{ $nextPage }}" data-partial-to
                                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-gray-600 mb-1">التاريخ</label>
                                            <input type="date" name="date" value="{{ now()->toDateString() }}"
                                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-gray-600 mb-1">ملاحظات</label>
                                            <input type="text" name="notes" maxlength="2000" placeholder="اختياري"
                                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p class="text-[11px] text-gray-500">
                                            سجّل الصفحات التي استمعها الطالب فقط ({{ $item->from_page }}–{{ $item->to_page }}) — يبقى الجزء «قيد التسميع» حتى تكتمل صفحاته.
                                        </p>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <button type="button" data-partial-preview
                                                data-preview-url="{{ route('quran.pages.preview', ['page' => '__PAGE__']) }}"
                                                class="text-xs font-bold text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 px-3 py-2 rounded-lg">
                                                معاينة الصفحات
                                            </button>
                                            <button type="submit" class="text-xs font-bold text-white bg-sky-700 hover:bg-sky-800 px-4 py-2 rounded-lg">
                                                حفظ الاستماع الجزئي
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($actions['partial'] ?? null)
                    <div data-pages-modal class="hidden fixed inset-0 z-[60]">
                        <div class="absolute inset-0 bg-gray-900/70 backdrop-blur-sm" data-pages-close></div>
                        <div class="relative mx-auto my-6 w-[min(96vw,56rem)] max-h-[90vh] flex flex-col bg-white rounded-3xl shadow-2xl overflow-hidden">
                            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                                <h3 class="font-black text-gray-800">معاينة صفحات المصحف</h3>
                                <button type="button" data-pages-close class="p-2 rounded-xl hover:bg-gray-100" aria-label="إغلاق">✕</button>
                            </div>
                            <div data-pages-body class="p-4 overflow-y-auto bg-[#f4f6f4]"></div>
                        </div>
                    </div>
                @endif
            @endif

            @if ($tasmeeSessions->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-right text-[11px] text-gray-400 border-b border-gray-100">
                                <th class="py-2">التاريخ</th>
                                <th class="py-2">الصفحات</th>
                                <th class="py-2">الأستاذ</th>
                                <th class="py-2">التقدير</th>
                                <th class="py-2">الأخطاء</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tasmeeSessions as $session)
                                @php
                                    $sessionJuz = $session->from_page ? QuranJuzMap::juzForPage((int) $session->from_page) : null;
                                    $sessionJuzRange = $sessionJuz ? QuranJuzMap::pageRange($sessionJuz) : null;
                                    $isPartialSession = $sessionJuzRange
                                        && ((int) $session->from_page !== $sessionJuzRange['from'] || (int) $session->to_page !== $sessionJuzRange['to']);
                                @endphp
                                <tr class="border-b border-gray-50">
                                    <td class="py-2 text-gray-500">{{ $session->date?->format('Y-m-d') ?? '—' }}</td>
                                    <td class="py-2 text-gray-500">
                                        {{ $session->from_page }}–{{ $session->to_page }}
                                        @if ($isPartialSession)
                                            <span class="text-[10px] font-bold text-sky-700 bg-sky-100 border border-sky-200 px-1.5 py-0.5 rounded-full">جزئي</span>
                                        @endif
                                    </td>
                                    <td class="py-2 text-gray-500">{{ $session->teacher?->name ?? '—' }}</td>
                                    <td class="py-2 font-bold text-gray-700">{{ $session->result?->label() ?? '—' }}</td>
                                    <td class="py-2 text-gray-500">{{ is_array($session->word_statuses) ? count($session->word_statuses) : 0 }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-xs text-gray-400">لا يوجد تسميع مسجّل لهذه الدفعة بعد — افتح صفحات الجزء وسجّل الأخطاء.</p>
            @endif
        @endif
    </div>

    {{-- ٢. الاختبار التراكمي --}}
    @if ($currentBatch)
        <div id="batch-test" class="bg-white rounded-2xl shadow p-5 scroll-mt-6">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-sky-100 text-sky-800 text-xs font-black flex items-center justify-center">٢</span>
                    <h3 class="font-bold text-gray-700">{{ $isRetest ? 'اختبار الإعادة' : 'الاختبار التراكمي' }}</h3>
                </div>
                <span class="text-[11px] font-bold text-gray-400">حد النجاح {{ $threshold }}%</span>
            </div>

            @error('results')
                <p class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2 mb-3">{{ $message }}</p>
            @enderror

            @if ($currentBatch->isPassed())
                <p class="text-sm text-emerald-700">
                    ✓ اجتاز الطالب هذه الدفعة بنسبة {{ $scoreLabel($lastTest?->score) }}% — الدفعة التالية مفتوحة للتسميع.
                </p>
            @elseif ($currentBatch->isReadyForTest())
                @php
                    $retakeModeChoice = $isRetest && ($fullRetakeAvailable ?? false) && ($fullRetakeJuz ?? []) !== [];
                    $testRows = $retakeModeChoice ? $fullRetakeJuz : $testScopeJuz;
                    $failedLookup = collect($failedJuz)->map(fn ($value) => (int) $value)->all();
                @endphp

                @if ($lastTest)
                    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-800 mb-3">
                        المحاولة السابقة: <b>{{ $scoreLabel($lastTest->score) }}%</b>
                        — الأجزاء الراسبة: <b>{{ $failedJuz === [] ? '—' : implode('، ', $failedJuz) }}</b>
                        @if ($retakeModeChoice)
                            — هذه محاولة إعادة: اختر إعادة الأجزاء الراسبة فقط أو الاختبار التراكمي كاملًا.
                        @else
                            — هذه محاولة إعادة، وتشمل الأجزاء: <b>{{ implode('، ', $testScopeJuz) }}</b>.
                        @endif
                    </div>
                @else
                    <p class="text-xs text-gray-500 mb-3">
                        سجّل نتيجة كل جزء من النطاق التراكمي: <b class="text-gray-700">{{ implode('، ', $testScopeJuz) }}</b>
                        — والرسوب في أي جزء يُعيده لإعادة التسميع فقط.
                    </p>
                @endif

                @if ($actions['test'] ?? null)
                <form method="POST" action="{{ $actions['test']($currentBatch) }}" class="space-y-2" data-retake-test-form>
                    @csrf

                    @if ($retakeModeChoice)
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-3 mb-2">
                            <div class="text-xs font-black text-amber-900 mb-2">نطاق اختبار الإعادة:</div>
                            <div class="flex flex-wrap items-center gap-4 text-sm">
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" name="scope" value="failed" checked class="text-amber-600 focus:ring-amber-500" data-retake-scope>
                                    <span class="font-bold text-amber-900">الأجزاء الراسبة فقط ({{ implode('، ', $failedJuz) }})</span>
                                </label>
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" name="scope" value="full" class="text-amber-600 focus:ring-amber-500" data-retake-scope>
                                    <span class="font-bold text-amber-900">
                                        الاختبار التراكمي كاملًا ({{ count($fullRetakeJuz) }} أجزاء: {{ $fullRetakeJuz[0] }}–{{ end($fullRetakeJuz) }})
                                    </span>
                                </label>
                            </div>
                        </div>
                    @endif

                    @foreach ($testRows as $juz)
                        @php
                            $juzRange = QuranJuzMap::pageRange($juz);
                            $isFailedJuz = in_array((int) $juz, $failedLookup, true);
                        @endphp
                        <div class="rounded-xl border p-3 flex flex-wrap items-center justify-between gap-2 {{ $isFailedJuz ? 'border-red-200 bg-red-50/60' : 'border-gray-200' }}"
                            data-retake-row="{{ $juz }}" @if ($retakeModeChoice && ! $isFailedJuz) data-retake-extra @endif>
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
                        <button data-retake-submit class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-6 py-2 rounded-xl">
                            {{ $isRetest ? 'تسجيل نتيجة اختبار الإعادة' : 'تسجيل نتيجة الاختبار' }}
                        </button>
                    </div>
                </form>
                @else
                    <p class="text-sm text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                        لا تملك صلاحية تسجيل نتيجة الاختبار — تواصل مع مدير الجامع.
                    </p>
                @endif
            @elseif ($currentBatch->isNeedsRepeat())
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                    رسب الطالب في الأجزاء: <b>{{ $failedJuz === [] ? '—' : implode('، ', $failedJuz) }}</b>
                    — الاختبار مقفل حتى إعادة تسميع الأجزاء الراسبة من قسم «١. التسميع مع المعلم» أعلاه.
                </div>
            @else
                <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                    لا يُفتح الاختبار التراكمي قبل اكتمال تسميع أجزاء الدفعة الخمسة كاملة.
                </p>
            @endif
        </div>
    @endif

    {{-- الاختبار المباشر للأجزاء المحفوظة مسبقاً --}}
    @if ($placementTestAllowed && $currentBatch && ($actions['placement'] ?? null))
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
                : collect($placementScope)->flatMap(fn (array $entry) => range($entry['from_juz'], $entry['to_juz']))->unique()->sort()->values()->all();

            $placementJuzLabel = count($placementJuz) > 1 && $placementJuz === range($placementJuz[0], end($placementJuz))
                ? $placementJuz[0].'–'.end($placementJuz)
                : implode('، ', $placementJuz);
        @endphp
        <div id="placement-test" class="bg-white rounded-2xl shadow p-5 scroll-mt-6">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-sky-100 text-sky-800 text-xs font-black flex items-center justify-center">↯</span>
                    <h3 class="font-bold text-gray-700">اختبار مباشر للأجزاء المحفوظة مسبقاً</h3>
                </div>
                <span class="text-[11px] font-bold text-gray-400">حد النجاح {{ $threshold }}%</span>
            </div>
            <p class="text-xs text-gray-500 mb-3">
                الطالب مسجّل بحفظ الأجزاء <b class="text-gray-700">{{ $placementJuzLabel }}</b> ({{ count($placementScope) }} دفعة) من ملفه — يمكن اختباره مباشرة دون تسميع.
                النجاح يثبّت الدفعة، والرسوب في أي جزء يُنشئ إعادة تسميع للجزء الراسب فقط.
            </p>

            @error('results')
                <p class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2 mb-3">{{ $message }}</p>
            @enderror

            <form method="POST" action="{{ $actions['placement']($currentBatch) }}" class="space-y-3" data-placement-test-form>
                @csrf
                @foreach ($placementScope as $entry)
                    <fieldset class="rounded-xl border border-gray-200 overflow-hidden" data-placement-batch>
                        <legend class="sr-only">الدفعة {{ $entry['batch_number'] }} (الأجزاء {{ $entry['from_juz'] }}–{{ $entry['to_juz'] }})</legend>
                        <div class="flex flex-wrap items-center justify-between gap-2 bg-gray-50 px-3 py-2 border-b border-gray-100">
                            <span class="text-xs font-black text-gray-700">الدفعة {{ $entry['batch_number'] }} (الأجزاء {{ $entry['from_juz'] }}–{{ $entry['to_juz'] }})</span>
                            <span data-placement-batch-state class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">سيُثبَّت ✓</span>
                        </div>
                        <div class="grid gap-2 p-3 md:grid-cols-2">
                            @foreach (range($entry['from_juz'], $entry['to_juz']) as $juz)
                                @php $juzRange = QuranJuzMap::pageRange($juz); @endphp
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

                <div class="flex flex-wrap items-center justify-between gap-3">
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

                            if (failed.length && !window.confirm('سيتم تسجيل رسوب في الأجزاء: ' + failed.join('، ') + ' وإعادة تسميع الأجزاء الراسبة. متابعة؟')) {
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
        </div>
    @endif

    {{-- سجل الاختبارات --}}
    <div class="bg-white rounded-2xl shadow p-5">
        <h3 class="font-bold text-gray-700 mb-3">سجل الاختبارات</h3>

        @if ($tests->isEmpty())
            <p class="text-sm text-gray-400">لا توجد اختبارات مسجّلة بعد.</p>
        @else
            <div class="space-y-3">
                @foreach ($tests as $test)
                    <div class="rounded-xl border {{ $test->isPass() ? 'border-emerald-200 bg-emerald-50' : 'border-red-200 bg-red-50' }} p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="text-sm font-bold {{ $test->isPass() ? 'text-emerald-900' : 'text-red-900' }}">
                                {{ $test->listeningBatch?->label() ?? 'دفعة' }}
                                — {{ $test->isPass() ? 'ناجح' : 'يحتاج إعادة' }}
                                ({{ $scoreLabel($test->score) }}%)
                            </div>
                            <div class="text-[11px] text-gray-500">
                                {{ $test->tested_at?->format('Y-m-d H:i') }} — الممتحن: {{ $test->examiner?->name ?? '—' }}
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 mt-2">
                            @foreach ($test->items as $item)
                                <span class="text-[11px] font-bold px-2 py-0.5 rounded-full {{ $item->result === QuranListeningTestResult::Pass ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-700' }}">
                                    الجزء {{ $item->juz }}: {{ $item->result === QuranListeningTestResult::Pass ? 'ناجح' : 'إعادة' }}
                                </span>
                            @endforeach
                        </div>
                        @if ($test->notes)
                            <p class="text-[11px] text-gray-500 mt-2">{{ $test->notes }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            document.querySelectorAll('[data-program-item]').forEach(function (root) {
                const toggle = root.querySelector('[data-partial-toggle]');
                const form = root.querySelector('[data-partial-form]');

                if (!toggle || !form) {
                    return;
                }

                const from = form.querySelector('[data-partial-from]');
                const to = form.querySelector('[data-partial-to]');
                const min = parseInt(from.min, 10);
                const max = parseInt(from.max, 10);

                function clamp() {
                    let f = parseInt(from.value, 10);
                    let t = parseInt(to.value, 10);

                    if (!(f >= min)) f = min;
                    if (f > max) f = max;
                    if (!(t >= f)) t = f;
                    if (t > max) t = max;

                    from.value = f;
                    to.value = t;
                }

                toggle.addEventListener('click', function () {
                    form.classList.toggle('hidden');
                });

                from.addEventListener('change', clamp);
                to.addEventListener('change', clamp);

                const preview = form.querySelector('[data-partial-preview]');
                const modal = document.querySelector('[data-pages-modal]');
                const body = modal ? modal.querySelector('[data-pages-body]') : null;

                if (preview && modal && body) {
                    preview.addEventListener('click', async function () {
                        clamp();

                        const f = parseInt(from.value, 10);
                        const t = parseInt(to.value, 10);

                        modal.classList.remove('hidden');
                        body.innerHTML = '<p class="text-center text-gray-500 py-10 font-bold">جارٍ التحميل…</p>';

                        try {
                            const url = preview.dataset.previewUrl.replace('__PAGE__', f) + '?to=' + t;
                            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                            body.innerHTML = res.ok ? await res.text() : '<p class="text-center text-red-600 py-10 font-bold">تعذّر تحميل المعاينة</p>';
                        } catch (e) {
                            body.innerHTML = '<p class="text-center text-red-600 py-10 font-bold">تعذّر تحميل المعاينة</p>';
                        }
                    });
                }
            });

            const pagesModal = document.querySelector('[data-pages-modal]');

            if (pagesModal) {
                pagesModal.querySelectorAll('[data-pages-close]').forEach(function (el) {
                    el.addEventListener('click', function () { pagesModal.classList.add('hidden'); });
                });

                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' && !pagesModal.classList.contains('hidden')) {
                        pagesModal.classList.add('hidden');
                    }
                });
            }

            const testForm = document.querySelector('[data-retake-test-form]');

            if (testForm) {
                const radios = Array.from(testForm.querySelectorAll('[data-retake-scope]'));

                if (radios.length) {
                    const extras = Array.from(testForm.querySelectorAll('[data-retake-extra]'));
                    const submit = testForm.querySelector('[data-retake-submit]');

                    function applyRetakeScope() {
                        const full = (radios.find((radio) => radio.checked)?.value ?? 'failed') === 'full';

                        extras.forEach(function (row) {
                            row.classList.toggle('hidden', !full);

                            const pass = row.querySelector('input[value="pass"]');

                            if (pass && !full) {
                                pass.checked = true;
                            }
                        });

                        if (submit) {
                            submit.textContent = full ? 'تسجيل نتيجة الاختبار التراكمي كاملًا' : 'تسجيل نتيجة اختبار الإعادة';
                        }
                    }

                    radios.forEach((radio) => radio.addEventListener('change', applyRetakeScope));
                    applyRetakeScope();
                }
            }
        })();
    </script>
@endpush
