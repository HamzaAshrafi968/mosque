@extends('layouts.app')

@section('title', 'الشعبة: '.$section->name)

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.classrooms.show', $section->classroom) }}" class="text-sm text-emerald-700 hover:underline">
        ← {{ $section->classroom?->name }} / {{ $section->name }}
    </a>
</div>

<div class="bg-white rounded-xl shadow overflow-hidden mb-6">
    <div class="px-4 py-4 bg-gradient-to-l from-teal-800 to-emerald-700 text-white flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold">{{ $section->classroom?->name }} / {{ $section->name }}</h1>
            <p class="text-sm text-teal-100 mt-1">{{ $section->description ?: '—' }}</p>
            <span @class([
                'inline-flex mt-1 px-2 py-0.5 rounded-full text-[11px] font-bold',
                'bg-white/20 text-white' => $section->studySession,
                'bg-black/20 text-teal-100' => ! $section->studySession,
            ])>{{ $section->studySession?->name ?: 'غير مرتبط بدوام' }}</span>
        </div>
        <div class="flex items-center gap-3 text-sm">
            <a href="{{ route('admin.attendance.create', ['section_id' => $section->id]) }}" class="bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-lg">تسجيل حضور</a>
            <a href="{{ route('admin.attendance.history', ['section_id' => $section->id]) }}" class="bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-lg">سجل الحضور</a>
            <button type="button" data-toggle-section-edit class="bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-lg">تعديل</button>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.sections.update', $section) }}" id="section-edit-form" class="hidden p-4 border-t space-y-3">
        @csrf
        @method('PATCH')
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <input type="text" name="name" value="{{ $section->name }}" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2">
            @if($section->classroom?->study_session_id)
                <div class="w-full border border-dashed border-teal-300 bg-teal-50 rounded-lg px-3 py-2 text-sm text-teal-800">
                    الدوام: <span class="font-bold">{{ $section->classroom->studySession?->name }}</span>
                    <span class="text-[11px] block text-teal-600">الشعبة تتبع دوام الصف</span>
                </div>
            @else
                <select name="study_session_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">بدون دوام</option>
                    @foreach($sessions as $session)
                        <option value="{{ $session->id }}" @selected((string) $section->study_session_id === (string) $session->id)>{{ $session->display_name }}</option>
                    @endforeach
                </select>
            @endif
            <input type="text" name="description" value="{{ $section->description }}" placeholder="وصف اختياري"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 md:col-span-2">
        </div>
        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg text-sm">حفظ تعديل الشعبة</button>
    </form>

    <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-x-reverse divide-gray-100 text-center">
        <div class="p-4">
            <div class="text-2xl font-bold text-gray-800">{{ $roster->count() }}</div>
            <div class="text-xs text-gray-500">طالب نشط</div>
        </div>
        <div class="p-4">
            <div class="text-2xl font-bold text-gray-800">{{ $section->teacherAssignments->where('status', 'active')->count() }}</div>
            <div class="text-xs text-gray-500">معلم موكل</div>
        </div>
        <div class="p-4">
            <div class="text-2xl font-bold text-amber-600">{{ $section->status === 'active' ? 'نشطة' : 'مؤرشفة' }}</div>
            <div class="text-xs text-gray-500">الحالة</div>
        </div>
        <div class="p-4">
            <a href="{{ route('admin.attendance.history', ['section_id' => $section->id]) }}" class="text-emerald-700 text-sm font-bold hover:underline">عرض التفاصيل ←</a>
        </div>
    </div>
</div>

@if($section->status === 'active')
    <div class="bg-white rounded-xl shadow overflow-hidden mb-6">
        <div class="px-4 py-3 bg-gray-50 border-b font-bold text-gray-800">المعلمون المكلفون بالشعبة</div>
        @if($section->teacherAssignments->where('status', 'active')->isEmpty())
            <div class="px-4 py-4 text-sm text-gray-500">لا يوجد معلمون موكلون — عيّن معلمين ليتمكنوا من إدارة هذه الشعبة</div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600">
                        <th class="px-4 py-2 text-right whitespace-nowrap">المعلم</th>
                        <th class="px-4 py-2 text-right whitespace-nowrap">الدور</th>
                        <th class="px-4 py-2 text-right whitespace-nowrap">منذ</th>
                        <th class="px-4 py-2 text-center whitespace-nowrap">إجراء</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($section->teacherAssignments->where('status', 'active') as $assignment)
                        <tr>
                            <td class="px-4 py-2 border-t font-bold whitespace-nowrap">{{ $assignment->teacher->name }}</td>
                            <td class="px-4 py-2 border-t whitespace-nowrap">{{ $assignment->role->label() }}</td>
                            <td class="px-4 py-2 border-t whitespace-nowrap text-gray-500">{{ $assignment->starts_at?->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-4 py-2 border-t text-center">
                                <form method="POST" action="{{ route('admin.sections.teachers.destroy', [$section, $assignment->teacher]) }}"
                                      onsubmit="return confirm('إنهاء تكليف المعلم؟')" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline text-xs">إنهاء التكليف</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        <div class="p-4 border-t bg-gray-50">
            <form method="POST" action="{{ route('admin.sections.teachers.store', $section) }}" class="flex flex-col sm:flex-row gap-3 sm:items-end">
                @csrf
                <div class="flex-1">
                    <select name="teacher_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        <option value="">اختر معلماً للتكليف...</option>
                        @foreach($availableTeachers as $teacher)
                            <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <select name="role" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        <option value="lead">معلم أساسي</option>
                        <option value="assistant">معلم مساعد</option>
                    </select>
                </div>
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">تكليف المعلم</button>
            </form>
        </div>
    </div>
@endif

<div class="bg-white rounded-xl shadow overflow-hidden mb-6">
    <div class="px-4 py-3 bg-gray-50 border-b flex items-center justify-between">
        <span class="font-bold text-gray-800">طلاب الشعبة ({{ $roster->count() }})</span>
        <a href="{{ route('admin.students.create') }}" class="text-emerald-700 text-sm font-bold hover:underline">+ طالب جديد</a>
    </div>
    @if($roster->isEmpty())
        <div class="px-4 py-6 text-center text-gray-500">لا يوجد طلاب مسجلون في هذه الشعبة</div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600">
                        <th class="px-4 py-3 text-right whitespace-nowrap">الطالب</th>
                        <th class="px-4 py-3 text-center whitespace-nowrap">حاضر</th>
                        <th class="px-4 py-3 text-center whitespace-nowrap">غائب</th>
                        <th class="px-4 py-3 text-center whitespace-nowrap">متأخر</th>
                        <th class="px-4 py-3 text-center whitespace-nowrap">معذور</th>
                        <th class="px-4 py-3 text-center whitespace-nowrap">نسبة الحضور</th>
                        <th class="px-4 py-3 text-center whitespace-nowrap">نقل</th>
                        <th class="px-4 py-3 text-center whitespace-nowrap">إجراء</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($roster as $studentId => $row)
                        <tr>
                            <td class="px-4 py-3 border-t whitespace-nowrap">
                                <a href="{{ route('admin.students.show', $row['student']) }}" class="font-bold text-emerald-800 hover:underline">{{ $row['student']->name }}</a>
                            </td>
                            <td class="px-4 py-3 border-t text-center text-green-700">{{ $row['present'] }}</td>
                            <td class="px-4 py-3 border-t text-center text-red-700">{{ $row['absent'] }}</td>
                            <td class="px-4 py-3 border-t text-center text-yellow-700">{{ $row['late'] }}</td>
                            <td class="px-4 py-3 border-t text-center text-sky-700">{{ $row['excused'] }}</td>
                            <td class="px-4 py-3 border-t text-center font-bold whitespace-nowrap">
                                @if($row['percentage'] !== null)
                                    <span class="text-emerald-700">{{ $row['percentage'] }}%</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 border-t text-center">
                                @if($sections->where('id', '!=', $section->id)->isNotEmpty())
                                    <form method="POST" action="{{ route('admin.students.transfer', $row['student']) }}" class="inline-flex items-center gap-1">
                                        @csrf
                                        <select name="section_id" onchange="this.form.submit()" title="نقل إلى شعبة" class="border border-gray-200 rounded-lg px-1 py-1 text-xs">
                                            <option value="">نقل...</option>
                                            @foreach($sections->where('id', '!=', $section->id) as $target)
                                                <option value="{{ $target->id }}">{{ $target->classroom?->name }} / {{ $target->name }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                @endif
                            </td>
                            <td class="px-4 py-3 border-t text-center whitespace-nowrap">
                                <form method="POST" action="{{ route('admin.sections.students.destroy', [$section, $row['student']]) }}"
                                      onsubmit="return confirm('إخراج الطالب من الشعبة (مع حفظ السجل)؟')" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline text-xs">إخراج</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    @if($section->status === 'active')
        @php
            $selectedIds = old('student_ids', []);
            $unassignedStudents = $availableStudents->filter(fn ($s) => blank($s->section_id))->values();
            $transferStudents = $availableStudents->reject(fn ($s) => blank($s->section_id))->values();
            $pickerGroups = [
                [
                    'key' => 'new',
                    'students' => $unassignedStudents,
                    'title' => 'غير مسجلين في أي شعبة',
                    'hint' => 'يُسجَّلون مباشرة في هذه الشعبة',
                    'dot' => 'bg-emerald-500',
                    'badge' => 'bg-emerald-50 border-emerald-200 text-emerald-700',
                    'label' => 'غير مسجل',
                ],
                [
                    'key' => 'transfer',
                    'students' => $transferStudents,
                    'title' => 'مسجلون في شعب أخرى',
                    'hint' => 'يُنقلون إلى هذه الشعبة مع حفظ سجل العضوية',
                    'dot' => 'bg-amber-500',
                    'badge' => 'bg-amber-50 border-amber-200 text-amber-700',
                    'label' => 'سيُنقل من',
                ],
            ];
            $pickerTotal = $availableStudents->count();
        @endphp

        <div class="border-t bg-gradient-to-b from-gray-50 to-white" data-section-picker>
            <div class="p-4 sm:p-5">
                <div class="flex items-start gap-3 mb-4">
                    <span class="w-11 h-11 shrink-0 rounded-2xl bg-gradient-to-br from-emerald-500 to-pine-800 text-white grid place-items-center shadow-md shadow-emerald-700/20">
                        <x-icon name="user-check" class="w-5 h-5" />
                    </span>
                    <div class="min-w-0">
                        <h3 class="font-black text-gray-800">إضافة طلاب إلى الشعبة</h3>
                        <p class="text-xs text-gray-500 mt-0.5 leading-relaxed">
                            حدّد طالباً أو أكثر ثم اضغط «إضافة المحددين». غير المسجَّلين يُسجَّلون مباشرة، ومن في شعبة أخرى يُنقل تلقائياً مع حفظ سجله السابق.
                        </p>
                    </div>
                </div>

                @if($pickerTotal === 0)
                    <div class="rounded-2xl border border-dashed border-gray-200 bg-white px-4 py-10 text-center">
                        <span class="mx-auto w-12 h-12 grid place-items-center rounded-full bg-emerald-50 text-emerald-600">
                            <x-icon name="check" class="w-6 h-6" />
                        </span>
                        <p class="mt-3 text-sm font-bold text-gray-700">كل الطلاب النشطين في نطاق هذا الدوام مسجَّلون في الشعبة</p>
                        <p class="text-xs text-gray-400 mt-1">أضف طالباً جديداً أو غيّر الدوام النشط ليظهر هنا.</p>
                    </div>
                @else
                    <form method="POST" action="{{ route('admin.sections.students.store', $section) }}"
                          data-picker-form class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-start">
                        @csrf

                        {{-- القائمة --}}
                        <div class="lg:col-span-8 space-y-3">
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 start-3 flex items-center text-gray-400">
                                    <x-icon name="search" class="w-4 h-4" />
                                </span>
                                <input type="text" data-picker-search autocomplete="off"
                                       placeholder="ابحث بالاسم أو اسم ولي الأمر أو رقم الجوال..."
                                       class="w-full rounded-xl border border-gray-200 bg-white ps-9 pe-10 py-2.5 text-sm shadow-sm transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/25 focus:outline-none">
                                <button type="button" data-picker-search-clear
                                        class="absolute inset-y-0 end-2 my-auto hidden h-7 w-7 place-items-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 grid"
                                        aria-label="مسح البحث">
                                    <x-icon name="x" class="w-4 h-4" />
                                </button>
                            </div>

                            <div class="flex flex-wrap items-center gap-1.5">
                                @foreach([
                                    'all' => 'الكل',
                                    'new' => 'غير مسجلين',
                                    'transfer' => 'سيُنقلون',
                                ] as $tabKey => $tabLabel)
                                    <button type="button" data-picker-tab="{{ $tabKey }}" data-active="{{ $tabKey === 'all' ? 'true' : 'false' }}"
                                            aria-pressed="{{ $tabKey === 'all' ? 'true' : 'false' }}"
                                            class="group inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-bold text-gray-500 transition hover:border-emerald-300 hover:text-emerald-700 data-[active=true]:border-emerald-700 data-[active=true]:bg-emerald-700 data-[active=true]:text-white">
                                        {{ $tabLabel }}
                                        <span class="rounded-full bg-gray-100 px-1.5 text-[10px] font-black text-gray-500 transition group-data-[active=true]:bg-white/20 group-data-[active=true]:text-white"
                                              data-picker-tab-count="{{ $tabKey }}">{{ $tabKey === 'all' ? $pickerTotal : ($tabKey === 'new' ? $unassignedStudents->count() : $transferStudents->count()) }}</span>
                                    </button>
                                @endforeach

                                <div class="ms-auto flex items-center gap-1">
                                    <button type="button" data-picker-select-visible
                                            class="rounded-lg px-2.5 py-1.5 text-[11px] font-bold text-emerald-700 transition hover:bg-emerald-50">
                                        تحديد الظاهر
                                    </button>
                                    <button type="button" data-picker-clear
                                            class="rounded-lg px-2.5 py-1.5 text-[11px] font-bold text-gray-400 transition hover:bg-gray-100 hover:text-gray-600">
                                        إلغاء التحديد
                                    </button>
                                </div>
                            </div>

                            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                                <div class="max-h-[24rem] divide-y divide-gray-100 overflow-y-auto" data-picker-list>
                                    @foreach($pickerGroups as $group)
                                        @if($group['students']->isNotEmpty())
                                            <section data-picker-group="{{ $group['key'] }}">
                                                <header class="sticky top-0 z-10 flex items-center gap-2 border-b border-gray-100 bg-gray-50/95 px-3.5 py-2 backdrop-blur">
                                                    <span class="w-2 h-2 rounded-full {{ $group['dot'] }}"></span>
                                                    <span class="text-[11px] font-black text-gray-700">{{ $group['title'] }}</span>
                                                    <span class="rounded-full bg-gray-200/70 px-1.5 text-[10px] font-black text-gray-500">{{ $group['students']->count() }}</span>
                                                    <span class="ms-auto hidden text-[10px] text-gray-400 sm:block">{{ $group['hint'] }}</span>
                                                </header>

                                                <div class="space-y-1 p-2">
                                                    @foreach($group['students'] as $student)
                                                        @php
                                                            $sourceLabel = trim(($student->classroom?->name ?? '').' / '.($student->section?->name ?? ''), ' /');
                                                            $metaLabel = $student->guardian_name
                                                                ? 'ولي الأمر: '.$student->guardian_name
                                                                : ($student->guardian_phone ?: ($student->gender === 'female' ? 'طالبة' : 'طالب'));
                                                        @endphp
                                                        <label data-picker-item
                                                               data-group="{{ $group['key'] }}"
                                                               data-name="{{ $student->name }}"
                                                               data-source="{{ $sourceLabel }}"
                                                               data-search="{{ trim($student->name.' '.$student->guardian_name.' '.$student->guardian_phone.' '.$sourceLabel) }}"
                                                               class="peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-500/60 grid cursor-pointer grid-cols-[auto_1fr_auto] items-center gap-x-3 gap-y-1 rounded-xl border border-transparent px-2.5 py-2.5 transition hover:border-emerald-100 hover:bg-emerald-50/70 has-[:checked]:border-emerald-300 has-[:checked]:bg-emerald-50 has-[:checked]:shadow-sm">
                                                            <input type="checkbox" name="student_ids[]" value="{{ $student->id }}"
                                                                   data-picker-checkbox
                                                                   @checked(in_array($student->id, $selectedIds, true))
                                                                   class="peer sr-only">

                                                            <x-avatar :src="$student->avatarUrl()" :name="$student->name" size="sm"
                                                                      class="row-span-2"
                                                                      fallback-class="bg-gradient-to-br from-gold-400 to-gold-700" />

                                                            <span class="min-w-0">
                                                                <span class="block truncate text-sm font-bold text-gray-800">{{ $student->name }}</span>
                                                                <span class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1">
                                                                    <span class="max-w-full truncate text-[11px] text-gray-400">{{ $metaLabel }}</span>
                                                                    <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-bold whitespace-nowrap {{ $group['badge'] }}">
                                                                        {{ $group['label'] }}@if($group['key'] === 'transfer' && $sourceLabel !== ''): {{ $sourceLabel }}@endif
                                                                    </span>
                                                                </span>
                                                            </span>

                                                            <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full border-2 border-gray-200 bg-white text-transparent transition-colors peer-checked:border-emerald-600 peer-checked:bg-emerald-600 peer-checked:text-white">
                                                                <x-icon name="check" class="w-3.5 h-3.5" />
                                                            </span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </section>
                                        @endif
                                    @endforeach

                                    <div data-picker-no-results class="hidden px-4 py-12 text-center">
                                        <span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-gray-100 text-gray-400">
                                            <x-icon name="search" class="w-5 h-5" />
                                        </span>
                                        <p class="mt-3 text-sm font-bold text-gray-600">لا يوجد طالب مطابق</p>
                                        <button type="button" data-picker-reset class="mt-1.5 text-xs font-bold text-emerald-700 hover:underline">
                                            مسح البحث والتصنيف
                                        </button>
                                    </div>
                                </div>
                            </div>

                            @if($roster->isNotEmpty())
                                <p class="text-[11px] leading-relaxed text-gray-400">
                                    <x-icon name="info" class="inline w-3.5 h-3.5 -mt-0.5" />
                                    طلاب هذه الشعبة الحاليون ({{ $roster->count() }}) غير معروضين في القائمة — راجع جدول الطلاب أعلاه.
                                </p>
                            @endif
                        </div>

                        {{-- ملخّص الاختيار --}}
                        <aside class="lg:col-span-4 w-full lg:sticky lg:top-24">
                            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg shadow-gray-900/5">
                                <div class="hidden items-center gap-2 bg-gradient-to-l from-pine-800 to-emerald-700 px-4 py-3 text-white lg:flex">
                                    <x-icon name="check" class="w-4 h-4" />
                                    <span class="text-sm font-black">ملخّص الإضافة</span>
                                    <span class="ms-auto inline-flex items-center gap-1 rounded-full bg-white/20 px-2.5 py-0.5 text-xs font-black">
                                        <span data-picker-count aria-live="polite">0</span> طالب
                                    </span>
                                </div>

                                <div class="hidden p-3 lg:block">
                                    <div data-picker-empty class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-6 text-center">
                                        <p class="text-xs font-bold text-gray-500">لم تحدد أي طالب بعد</p>
                                        <p class="mt-1 text-[11px] leading-relaxed text-gray-400">حدّد الطلاب من القائمة لتظهر أسماؤهم هنا قبل الإضافة.</p>
                                    </div>
                                    <ul data-picker-selected class="hidden max-h-56 space-y-1.5 overflow-y-auto"></ul>
                                </div>

                                <div class="hidden border-t border-gray-100 px-4 py-3 lg:block">
                                    <div class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] leading-relaxed text-amber-800">
                                        <x-icon name="info" class="mt-0.5 w-3.5 h-3.5 shrink-0" />
                                        <span>الطلاب المنتقلون يُسحبون من شعبهم الحالية مع حفظ سجل العضوية السابق.</span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 border-t border-gray-100 bg-gray-50 p-3">
                                    <span class="text-xs font-bold text-gray-600 lg:hidden">
                                        المحددون: <span data-picker-count>0</span>
                                    </span>
                                    <button type="submit" data-picker-submit
                                            class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-black text-white shadow-md shadow-emerald-700/20 transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-500 disabled:shadow-none">
                                        <x-icon name="plus" class="w-4 h-4" />
                                        إضافة المحددين للشعبة
                                    </button>
                                </div>
                            </div>
                        </aside>
                    </form>
                @endif
            </div>
        </div>
    @endif
</div>

@if($enrollments->isNotEmpty())
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="px-4 py-3 bg-gray-50 border-b font-bold text-gray-800">سجل العضوية بالشعبة</div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-xs">
                        <th class="px-4 py-2 text-right">الطالب</th>
                        <th class="px-4 py-2 text-right">الحالة</th>
                        <th class="px-4 py-2 text-right">تسجيل</th>
                        <th class="px-4 py-2 text-right">انتهاء</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($enrollments as $enrollment)
                        <tr>
                            <td class="px-4 py-2 border-t whitespace-nowrap">{{ $enrollment->student?->name ?? '—' }}</td>
                            <td class="px-4 py-2 border-t whitespace-nowrap">
                                <span @class([
                                    'px-2 py-0.5 rounded-full text-xs font-bold',
                                    'bg-green-100 text-green-800' => $enrollment->status->value === 'active',
                                    'bg-yellow-100 text-yellow-800' => $enrollment->status->value === 'transferred',
                                    'bg-gray-100 text-gray-600' => $enrollment->status->value === 'inactive',
                                    'bg-sky-100 text-sky-800' => $enrollment->status->value === 'completed',
                                ])>{{ $enrollment->status->label() }}</span>
                            </td>
                            <td class="px-4 py-2 border-t whitespace-nowrap">{{ $enrollment->enrolled_at?->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-4 py-2 border-t whitespace-nowrap">{{ $enrollment->left_at?->format('Y-m-d') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
    (() => {
        const toggleBtn = document.querySelector('[data-toggle-section-edit]');
        const editForm = document.getElementById('section-edit-form');
        if (toggleBtn && editForm) {
            toggleBtn.addEventListener('click', () => editForm.classList.toggle('hidden'));
        }

        // تطبيع النص العربي: تجاهل التشكيل والتطويل وتوحيد الألف والهمزات
        // والتاء المربوطة حتى يجد البحث «أحمد» عند كتابة «احمد».
        const normalizeArabic = (value) => String(value ?? '')
            .toLowerCase()
            .replace(/[\u064B-\u0652\u0640]/g, '')
            .replace(/[أإآٱ]/g, 'ا')
            .replace(/ى/g, 'ي')
            .replace(/ة/g, 'ه')
            .replace(/ؤ/g, 'و')
            .replace(/ئ/g, 'ي')
            .replace(/[^\p{L}\p{N}]+/gu, ' ')
            .trim();

        document.querySelectorAll('[data-section-picker]').forEach((root) => {
            const form = root.querySelector('[data-picker-form]');
            if (!form) return;

            const searchInput = form.querySelector('[data-picker-search]');
            const searchClear = form.querySelector('[data-picker-search-clear]');
            const items = Array.from(form.querySelectorAll('[data-picker-item]'));
            const groups = Array.from(form.querySelectorAll('[data-picker-group]'));
            const tabs = Array.from(form.querySelectorAll('[data-picker-tab]'));
            const countEls = Array.from(form.querySelectorAll('[data-picker-count]'));
            const submitBtn = form.querySelector('[data-picker-submit]');
            const selectedList = form.querySelector('[data-picker-selected]');
            const emptyState = form.querySelector('[data-picker-empty]');
            const noResults = form.querySelector('[data-picker-no-results]');

            items.forEach((item) => {
                item.dataset.search = normalizeArabic(item.dataset.search);
            });

            let activeTab = 'all';

            const checkboxOf = (item) => item.querySelector('[data-picker-checkbox]');

            const refresh = () => {
                const term = normalizeArabic(searchInput?.value ?? '');
                const visible = { all: 0, new: 0, transfer: 0 };

                items.forEach((item) => {
                    const show = (activeTab === 'all' || item.dataset.group === activeTab)
                        && (term === '' || item.dataset.search.includes(term));

                    item.classList.toggle('hidden', !show);

                    if (show) {
                        visible.all += 1;
                        visible[item.dataset.group] = (visible[item.dataset.group] ?? 0) + 1;
                    }
                });

                groups.forEach((group) => {
                    group.classList.toggle('hidden', (visible[group.dataset.pickerGroup] ?? 0) === 0);
                });

                form.querySelectorAll('[data-picker-tab-count]').forEach((el) => {
                    el.textContent = visible[el.dataset.pickerTabCount] ?? 0;
                });

                tabs.forEach((tab) => {
                    const isActive = tab.dataset.pickerTab === activeTab;
                    tab.dataset.active = isActive ? 'true' : 'false';
                    tab.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });

                searchClear?.classList.toggle('hidden', (searchInput?.value ?? '').trim() === '');
                noResults?.classList.toggle('hidden', visible.all > 0);
            };

            const renderSelected = (checked) => {
                if (!selectedList) return;

                selectedList.innerHTML = '';

                checked.forEach((item) => {
                    const isTransfer = item.dataset.group === 'transfer';

                    const row = document.createElement('li');
                    row.className = 'flex items-center gap-2 rounded-xl border border-emerald-100 bg-emerald-50/70 px-2.5 py-2';

                    const info = document.createElement('span');
                    info.className = 'min-w-0 flex-1';

                    const name = document.createElement('span');
                    name.className = 'block truncate text-xs font-bold text-gray-800';
                    name.textContent = item.dataset.name ?? '';
                    info.appendChild(name);

                    const badge = document.createElement('span');
                    badge.className = 'mt-0.5 block truncate text-[10px] font-bold ' + (isTransfer ? 'text-amber-700' : 'text-emerald-700');
                    badge.textContent = isTransfer ? `نقل من: ${item.dataset.source || 'شعبة أخرى'}` : 'تسجيل جديد';
                    info.appendChild(badge);
                    row.appendChild(info);

                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'grid h-6 w-6 shrink-0 place-items-center rounded-full text-gray-400 transition hover:bg-white hover:text-red-600';
                    remove.setAttribute('aria-label', `إزالة ${item.dataset.name ?? ''}`);
                    remove.textContent = '×';
                    remove.addEventListener('click', () => {
                        const box = checkboxOf(item);
                        if (box) box.checked = false;
                        syncSelection();
                    });
                    row.appendChild(remove);

                    selectedList.appendChild(row);
                });
            };

            const syncSelection = () => {
                const checked = items.filter((item) => checkboxOf(item)?.checked);

                countEls.forEach((el) => { el.textContent = checked.length; });
                if (submitBtn) submitBtn.disabled = checked.length === 0;

                emptyState?.classList.toggle('hidden', checked.length > 0);
                selectedList?.classList.toggle('hidden', checked.length === 0);

                renderSelected(checked);
            };

            tabs.forEach((tab) => {
                tab.addEventListener('click', () => {
                    activeTab = tab.dataset.pickerTab ?? 'all';
                    refresh();
                });
            });

            searchInput?.addEventListener('input', refresh);

            searchClear?.addEventListener('click', () => {
                searchInput.value = '';
                refresh();
                searchInput.focus();
            });

            form.querySelector('[data-picker-select-visible]')?.addEventListener('click', () => {
                items.forEach((item) => {
                    if (!item.classList.contains('hidden')) {
                        const box = checkboxOf(item);
                        if (box) box.checked = true;
                    }
                });
                syncSelection();
            });

            form.querySelector('[data-picker-clear]')?.addEventListener('click', () => {
                items.forEach((item) => {
                    const box = checkboxOf(item);
                    if (box) box.checked = false;
                });
                syncSelection();
            });

            form.querySelector('[data-picker-reset]')?.addEventListener('click', () => {
                if (searchInput) searchInput.value = '';
                activeTab = 'all';
                refresh();
            });

            form.addEventListener('change', (event) => {
                if (event.target.matches('[data-picker-checkbox]')) syncSelection();
            });

            refresh();
            syncSelection();
        });
    })();
</script>
@endpush
