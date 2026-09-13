@extends('layouts.app')

@section('title', 'الجداول الدراسية')

@php
    $days = [0 => 'الأحد', 1 => 'الاثنين', 2 => 'الثلاثاء', 3 => 'الأربعاء', 4 => 'الخميس', 5 => 'الجمعة', 6 => 'السبت'];
    $bulkDays = array_map('intval', (array) old('days', [0, 1, 2, 3, 4]));
    $programData = $programs->mapWithKeys(fn ($program) => [
        $program->id => $program->periods->map(fn ($period) => [
            'id' => $period->id,
            'name' => $period->name,
            'starts_at' => $period->starts_at ? substr($period->starts_at, 0, 5) : null,
            'ends_at' => $period->ends_at ? substr($period->ends_at, 0, 5) : null,
        ])->values(),
    ]);
@endphp

@section('content')
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
                <option value="">كل الدوامات</option>
                @foreach($studySessions as $session)
                    <option value="{{ $session->id }}" @selected(request('study_session_id') == $session->id)>{{ $session->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg w-full">بحث</button>
        </div>
    </form>

    <details class="mb-2" @if($errors->any() && old('days') === null) open @endif>
        <summary class="cursor-pointer text-emerald-700 font-bold mb-2">إضافة حصة جديدة</summary>
        <form method="POST" action="{{ route('admin.schedules.store') }}" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">البرنامج/التخصص</label>
                <select name="program_id" id="schedule-program" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">— بدون برنامج —</option>
                    @foreach($programs as $program)
                        <option value="{{ $program->id }}" @selected(old('program_id') == $program->id)>{{ $program->name }}</option>
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
                        <option value="{{ $session->id }}" @selected(old('study_session_id', $currentSessionId) == $session->id)>{{ $session->name }}</option>
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
                <label class="block text-sm font-medium text-gray-700 mb-1">اليوم</label>
                <select name="day_of_week" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    @foreach($days as $key => $day)
                        <option value="{{ $key }}" @selected(old('day_of_week') == (string) $key)>{{ $day }}</option>
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
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg w-full">إضافة</button>
            </div>
        </form>
    </details>

    <details class="mb-2" @if($errors->any() && old('days') !== null) open @endif>
        <summary class="cursor-pointer text-emerald-700 font-bold mb-2">توليد جدول أسبوعي لبرنامج/فترة (عدة أيام)</summary>
        <form method="POST" action="{{ route('admin.schedules.generate') }}" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">البرنامج/التخصص</label>
                <select name="program_id" id="bulk-program" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">— بدون برنامج —</option>
                    @foreach($programs as $program)
                        <option value="{{ $program->id }}" @selected(old('program_id') == $program->id)>{{ $program->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الفترة <span class="text-gray-400 text-xs">(اختر الفترة المطلوبة فقط — مثال: الأولى دون الثانية)</span></label>
                <select name="program_period_id" id="bulk-period" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">— بدون فترة —</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الدوام</label>
                <select name="study_session_id" id="bulk-session" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">بدون دوام</option>
                    @foreach($studySessions as $session)
                        <option value="{{ $session->id }}" @selected(old('study_session_id', $currentSessionId) == $session->id)>{{ $session->name }}</option>
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
                <input type="time" name="starts_at" id="bulk-starts" value="{{ old('starts_at') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">وقت النهاية <span class="text-gray-400 text-xs">(تلقائي مع الفترة)</span></label>
                <input type="time" name="ends_at" id="bulk-ends" value="{{ old('ends_at') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>
            <div class="md:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">أيام الأسبوع</label>
                <div class="flex flex-wrap gap-x-6 gap-y-2 border border-gray-200 rounded-lg px-3 py-2">
                    @foreach($days as $key => $day)
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="days[]" value="{{ $key }}" @checked(in_array($key, $bulkDays, true))
                                   class="rounded border-gray-300 text-emerald-700 focus:ring-emerald-500">
                            {{ $day }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div>
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg w-full">توليد الجدول</button>
            </div>
        </form>
    </details>
</div>

<div class="mb-4">
    <button onclick="window.print()" class="bg-gray-700 hover:bg-gray-800 text-white font-bold px-4 py-2 rounded-lg">طباعة</button>
</div>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50 text-gray-600 text-sm">
                    <th class="px-4 py-3 text-right whitespace-nowrap">اليوم</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">الوقت</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">البرنامج</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">الفترة</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">الدوام</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">الصف</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">الشعبة</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">المادة</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">المعلم</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">حذف</th>
                </tr>
            </thead>
            <tbody>
                @forelse($schedules as $schedule)
                    <tr>
                        <td class="px-4 py-3 border-t font-bold whitespace-nowrap">{{ $days[$schedule->day_of_week] }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ substr($schedule->starts_at, 0, 5) }}–{{ substr($schedule->ends_at, 0, 5) }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">
                            @if($schedule->program)
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full" style="background: {{ $schedule->program->color ?: '#475569' }}"></span>
                                    {{ $schedule->program->name }}
                                </span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $schedule->programPeriod?->name ?? '—' }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $schedule->studySession?->name ?? '—' }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $schedule->classroom?->name }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $schedule->section?->name }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $schedule->subject?->name ?? '—' }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $schedule->teacher?->name }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">
                            <form method="POST" action="{{ route('admin.schedules.destroy', $schedule) }}" onsubmit="return confirm('هل أنت متأكد؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline text-sm">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-6 text-center text-gray-500">لا توجد جداول</td>
                    </tr>
                @endforelse
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
            const periods = schedulePrograms[programSelect.value] || [];
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
    const bulkPeriodSelect = wireProgramPeriodForm('bulk-program', 'bulk-period', 'bulk-starts', 'bulk-ends');

    const oldPeriod = @json(old('program_period_id'));
    if (oldPeriod && singlePeriodSelect) { singlePeriodSelect.value = oldPeriod; }
    if (oldPeriod && bulkPeriodSelect) { bulkPeriodSelect.value = oldPeriod; }

    // تخصيص البرامج حسب الدوام: خريطة [دوام => برامجه]؛ الدوام غير المذكور
    // تعرض له كل البرامج. تُطبَّق على فلاتر البحث والنموذجين.
    const sessionProgramMap = @json($sessionProgramMap);

    function allowedProgramIds(sessionId) {
        if (!sessionId) { return null; }

        const list = sessionProgramMap[sessionId];

        return list ? new Set(list) : null;
    }

    function wireProgramSessionFilter(sessionSelectId, programSelectId) {
        const sessionSelect = document.getElementById(sessionSelectId);
        const programSelect = document.getElementById(programSelectId);

        if (!sessionSelect || !programSelect) { return; }

        function apply() {
            const allowed = allowedProgramIds(sessionSelect.value);
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

        sessionSelect.addEventListener('change', apply);
        apply();
    }

    wireProgramSessionFilter('filter-session', 'filter-program');
    wireProgramSessionFilter('schedule-session', 'schedule-program');
    wireProgramSessionFilter('bulk-session', 'bulk-program');
</script>
@endsection
