@extends('layouts.app')

@section('title', 'الجداول الدراسية')

@php
    $days = [0 => 'الأحد', 1 => 'الاثنين', 2 => 'الثلاثاء', 3 => 'الأربعاء', 4 => 'الخميس', 5 => 'الجمعة', 6 => 'السبت'];
    $selectedDays = array_map('intval', (array) old('days', []));
    $nextDateFor = function (int $day): string {
        $today = now();

        return $today->dayOfWeek === $day ? $today->toDateString() : $today->copy()->next($day)->toDateString();
    };
    $programData = $programs->mapWithKeys(fn ($program) => [
        $program->id => [
            'periods' => $program->periods->map(fn ($period) => [
                'id' => $period->id,
                'name' => $period->name,
                'starts_at' => $period->starts_at ? substr($period->starts_at, 0, 5) : null,
                'ends_at' => $period->ends_at ? substr($period->ends_at, 0, 5) : null,
            ])->values(),
            'ends_on' => $program->ends_on?->format('Y/m/d'),
        ],
    ]);
@endphp

@section('content')
@php
    $authorization = app(\App\Services\AuthorizationService::class);
    $can = fn (string $permission) => $authorization->can(auth()->user(), $permission);
@endphp
<div class="bg-white rounded-xl shadow overflow-hidden p-4 mb-6">
    <form method="GET" action="{{ route('admin.schedules.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end mb-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الصف</label>
            <select name="classroom_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="">الكل</option>
                @foreach($classrooms as $classroom)
                    <option value="{{ $classroom->id }}" @selected(request('classroom_id') == $classroom->id)>{{ $classroom->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">المعلم</label>
            <select name="teacher_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="">الكل</option>
                @foreach($teachers as $teacher)
                    <option value="{{ $teacher->id }}" @selected(request('teacher_id') == $teacher->id)>{{ $teacher->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">البرنامج/التخصص</label>
            <select name="program_id" id="filter-program" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="">الكل</option>
                @foreach($programs as $program)
                    <option value="{{ $program->id }}" @selected(request('program_id') == $program->id)>{{ $program->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الدوام</label>
            <select name="study_session_id" id="filter-session" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="">كل الدوام</option>
                @foreach($studySessions as $session)
                    <option value="{{ $session->id }}" @selected((string) $currentSessionId === (string) $session->id)>{{ $session->display_name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg w-full">بحث</button>
        </div>
    </form>

    @if ($can('schedule.create'))
    <details class="mb-2" id="schedule-add-form" @if($errors->any()) open @endif>
        <summary class="cursor-pointer text-emerald-700 font-bold mb-2">إضافة حصة جديدة</summary>
        <form method="POST" action="{{ route('admin.schedules.store') }}" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">البرنامج/التخصص</label>
                <select name="program_id" id="schedule-program" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">— بدون برنامج —</option>
                    @foreach($programs as $program)
                        <option value="{{ $program->id }}" data-ends-on="{{ $program->ends_on?->format('Y/m/d') }}" @selected(old('program_id') == $program->id)>{{ $program->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الفترة</label>
                <select name="program_period_id" id="schedule-period" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">— بدون فترة —</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الدوام</label>
                <select name="study_session_id" id="schedule-session" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">بدون دوام</option>
                    @foreach($studySessions as $session)
                        <option value="{{ $session->id }}" @selected(old('study_session_id', $currentSessionId) == $session->id)>{{ $session->display_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الصف</label>
                <select name="classroom_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">اختر الصف</option>
                    @foreach($classrooms as $classroom)
                        <option value="{{ $classroom->id }}" @selected(old('classroom_id') == $classroom->id)>{{ $classroom->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الشعبة</label>
                <select name="section_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">كل الشعب</option>
                    @foreach($classrooms as $classroom)
                        @foreach($classroom->sections as $section)
                            <option value="{{ $section->id }}" @selected(old('section_id') == $section->id)>{{ $classroom->name }} - {{ $section->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">المادة <span class="text-gray-400 text-xs">(اختياري عند اختيار برنامج)</span></label>
                <select name="subject_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">اختر المادة</option>
                    @foreach($subjects as $subject)
                        <option value="{{ $subject->id }}" @selected(old('subject_id') == $subject->id)>{{ $subject->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">المعلم</label>
                <select name="teacher_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">اختر المعلم</option>
                    @foreach($teachers as $teacher)
                        <option value="{{ $teacher->id }}" @selected(old('teacher_id') == $teacher->id)>{{ $teacher->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">وقت البداية <span class="text-gray-400 text-xs">(تلقائي مع الفترة)</span></label>
                <input type="time" name="starts_at" id="schedule-starts" value="{{ old('starts_at') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">وقت النهاية <span class="text-gray-400 text-xs">(تلقائي مع الفترة)</span></label>
                <input type="time" name="ends_at" id="schedule-ends" value="{{ old('ends_at') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ البداية <span class="text-gray-400 text-xs">(افتراضياً اليوم)</span></label>
                <input type="date" name="starts_on" id="schedule-starts-on" value="{{ old('starts_on', now()->toDateString()) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">مدة الصلاحية</label>
                <select name="duration" id="schedule-duration" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    @foreach($durations as $duration)
                        <option value="{{ $duration->value }}" @selected(old('duration', 'open') === $duration->value)>{{ $duration->label() }}</option>
                    @endforeach
                </select>
                <p id="schedule-course-hint" class="hidden text-xs mt-1"></p>
            </div>
            <div class="md:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">أيام الأسبوع <span class="text-gray-400 text-xs">(اختر يوماً واحداً أو أكثر — تُضاف حصة لكل يوم بنفس المدة)</span></label>
                <div class="flex flex-wrap gap-x-6 gap-y-2 border border-gray-200 rounded-lg px-3 py-2">
                    @foreach($days as $key => $day)
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="days[]" value="{{ $key }}" @checked(in_array($key, $selectedDays, true))
                                   class="rounded border-gray-300 text-emerald-700 focus:ring-emerald-500">
                            {{ $day }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="md:col-span-3">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg w-full md:w-auto md:px-8">إضافة الحصة</button>
            </div>
        </form>
    </details>
    @endif

</div>

<div class="mb-4">
    <button onclick="window.print()" class="bg-gray-700 hover:bg-gray-800 text-white font-bold px-4 py-2 rounded-lg">طباعة</button>
</div>

{{-- الشبكة الأسبوعية: الحصص موزّعة على أيام الأسبوع مع التغييرات القادمة --}}
<div class="bg-white rounded-xl shadow overflow-hidden mb-6">
    <div class="p-4 border-b flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-lg font-bold text-gray-800">الجدول الأسبوعي</h2>
        <p class="text-xs text-gray-400">اضغط «+ حصة» في أي يوم لإضافة حصة فيه، و«إلغاء/تأجيل» على أي حصة لتغيير يوم واحد فقط</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-gray-600">
                    @foreach($days as $dayName)
                        <th class="px-2 py-3 text-center border-r whitespace-nowrap">{{ $dayName }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <tr class="align-top">
                    @foreach($days as $dayNum => $dayName)
                        <td class="border-r border-t p-2 min-w-[190px]">
                            @forelse($schedules->where('day_of_week', $dayNum) as $schedule)
                                @php $slotExceptions = $exceptions->get($schedule->id, collect()); @endphp
                                <div class="rounded-lg border p-2 mb-2 bg-white" style="border-right: 3px solid {{ $schedule->program?->color ?: '#a7f3d0' }}">
                                    <div class="flex items-center justify-between gap-1">
                                        <span class="font-bold text-gray-800">{{ substr($schedule->starts_at, 0, 5) }}–{{ substr($schedule->ends_at, 0, 5) }}</span>
                                        @if($schedule->program)
                                            <span class="text-[10px] px-1.5 py-0.5 rounded" style="background: {{ ($schedule->program->color ?: '#475569') }}22; color: {{ $schedule->program->color ?: '#475569' }}">{{ $schedule->program->name }}</span>
                                        @endif
                                    </div>
                                    <div class="text-gray-700 font-medium mt-0.5">{{ $schedule->subject?->name ?? '—' }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">
                                        {{ $schedule->classroom?->name }}@if($schedule->section) — {{ $schedule->section->name }}@endif
                                    </div>
                                    <div class="text-xs text-gray-500">{{ $schedule->teacher?->name }}</div>
                                    @if($schedule->studySession)
                                        <div class="text-[10px] text-gray-400 mt-0.5">دوام {{ $schedule->studySession->display_name }}</div>
                                    @endif
                                    @if($schedule->validityLabel())
                                        <div class="mt-1">
                                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-600" title="مدة صلاحية الحصة">
                                                {{ $schedule->validityLabel() }}
                                            </span>
                                        </div>
                                    @endif

                                    @foreach($slotExceptions as $exception)
                                        <div class="mt-1 rounded px-1.5 py-1 text-[11px] {{ $exception->status->value === 'cancelled' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700' }}">
                                            <div class="flex items-center justify-between gap-1">
                                                <span>
                                                    {{ $exception->status->label() }} {{ $exception->date->format('m/d') }}
                                                    @if($exception->status->value === 'postponed')
                                                        ← {{ $exception->postponed_date?->format('m/d') }} {{ substr((string) $exception->postponed_starts_at, 0, 5) }}
                                                    @endif
                                                </span>
                                                @if ($can('schedule.update'))
                                                    <form method="POST" action="{{ route('admin.schedules.restore', $exception) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="hover:underline font-bold">إرجاع</button>
                                                    </form>
                                                @endif
                                            </div>
                                            @if($exception->reason)
                                                <div class="text-[10px] opacity-75">{{ $exception->reason }}</div>
                                            @endif
                                        </div>
                                    @endforeach

                                    @if ($can('schedule.update'))
                                    <details class="mt-1">
                                        <summary class="cursor-pointer text-[11px] text-gray-500">إلغاء/تأجيل</summary>
                                        @php $nextDate = $nextDateFor((int) $schedule->day_of_week); @endphp
                                        <form method="POST" action="{{ route('admin.schedules.cancel', $schedule) }}" class="mt-1 space-y-1">
                                            @csrf
                                            <input type="date" name="date" required value="{{ $nextDate }}" class="w-full border border-gray-300 rounded px-1.5 py-0.5 text-xs">
                                            <input type="text" name="reason" placeholder="سبب الإلغاء (اختياري)" class="w-full border border-gray-300 rounded px-1.5 py-0.5 text-xs">
                                            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white rounded py-0.5 text-xs">إلغاء هذا اليوم</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.schedules.postpone', $schedule) }}" class="mt-1 space-y-1 border-t border-dashed pt-1">
                                            @csrf
                                            <input type="hidden" name="date" value="{{ $nextDate }}">
                                            <input type="date" name="postponed_date" required value="{{ $nextDate }}" class="w-full border border-gray-300 rounded px-1.5 py-0.5 text-xs">
                                            <div class="flex gap-1">
                                                <input type="time" name="postponed_starts_at" required value="{{ substr($schedule->starts_at, 0, 5) }}" class="w-1/2 border border-gray-300 rounded px-1 py-0.5 text-xs">
                                                <input type="time" name="postponed_ends_at" required value="{{ substr($schedule->ends_at, 0, 5) }}" class="w-1/2 border border-gray-300 rounded px-1 py-0.5 text-xs">
                                            </div>
                                            <button type="submit" class="w-full bg-amber-600 hover:bg-amber-700 text-white rounded py-0.5 text-xs">تأجيل</button>
                                        </form>
                                    </details>
                                    @endif
                                </div>
                            @empty
                                <p class="text-gray-300 text-xs text-center py-4">لا حصص</p>
                            @endforelse

                            @if ($can('schedule.create'))
                                <button type="button"
                                        class="add-slot-btn w-full text-emerald-700 border border-dashed border-emerald-300 rounded-lg py-1 text-xs hover:bg-emerald-50"
                                        data-day="{{ $dayNum }}"
                                        data-next-date="{{ $nextDateFor((int) $dayNum) }}"
                                        data-classroom="{{ request('classroom_id') }}"
                                        data-section="{{ request('section_id') }}">
                                    + حصة
                                </button>
                            @endif
                        </td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
    const schedulePrograms = @json($programData);

    function wireProgramPeriodForm(programId, periodId, startsId, endsId) {
        const programSelect = document.getElementById(programId);
        const periodSelect = document.getElementById(periodId);
        const startsInput = document.getElementById(startsId);
        const endsInput = document.getElementById(endsId);

        if (!programSelect || !periodSelect) { return null; }

        function fillPeriods() {
            const periods = (schedulePrograms[programSelect.value] || {}).periods || [];
            periodSelect.innerHTML = '<option value="">— بدون فترة —</option>';
            periods.forEach(function (period) {
                const option = document.createElement('option');
                option.value = period.id;
                option.textContent = period.name + (period.starts_at ? ' (' + period.starts_at + '–' + period.ends_at + ')' : '');
                option.dataset.starts = period.starts_at || '';
                option.dataset.ends = period.ends_at || '';
                periodSelect.appendChild(option);
            });
        }

        function fillTimes() {
            const option = periodSelect.selectedOptions[0];
            if (option && option.dataset.starts) { startsInput.value = option.dataset.starts; }
            if (option && option.dataset.ends) { endsInput.value = option.dataset.ends; }
        }

        programSelect.addEventListener('change', fillPeriods);
        periodSelect.addEventListener('change', fillTimes);
        fillPeriods();

        return periodSelect;
    }

    const singlePeriodSelect = wireProgramPeriodForm('schedule-program', 'schedule-period', 'schedule-starts', 'schedule-ends');

    const oldPeriod = @json(old('program_period_id'));
    if (oldPeriod && singlePeriodSelect) { singlePeriodSelect.value = oldPeriod; }

    // تخصيص البرامج حسب الدوام: خريطة [دوام => برامجه]؛ الدوام غير المذكور
    // تعرض له كل البرامج. تُطبَّق على فلاتر البحث والنموذجين.
    const sessionProgramMap = @json($sessionProgramMap);

    // دوام الصف/الشعبة: يُستخدم لاستنتاج الدوام عند تركه «بدون دوام» حتى
    // تُرشَّح البرامج المتاحة فعلاً (نفس منطق الخادم).
    const classroomSessionMap = @json($classroomSessionMap);
    const sectionSessionMap = @json($sectionSessionMap);

    function allowedProgramIds(sessionId) {
        if (!sessionId) { return null; }

        const list = sessionProgramMap[sessionId];

        return list ? new Set(list) : null;
    }

    function applyProgramFilter(programSelect, sessionId) {
        if (!programSelect) { return; }

        const allowed = allowedProgramIds(sessionId);
        let reset = false;

        Array.from(programSelect.options).forEach(function (option) {
            if (!option.value) { return; }

            const hide = allowed !== null && !allowed.has(option.value);
            option.hidden = hide;
            option.disabled = hide;
            option.style.display = hide ? 'none' : '';

            if (hide && option.selected) { reset = true; }
        });

        if (reset) {
            programSelect.value = '';
            programSelect.dispatchEvent(new Event('change'));
        }
    }

    function wireProgramSessionFilter(sessionSelectId, programSelectId) {
        const sessionSelect = document.getElementById(sessionSelectId);
        const programSelect = document.getElementById(programSelectId);

        if (!sessionSelect || !programSelect) { return; }

        const apply = function () { applyProgramFilter(programSelect, sessionSelect.value); };

        sessionSelect.addEventListener('change', apply);
        apply();
    }

    wireProgramSessionFilter('filter-session', 'filter-program');

    // نموذج الإضافة: الدوام الفعّال = الدوام المختار، وإلا دوام الشعبة، وإلا
    // دوام الصف — ثم تُرشَّح البرامج ويُزامَن اختيار الدوام مع الصف/الشعبة.
    const addForm = document.getElementById('schedule-add-form');
    const addSessionSelect = document.getElementById('schedule-session');
    const addProgramSelect = document.getElementById('schedule-program');
    const addClassroomSelect = addForm ? addForm.querySelector('select[name="classroom_id"]') : null;
    const addSectionSelect = addForm ? addForm.querySelector('select[name="section_id"]') : null;

    function derivedClassroomSession() {
        if (addSectionSelect && addSectionSelect.value && sectionSessionMap[addSectionSelect.value]) {
            return sectionSessionMap[addSectionSelect.value];
        }

        if (addClassroomSelect && addClassroomSelect.value && classroomSessionMap[addClassroomSelect.value]) {
            return classroomSessionMap[addClassroomSelect.value];
        }

        return '';
    }

    function refreshAddFormPrograms() {
        if (!addSessionSelect || !addProgramSelect) { return; }

        const derived = derivedClassroomSession();

        if (derived && addSessionSelect.value !== derived) {
            addSessionSelect.value = derived;
        }

        applyProgramFilter(addProgramSelect, addSessionSelect.value);
    }

    if (addSessionSelect) { addSessionSelect.addEventListener('change', refreshAddFormPrograms); }
    if (addClassroomSelect) { addClassroomSelect.addEventListener('change', refreshAddFormPrograms); }
    if (addSectionSelect) { addSectionSelect.addEventListener('change', refreshAddFormPrograms); }
    refreshAddFormPrograms();

    // «حتى انتهاء الدورة»: عرض تاريخ نهاية البرنامج المختار أو تنبيه بغيابه.
    const courseHint = document.getElementById('schedule-course-hint');
    const durationSelect = document.getElementById('schedule-duration');
    const programSelect = document.getElementById('schedule-program');

    function updateCourseHint() {
        if (!courseHint || !durationSelect || !programSelect) { return; }

        if (durationSelect.value !== 'course') {
            courseHint.classList.add('hidden');
            courseHint.textContent = '';

            return;
        }

        courseHint.classList.remove('hidden');

        const endsOn = programSelect.selectedOptions[0]?.dataset.endsOn || '';

        if (!programSelect.value) {
            courseHint.className = 'text-xs mt-1 text-amber-600';
            courseHint.textContent = 'اختر البرنامج أولاً لتحديد تاريخ نهاية الدورة';
        } else if (!endsOn) {
            courseHint.className = 'text-xs mt-1 text-amber-600';
            courseHint.textContent = '⚠ هذا البرنامج بلا تاريخ نهاية — حدده من صفحة البرنامج';
        } else {
            courseHint.className = 'text-xs mt-1 text-emerald-700';
            courseHint.textContent = 'ستُشال الحصص تلقائياً بعد ' + endsOn;
        }
    }

    if (durationSelect) { durationSelect.addEventListener('change', updateCourseHint); }
    if (programSelect) { programSelect.addEventListener('change', updateCourseHint); }
    updateCourseHint();

    // شبكة الجدول: زر «+ حصة» يفتح النموذج ويعبّئ اليوم/التاريخ/الصف/الشعبة.
    document.querySelectorAll('.add-slot-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            const form = document.getElementById('schedule-add-form');
            if (!form) { return; }

            form.open = true;
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });

            const dayCheckbox = form.querySelector('input[name="days[]"][value="' + button.dataset.day + '"]');
            const startsOnInput = form.querySelector('input[name="starts_on"]');
            const classroomSelect = form.querySelector('select[name="classroom_id"]');
            const sectionSelect = form.querySelector('select[name="section_id"]');

            if (dayCheckbox) { dayCheckbox.checked = true; }
            if (startsOnInput && button.dataset.nextDate) { startsOnInput.value = button.dataset.nextDate; }
            if (classroomSelect && button.dataset.classroom) { classroomSelect.value = button.dataset.classroom; }
            if (sectionSelect && button.dataset.section) { sectionSelect.value = button.dataset.section; }

            refreshAddFormPrograms();
        });
    });
</script>
@endsection
