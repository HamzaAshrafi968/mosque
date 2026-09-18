@php
    use App\Enums\ProgramType;
    use App\Enums\QuranListeningBatchStatus;
    use App\Enums\QuranListeningItemStatus;

    $program = $program ?? null;
    $states = $states ?? collect();
    $currentBatch = $currentBatch ?? null;
    $juzGrid = $juzGrid ?? collect();
    $summary = $summary ?? [];
    $listeningLog = $listeningLog ?? collect();
    $tests = $tests ?? collect();
    $actions = $actions ?? [];
    $canTest = $canTest ?? false;
    $canCancel = $canCancel ?? false;
    $minimumPassingPercentage = $minimumPassingPercentage ?? 80;

    $isCumulative = $program && in_array($program->type, [ProgramType::Qualifying, ProgramType::Ijazah], true);

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

    $scoreLabel = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    $tasmeeCount = $listeningLog->filter(fn ($item) => $item->quran_recitation_session_id !== null)->count();

    $programItemLabel = fn (?QuranListeningItemStatus $status) => match ($status) {
        QuranListeningItemStatus::Available => 'قيد التسميع',
        QuranListeningItemStatus::Listened => 'تم التسميع',
        default => $status?->label() ?? '—',
    };

    $programBatchLabel = fn (QuranListeningBatchStatus $status) => $status === QuranListeningBatchStatus::Listening
        ? 'قيد التسميع'
        : $status->label();

    $currentBatch = $currentBatch?->loadMissing([
        'items.listenedBy:id,name',
        'items.passedBy:id,name',
        'items.quranRecitationSession:id,result',
        'lastTest.items',
    ]);
@endphp

@if (! $program)
    <div class="bg-white rounded-2xl shadow p-8 text-center text-gray-500">
        اختر طالباً وبرنامجاً لعرض دورة البرنامج — «وين موصل» و«شو مسمع».
    </div>
@elseif ($isCumulative)
    @include('quran.programs.cycle-batch')
@else
    <div class="space-y-6">
        {{-- ترويسة البرنامج --}}
        <div class="bg-white rounded-2xl shadow p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-xl font-black text-gray-800">{{ $program->label() }}</h2>
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
                            onsubmit="return confirm('إلغاء {{ $program->label() }}؟')">
                            @csrf
                            <button class="text-xs font-bold text-red-700 border border-red-200 bg-red-50 hover:bg-red-100 px-3 py-2 rounded-lg">
                                إلغاء البرنامج
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        {{-- وين موصل: مؤشرات --}}
        <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
            <div class="bg-white rounded-2xl shadow p-4">
                <div class="text-[11px] font-bold text-gray-400">نسبة التقدم</div>
                <div class="text-2xl font-black text-emerald-700">{{ $summary['percentage'] ?? 0 }}%</div>
                <div class="mt-2 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                    <div class="h-full bg-emerald-500" style="width: {{ min(100, $summary['percentage'] ?? 0) }}%"></div>
                </div>
            </div>
            <div class="bg-white rounded-2xl shadow p-4">
                <div class="text-[11px] font-bold text-gray-400">الأجزاء المسمّعة</div>
                <div class="text-2xl font-black text-indigo-700">{{ $summary['listened_juz'] ?? 0 }} / {{ $summary['total_juz'] ?? 30 }}</div>
                <div class="text-[11px] text-gray-400 mt-1">ناجح: {{ $summary['passed_juz'] ?? 0 }}</div>
            </div>
            <div class="bg-white rounded-2xl shadow p-4">
                <div class="text-[11px] font-bold text-gray-400">الدفعات الناجحة</div>
                <div class="text-2xl font-black text-amber-700">{{ $summary['passed_batches'] ?? 0 }} / {{ $summary['total_batches'] ?? 6 }}</div>
                <div class="text-[11px] text-gray-400 mt-1">كل دفعة = 5 أجزاء</div>
            </div>
            <div class="bg-white rounded-2xl shadow p-4">
                <div class="text-[11px] font-bold text-gray-400">الجزء التالي</div>
                <div class="text-2xl font-black text-sky-700">{{ $summary['next_juz'] ?? '—' }}</div>
                <div class="text-[11px] text-gray-400 mt-1">{{ $summary['next_juz_label'] ?? 'لا يوجد — أكملت كل شيء' }}</div>
            </div>
            <div class="bg-white rounded-2xl shadow p-4">
                <div class="text-[11px] font-bold text-gray-400">جلسات التسميع</div>
                <div class="text-2xl font-black text-gray-700">{{ $tasmeeCount }}</div>
                <div class="text-[11px] text-gray-400 mt-1">مسجّلة مع الأخطاء</div>
            </div>
        </div>

        {{-- شبكة الدفعات الست --}}
        <div class="bg-white rounded-2xl shadow p-5">
            <h3 class="font-bold text-gray-700 mb-3">الدفعات الست</h3>
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
                    <div class="rounded-lg border p-2 text-center {{ $itemClasses($cell['status']) }}"
                        title="الجزء {{ $cell['juz'] }} — صفحات {{ $cell['from_page'] }}–{{ $cell['to_page'] }}{{ $cell['listened_by'] ? ' — سمعه: '.$cell['listened_by'] : '' }}">
                        <div class="font-black text-sm">{{ $cell['juz'] }}</div>
                        <div class="text-[10px] leading-tight">{{ $cell['status'] ? $programItemLabel($cell['status']) : '—' }}</div>
                        @if ($cell['batch_number'])
                            <div class="text-[9px] opacity-60">د{{ $cell['batch_number'] }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- الدفعة الحالية: التسميع --}}
        @if ($currentBatch)
            <div class="bg-white rounded-2xl shadow p-5">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-800 text-xs font-black flex items-center justify-center">١</span>
                        <h3 class="font-bold text-gray-700">تسميع {{ $currentBatch->label() }}</h3>
                    </div>
                    <span class="text-[11px] font-bold text-gray-400">
                        {{ $currentBatch->items->filter(fn ($item) => $item->isListened() || $item->isPassed())->count() }} / {{ $currentBatch->items->count() }} تم تسميعه
                    </span>
                </div>

                <div class="space-y-2">
                    @foreach ($currentBatch->items as $item)
                        <div class="rounded-xl border border-gray-200 p-3 flex flex-wrap items-center justify-between gap-3">
                            <div class="min-w-40">
                                <div class="font-bold text-gray-800 text-sm">{{ $item->label() }}</div>
                                <div class="text-[11px] text-gray-400">
                                    {{ $programItemLabel($item->status) }}
                                    @if ($item->listened_at)
                                        — سجّله: {{ $item->listenedBy?->name ?? '—' }} ({{ $item->listened_at->format('Y-m-d') }})
                                    @endif
                                    @if ($item->quranRecitationSession?->result)
                                        — التقدير: {{ $item->quranRecitationSession->result->label() }}
                                    @endif
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" data-program-pages
                                    data-url="{{ route('quran.pages.preview', ['page' => '__PAGE__']) }}"
                                    data-from="{{ $item->from_page }}"
                                    data-to="{{ $item->to_page }}"
                                    data-label="صفحات الجزء {{ $item->juz }} ({{ $item->from_page }}–{{ $item->to_page }})"
                                    class="text-xs font-bold text-pine-700 border border-pine-200 bg-pine-50 hover:bg-pine-100 px-3 py-2 rounded-lg">
                                    صفحات المصحف
                                </button>
                                @if ($program->isActive() && $item->canBeListened() && ($actions['tasmee'] ?? null))
                                    <a href="{{ $actions['tasmee']($item) }}"
                                        class="text-xs font-bold text-white bg-emerald-700 hover:bg-emerald-800 px-3 py-2 rounded-lg">
                                        فتح الصفحات وتسجيل الأخطاء
                                    </a>
                                @elseif ($item->isPassed())
                                    <span class="text-[11px] font-bold text-emerald-700">✓ ناجح</span>
                                @elseif ($item->isListened())
                                    <span class="text-[11px] font-bold text-indigo-700">بانتظار الاختبار</span>
                                @else
                                    <span class="text-[11px] font-bold text-gray-400">مقفل</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @elseif ($program->isCompleted())
            <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-5 text-emerald-900 font-bold">
                ما شاء الله — أتمّ الطالب {{ $program->label() }} كاملاً (جميع الدفعات الست).
            </div>
        @endif

        {{-- اختبار الدفعة الحالية --}}
        @if ($canTest && $currentBatch)
            @include('quran.programs.test-form', [
                'batch' => $currentBatch,
                'actions' => $actions,
                'minimumPassingPercentage' => $minimumPassingPercentage,
            ])
        @endif

        {{-- شو مسمع: سجل التسميع --}}
        <div class="bg-white rounded-2xl shadow p-5">
            <h3 class="font-bold text-gray-700 mb-3">سجل التسميع — شو مسمع</h3>

            @if ($listeningLog->isEmpty())
                <p class="text-sm text-gray-400">لا يوجد تسميع مسجّل بعد.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-right text-[11px] text-gray-400 border-b border-gray-100">
                                <th class="py-2">الجزء</th>
                                <th class="py-2">الدفعة</th>
                                <th class="py-2">التاريخ</th>
                                <th class="py-2">سجّله</th>
                                <th class="py-2">التقدير</th>
                                <th class="py-2">الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($listeningLog as $item)
                                <tr class="border-b border-gray-50">
                                    <td class="py-2 font-bold text-gray-700">الجزء {{ $item->juz }}</td>
                                    <td class="py-2 text-gray-500">{{ $item->batch?->label() ?? '—' }}</td>
                                    <td class="py-2 text-gray-500">{{ $item->listened_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                    <td class="py-2 text-gray-500">{{ $item->listenedBy?->name ?? '—' }}</td>
                                    <td class="py-2 text-gray-500">{{ $item->quranRecitationSession?->result?->label() ?? '—' }}</td>
                                    <td class="py-2">
                                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full {{ $item->isPassed() ? 'bg-emerald-100 text-emerald-800' : ($item->isNeedsRepeat() ? 'bg-red-100 text-red-700' : 'bg-indigo-100 text-indigo-800') }}">
                                            {{ $programItemLabel($item->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

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
                                    <span class="text-[11px] font-bold px-2 py-0.5 rounded-full {{ $item->result === \App\Enums\QuranListeningTestResult::Pass ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-700' }}">
                                        الجزء {{ $item->juz }}: {{ $item->result === \App\Enums\QuranListeningTestResult::Pass ? 'ناجح' : 'إعادة' }}
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

        {{-- معاينة صفحات المصحف: تُفتح من أزرار «صفحات المصحف» --}}
        <div data-program-pages-modal class="hidden fixed inset-0 z-[60]">
            <div class="absolute inset-0 bg-pine-950/70 backdrop-blur-sm" data-program-pages-close></div>
            <div class="relative mx-auto my-6 w-[min(96vw,56rem)] max-h-[90vh] flex flex-col bg-white rounded-3xl shadow-2xl overflow-hidden">
                <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                    <h3 class="font-black text-pine-950" data-program-pages-title>صفحات المصحف</h3>
                    <div class="flex items-center gap-2">
                        <a data-program-pages-full href="#" class="text-xs font-bold text-emerald-700 hover:underline hidden">فتح في صفحة كاملة</a>
                        <button type="button" data-program-pages-close class="p-2 rounded-xl hover:bg-gray-100" aria-label="إغلاق">
                            <x-icon name="x" class="w-5 h-5" />
                        </button>
                    </div>
                </div>
                <div data-program-pages-body class="p-4 overflow-y-auto bg-[#f4f6f4]"></div>
            </div>
        </div>

        @once
            @push('scripts')
                <script>
                    (function () {
                        const modal = document.querySelector('[data-program-pages-modal]');

                        if (!modal) {
                            return;
                        }

                        const body = modal.querySelector('[data-program-pages-body]');
                        const title = modal.querySelector('[data-program-pages-title]');
                        const full = modal.querySelector('[data-program-pages-full]');

                        function close() {
                            modal.classList.add('hidden');
                            body.innerHTML = '';
                        }

                        modal.querySelectorAll('[data-program-pages-close]').forEach(function (el) {
                            el.addEventListener('click', close);
                        });

                        document.addEventListener('keydown', function (event) {
                            if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                                close();
                            }
                        });

                        document.querySelectorAll('[data-program-pages]').forEach(function (button) {
                            button.addEventListener('click', async function () {
                                const from = parseInt(button.dataset.from, 10);
                                const to = parseInt(button.dataset.to, 10);

                                if (!(from >= 1 && to >= from)) {
                                    return;
                                }

                                title.textContent = button.dataset.label || 'صفحات المصحف';

                                if (full) {
                                    full.href = '{{ url('quran/pages') }}/' + from;
                                    full.classList.remove('hidden');
                                }

                                modal.classList.remove('hidden');
                                body.innerHTML = '<p class="text-center text-gray-500 py-10 font-bold">جارٍ التحميل…</p>';

                                try {
                                    const url = button.dataset.url.replace('__PAGE__', from) + '?to=' + to;
                                    const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                                    body.innerHTML = response.ok
                                        ? await response.text()
                                        : '<p class="text-center text-red-600 py-10 font-bold">تعذّر تحميل الصفحات</p>';
                                } catch (error) {
                                    body.innerHTML = '<p class="text-center text-red-600 py-10 font-bold">تعذّر تحميل الصفحات</p>';
                                }
                            });
                        });
                    })();
                </script>
            @endpush
        @endonce
    </div>
@endif
