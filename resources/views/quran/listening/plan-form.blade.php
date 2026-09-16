@php
    $teacherOptions = $teachers ?? collect();
    $studentShift = old('study_session_id', $selectedStudent->study_session_id ?? $currentSessionId);
    $oldItems = old('items', []);
    $khamsatByJuz = collect($khamsat ?? [])->keyBy('juz');
    $memorizedCount = count($memorizedJuz ?? []);
@endphp

<div class="space-y-6">
    <form method="GET" action="{{ $pickerRoute }}" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-gray-700 mb-1">الطالب <span class="text-red-500">*</span></label>
                <select name="student_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">— اختر الطالب —</option>
                    @foreach($students as $student)
                        <option value="{{ $student->id }}" @selected($selectedStudent && $selectedStudent->id === $student->id)>{{ $student->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <button type="submit" class="w-full bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">عرض الأجزاء وبناء الخطة</button>
            </div>
        </div>
    </form>

    @if($selectedStudent)
        <form method="POST" action="{{ $storeRoute }}" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-6" data-listening-form>
            @csrf
            <input type="hidden" name="student_id" value="{{ $selectedStudent->id }}">

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                @if($teacherOptions->count() > 1)
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">الأستاذ المسؤول <span class="text-red-500">*</span></label>
                        <select name="teacher_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                            <option value="">— اختر الأستاذ —</option>
                            @foreach($teacherOptions as $teacher)
                                <option value="{{ $teacher->id }}" @selected(old('teacher_id') === $teacher->id)>{{ $teacher->name }}</option>
                            @endforeach
                        </select>
                        @error('teacher_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                @elseif($teacherOptions->count() === 1)
                    <input type="hidden" name="teacher_id" value="{{ $teacherOptions->first()->id }}">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">الأستاذ المسؤول</label>
                        <div class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-gray-700">{{ $teacherOptions->first()->name }}</div>
                    </div>
                @else
                    <div class="md:col-span-4">
                        <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">لا يوجد أستاذ متاح في هذا الدوام — أضف أستاذاً أولاً.</p>
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">الدوام <span class="text-red-500">*</span></label>
                    <select name="study_session_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        <option value="">— اختر الدوام —</option>
                        @foreach($sessions as $session)
                            <option value="{{ $session->id }}" @selected($studentShift === $session->id)>{{ $session->name }}</option>
                        @endforeach
                    </select>
                    @error('study_session_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">عنوان الخطة (اختياري)</label>
                    <input type="text" name="title" maxlength="150" value="{{ old('title') }}" placeholder="خطة استماع — الجزء ١ و٢" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">العناصر المفتوحة معاً (الدفعة)</label>
                    <select name="gate_size" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        @foreach(range(1, \App\Services\QuranListeningService::MAX_GATE_SIZE) as $size)
                            <option value="{{ $size }}" @selected((int) old('gate_size', 1) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-gray-400 mt-1">مثال: ٢ تعني أن العنصرين يُستمعان ويُختبران معاً، ثم يُفتح ما بعدهما — ولا يُفتح جديد حتى ينجح الجميع.</p>
                </div>

                <div class="md:col-span-4">
                    <label class="block text-sm font-bold text-gray-700 mb-1">ملاحظات</label>
                    <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div>
                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                    <h3 class="font-bold text-gray-800">اختر العناصر: جديد بنطاق صفحات أو مراجعة 5</h3>
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-xs text-gray-400">
                            المحفوظ: <b class="text-emerald-700">{{ $memorizedCount }}</b> جزءاً —
                            المحدد: <b data-juz-count>0</b> عنصراً (<b data-review-count>0</b> مراجعة)
                        </span>
                        @if($memorizedCount > 0)
                            <button type="button" data-autofill-memorized
                                class="text-xs font-bold px-3 py-1.5 rounded-lg bg-emerald-100 text-emerald-800 hover:bg-emerald-200">
                                توليد تلقائي من الأجزاء المحفوظة
                            </button>
                        @endif
                    </div>
                </div>

                @if($memorizedCount === 0)
                    <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-3">
                        لا توجد أجزاء محفوظة مسجّلة لهذا الطالب — خيار «مراجعة 5» يُفتح بعد تسجيل الحفظ.
                        @isset($memorizationRoute)
                            <a href="{{ $memorizationRoute }}" class="font-bold underline">تسجيل الأجزاء المحفوظة</a>
                        @endisset
                    </p>
                @endif

                @error('items') <p class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2 mb-3">{{ $message }}</p> @enderror

                <div class="space-y-2 max-h-[36rem] overflow-y-auto pe-1" data-juz-picker>
                    @foreach($juzOptions as $juzRow)
                        @php
                            $juz = $juzRow['juz'];
                            $oldRow = $oldItems[$juz] ?? [];
                            $checked = isset($oldRow['selected']);
                            $type = $oldRow['type'] ?? 'new';
                            $from = $oldRow['from_page'] ?? $juzRow['from_page'];
                            $to = $oldRow['to_page'] ?? $juzRow['to_page'];
                            $oldKhamsat = array_map('intval', array_keys((array) ($oldRow['khamsat'] ?? [])));
                            $khamsatRow = $khamsatByJuz->get($juz);
                        @endphp
                        <div @class([
                                'rounded-xl border p-3 transition',
                                'border-emerald-300 bg-emerald-50/50' => $checked,
                                'border-gray-200 bg-gray-50' => ! $checked,
                            ])
                            data-juz-row
                            data-juz="{{ $juz }}"
                            data-min="{{ $juzRow['from_page'] }}"
                            data-max="{{ $juzRow['to_page'] }}"
                            data-memorized="{{ $juzRow['memorized'] ? '1' : '0' }}">
                            <label class="flex flex-wrap items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="items[{{ $juz }}][selected]" value="1"
                                    @checked($checked) data-juz-toggle
                                    class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                <span class="font-bold text-gray-800">الجزء {{ $juz }}</span>
                                <span class="text-xs text-gray-500">صفحات {{ $juzRow['from_page'] }}–{{ $juzRow['to_page'] }} ({{ $juzRow['pages'] }} صفحة)</span>
                                @if($juzRow['memorized'])
                                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-bold">محفوظ ✓</span>
                                @else
                                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-200 text-gray-600 font-bold">غير محفوظ</span>
                                @endif
                            </label>
                            <input type="hidden" name="items[{{ $juz }}][juz]" value="{{ $juz }}">

                            <div class="mt-3 space-y-3" data-juz-body @if(! $checked) hidden @endif>
                                <div class="flex flex-wrap items-center gap-4 text-sm">
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="radio" name="items[{{ $juz }}][type]" value="new"
                                            @checked($type !== 'review') data-item-type
                                            class="text-emerald-600 focus:ring-emerald-500">
                                        <span class="font-bold text-gray-700">جديد (نطاق صفحات)</span>
                                    </label>
                                    <label @class([
                                        'flex items-center gap-1.5',
                                        'cursor-pointer' => $juzRow['memorized'],
                                        'cursor-not-allowed text-gray-400' => ! $juzRow['memorized'],
                                    ])>
                                        <input type="radio" name="items[{{ $juz }}][type]" value="review"
                                            @checked($type === 'review') @disabled(! $juzRow['memorized']) data-item-type
                                            class="text-emerald-600 focus:ring-emerald-500">
                                        <span class="font-bold">مراجعة 5 (خمسات الجزء)</span>
                                    </label>
                                    @error("items.{$juz}.type") <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                                </div>

                                <div class="grid grid-cols-2 gap-3" data-item-new @if($type === 'review') hidden @endif>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 mb-1">من صفحة</label>
                                        <input type="number" name="items[{{ $juz }}][from_page]"
                                            min="{{ $juzRow['from_page'] }}" max="{{ $juzRow['to_page'] }}"
                                            value="{{ $from }}" data-juz-from
                                            class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
                                        @error("items.{$juz}.from_page") <p class="text-[11px] text-red-600 mt-1">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 mb-1">إلى صفحة</label>
                                        <input type="number" name="items[{{ $juz }}][to_page]"
                                            min="{{ $juzRow['from_page'] }}" max="{{ $juzRow['to_page'] }}"
                                            value="{{ $to }}" data-juz-to
                                            class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
                                        @error("items.{$juz}.to_page") <p class="text-[11px] text-red-600 mt-1">{{ $message }}</p> @enderror
                                    </div>
                                </div>

                                <div data-item-review @if($type !== 'review') hidden @endif>
                                    @if($juzRow['memorized'] && $khamsatRow)
                                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
                                            @foreach($khamsatRow['khamsat'] as $khamsa)
                                                @php
                                                    $disabled = ! $khamsa['unlocked'] || $khamsa['already_assigned'];
                                                    $khamsaChecked = in_array($khamsa['khamsa'], $oldKhamsat, true);
                                                @endphp
                                                <label @class([
                                                    'flex flex-col rounded-lg border px-3 py-2 text-sm transition',
                                                    'border-emerald-300 bg-white hover:border-emerald-500 cursor-pointer' => ! $disabled,
                                                    'border-gray-200 bg-gray-100 text-gray-400 cursor-not-allowed' => $disabled,
                                                    'ring-2 ring-emerald-500 border-emerald-500' => $khamsaChecked && ! $disabled,
                                                ])>
                                                    <span class="flex items-center gap-2 font-bold">
                                                        <input type="checkbox" name="items[{{ $juz }}][khamsat][{{ $khamsa['khamsa'] }}]" value="1"
                                                            @checked($khamsaChecked) @disabled($disabled) data-khamsa-toggle
                                                            class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                                        الخمسة {{ $khamsa['khamsa'] }}
                                                    </span>
                                                    <span class="text-[11px] mt-1">صفحات {{ $khamsa['from_page'] }}–{{ $khamsa['to_page'] }} ({{ $khamsa['pages'] }} صفحات)</span>
                                                    @if($khamsa['already_assigned'])
                                                        <span class="text-[11px] mt-1 text-amber-700 font-bold">قيد المراجعة</span>
                                                    @endif
                                                </label>
                                            @endforeach
                                        </div>
                                        @error("items.{$juz}.khamsat") <p class="text-[11px] text-red-600 mt-1">{{ $message }}</p> @enderror
                                    @else
                                        <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                                            سجّل الجزء {{ $juz }} محفوظاً للطالب أولاً لتخصيص خمساته.
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-8 py-2.5 rounded-xl">حفظ خطة الاستماع</button>
                <a href="{{ $indexRoute }}" class="text-gray-500 text-sm hover:underline">إلغاء</a>
            </div>
        </form>
    @else
        <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-8 text-center text-gray-400">
            اختر الطالب أولاً لعرض الأجزاء الثلاثين وحالة الحفظ وبناء الخطة.
        </div>
    @endif
</div>

@once
    @push('scripts')
        <script>
            (function () {
                const picker = document.querySelector('[data-juz-picker]');

                if (!picker) {
                    return;
                }

                const counter = document.querySelector('[data-juz-count]');
                const reviewCounter = document.querySelector('[data-review-count]');

                function rowType(row) {
                    const checked = row.querySelector('[data-item-type]:checked');

                    return checked ? checked.value : 'new';
                }

                function refreshRow(row) {
                    const toggle = row.querySelector('[data-juz-toggle]');
                    const body = row.querySelector('[data-juz-body]');
                    const newBox = row.querySelector('[data-item-new]');
                    const reviewBox = row.querySelector('[data-item-review]');
                    const type = rowType(row);

                    body.hidden = !toggle.checked;
                    newBox.hidden = type !== 'new';
                    reviewBox.hidden = type !== 'review';

                    row.classList.toggle('border-emerald-300', toggle.checked);
                    row.classList.toggle('bg-emerald-50/50', toggle.checked);
                    row.classList.toggle('border-gray-200', !toggle.checked);
                    row.classList.toggle('bg-gray-50', !toggle.checked);
                }

                function refreshCounter() {
                    let items = 0;
                    let reviews = 0;

                    picker.querySelectorAll('[data-juz-row]').forEach(function (row) {
                        const toggle = row.querySelector('[data-juz-toggle]');

                        if (!toggle.checked) {
                            return;
                        }

                        if (rowType(row) === 'review') {
                            reviews += row.querySelectorAll('[data-khamsa-toggle]:checked').length;
                        } else {
                            items += 1;
                        }
                    });

                    if (counter) {
                        counter.textContent = items + reviews;
                    }

                    if (reviewCounter) {
                        reviewCounter.textContent = reviews;
                    }
                }

                function clamp(input) {
                    const row = input.closest('[data-juz-row]');
                    const min = parseInt(row.dataset.min, 10);
                    const max = parseInt(row.dataset.max, 10);
                    let value = parseInt(input.value, 10);

                    if (isNaN(value)) {
                        value = min;
                    }

                    input.value = Math.min(Math.max(value, min), max);
                }

                picker.querySelectorAll('[data-juz-row]').forEach(function (row) {
                    const toggle = row.querySelector('[data-juz-toggle]');
                    const from = row.querySelector('[data-juz-from]');
                    const to = row.querySelector('[data-juz-to]');

                    toggle.addEventListener('change', function () {
                        refreshRow(row);
                        refreshCounter();
                    });

                    row.querySelectorAll('[data-item-type]').forEach(function (radio) {
                        radio.addEventListener('change', function () {
                            refreshRow(row);
                            refreshCounter();
                        });
                    });

                    row.querySelectorAll('[data-khamsa-toggle]').forEach(function (checkbox) {
                        checkbox.addEventListener('change', refreshCounter);
                    });

                    [from, to].forEach(function (input) {
                        if (!input) {
                            return;
                        }

                        input.addEventListener('change', function () {
                            clamp(input);

                            if (from.value !== '' && to.value !== '' && parseInt(from.value, 10) > parseInt(to.value, 10)) {
                                to.value = from.value;
                            }
                        });
                    });

                    refreshRow(row);
                });

                const autofill = document.querySelector('[data-autofill-memorized]');

                if (autofill) {
                    autofill.addEventListener('click', function () {
                        picker.querySelectorAll('[data-juz-row]').forEach(function (row) {
                            if (row.dataset.memorized !== '1') {
                                return;
                            }

                            const toggle = row.querySelector('[data-juz-toggle]');
                            const newRadio = row.querySelector('[data-item-type][value="new"]');

                            toggle.checked = true;

                            if (newRadio) {
                                newRadio.checked = true;
                            }

                            row.querySelector('[data-juz-from]').value = row.dataset.min;
                            row.querySelector('[data-juz-to]').value = row.dataset.max;
                            row.querySelectorAll('[data-khamsa-toggle]').forEach(function (checkbox) {
                                checkbox.checked = false;
                            });

                            refreshRow(row);
                        });

                        refreshCounter();
                    });
                }

                refreshCounter();
            })();
        </script>
    @endpush
@endonce
