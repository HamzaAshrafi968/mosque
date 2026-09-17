@extends('layouts.app')

@section('title', $course->name)

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div>
        <a href="{{ route('admin.sharia-courses.index') }}" class="text-sm text-emerald-700 hover:text-emerald-800">← الدورات الشرعية</a>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <h2 class="text-2xl font-extrabold text-gray-800">{{ $course->name }}</h2>
                    <span @class([
                        'px-2.5 py-1 rounded-full text-xs font-bold',
                        'bg-gray-100 text-gray-600' => $course->status->value === 'draft',
                        'bg-emerald-100 text-emerald-800' => $course->status->value === 'active',
                        'bg-sky-100 text-sky-800' => $course->status->value === 'completed',
                        'bg-red-100 text-red-800' => $course->status->value === 'cancelled',
                    ])>{{ $course->status->label() }}</span>
                    @if($course->isFromSuperAdmin())
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-violet-100 text-violet-800">أضيفت من مدير الجوامع</span>
                    @endif
                </div>
                <div class="text-sm text-gray-500 mt-2 space-y-1">
                    <div>
                        المشرفون:
                        @forelse($course->supervisors as $supervisor)
                            <span class="font-bold text-gray-700">{{ $supervisor->name }}</span>@if(!$loop->last)، @endif
                        @empty
                            <span class="font-bold text-gray-700">—</span>
                        @endforelse
                    </div>
                    <div>المكان: {{ $course->location ?? '—' }}</div>
                    <div>الفترة: {{ $course->start_date?->format('Y-m-d') ?? '—' }} ← {{ $course->end_date?->format('Y-m-d') ?? '—' }}</div>
                    @if($course->description)<div class="text-gray-600">{{ $course->description }}</div>@endif
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.sharia-courses.edit', $course) }}" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-bold px-4 py-2 rounded-lg">تعديل الدورة</a>
                <form method="POST" action="{{ route('admin.sharia-courses.destroy', $course) }}" onsubmit="return confirm('حذف الدورة سيحذف دروسها وطلابها وسجلات حضورهم. متأكد؟')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="bg-red-50 border border-red-200 hover:bg-red-100 text-red-700 text-sm font-bold px-4 py-2 rounded-lg">حذف</button>
                </form>
            </div>
        </div>
    </div>

    @php
        $tabs = [
            'lessons' => 'الدروس والمحاضرات',
            'students' => 'الطلاب',
            'attendance' => 'الحضور والغياب',
            'report' => 'التقرير',
        ];
    @endphp
    <div class="flex flex-wrap gap-2">
        @foreach($tabs as $key => $label)
            <a href="{{ route('admin.sharia-courses.show', ['course' => $course, 'tab' => $key]) }}"
               @class([
                   'px-4 py-2 rounded-lg text-sm font-bold border',
                   'bg-emerald-700 text-white border-emerald-700' => $tab === $key,
                   'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' => $tab !== $key,
               ])>{{ $label }}</a>
        @endforeach
    </div>

    @if($tab === 'lessons')
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <h3 class="font-black text-pine-950 mb-3">إضافة درس / محاضرة</h3>
            <form method="POST" action="{{ route('admin.sharia-courses.lessons.store', $course) }}" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                @csrf
                <div class="md:col-span-2">
                    <input type="text" name="title" required maxlength="255" value="{{ old('title') }}" placeholder="العنوان"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <select name="type" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @foreach($lessonTypes as $type)
                        <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
                <input type="date" name="date" required value="{{ old('date', now()->toDateString()) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <input type="time" name="start_time" value="{{ old('start_time') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" aria-label="من">
                <input type="time" name="end_time" value="{{ old('end_time') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" aria-label="إلى">
                <select name="teacher_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">— المعلم —</option>
                    @foreach($teachers as $teacher)
                        <option value="{{ $teacher->id }}" @selected(old('teacher_id') === $teacher->id)>{{ $teacher->name }}</option>
                    @endforeach
                </select>
                <input type="file" name="attachment" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png" class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm md:col-span-2">
                <div class="md:col-span-3">
                    <textarea name="description" rows="2" maxlength="5000" placeholder="وصف مختصر" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('description') }}</textarea>
                </div>
                <div class="md:col-span-3">
                    <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-5 py-2 rounded-lg">إضافة</button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600">
                            <th class="px-4 py-3 text-right">العنوان</th>
                            <th class="px-4 py-3 text-right">النوع</th>
                            <th class="px-4 py-3 text-right">التاريخ</th>
                            <th class="px-4 py-3 text-right">الوقت</th>
                            <th class="px-4 py-3 text-right">المعلم</th>
                            <th class="px-4 py-3 text-right">مرفق</th>
                            <th class="px-4 py-3 text-center">إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($lessons as $lesson)
                        <tr class="border-t">
                            <td class="px-4 py-3 font-bold text-gray-800">{{ $lesson->title }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'px-2 py-0.5 rounded-full text-xs font-bold',
                                    'bg-emerald-50 text-emerald-700' => $lesson->type->value === 'lesson',
                                    'bg-sky-50 text-sky-700' => $lesson->type->value === 'lecture',
                                ])>{{ $lesson->type->label() }}</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $lesson->date->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ $lesson->start_time ? substr($lesson->start_time, 0, 5) : '—' }}
                                {{ $lesson->end_time ? '— '.substr($lesson->end_time, 0, 5) : '' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $lesson->teacher?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if($lesson->attachment_path)
                                    <a href="{{ asset('storage/'.$lesson->attachment_path) }}" target="_blank" class="text-emerald-700 hover:underline text-xs">تحميل</a>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <details class="inline-block text-right">
                                    <summary class="cursor-pointer text-xs text-blue-600 font-bold select-none">تعديل</summary>
                                    <form method="POST" action="{{ route('admin.sharia-courses.lessons.update', $lesson) }}" enctype="multipart/form-data" class="mt-2 bg-gray-50 border border-gray-200 rounded-xl p-3 space-y-2 min-w-64">
                                        @csrf
                                        @method('PATCH')
                                        <input type="text" name="title" required value="{{ $lesson->title }}" class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm">
                                        <div class="grid grid-cols-2 gap-2">
                                            <select name="type" class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm">
                                                @foreach($lessonTypes as $type)
                                                    <option value="{{ $type->value }}" @selected($lesson->type->value === $type->value)>{{ $type->label() }}</option>
                                                @endforeach
                                            </select>
                                            <input type="date" name="date" required value="{{ $lesson->date->format('Y-m-d') }}" class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm">
                                            <input type="time" name="start_time" value="{{ $lesson->start_time ? substr($lesson->start_time, 0, 5) : '' }}" class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm">
                                            <input type="time" name="end_time" value="{{ $lesson->end_time ? substr($lesson->end_time, 0, 5) : '' }}" class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm">
                                        </div>
                                        <select name="teacher_id" class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm">
                                            <option value="">— المعلم —</option>
                                            @foreach($teachers as $teacher)
                                                <option value="{{ $teacher->id }}" @selected($lesson->teacher_id === $teacher->id)>{{ $teacher->name }}</option>
                                            @endforeach
                                        </select>
                                        <textarea name="description" rows="2" class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm">{{ $lesson->description }}</textarea>
                                        <input type="file" name="attachment" class="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs">
                                        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-4 py-2 rounded-lg">حفظ</button>
                                    </form>
                                </details>
                                <form method="POST" action="{{ route('admin.sharia-courses.lessons.destroy', $lesson) }}" class="inline ms-2" onsubmit="return confirm('حذف الدرس وحضوره؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-600 hover:underline">حذف</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">لا توجد دروس أو محاضرات بعد</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($tab === 'students')
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between flex-wrap gap-2 mb-3">
                <h3 class="font-black text-pine-950">تسجيل طلاب موجودين في الجامع</h3>
                <span class="text-xs text-gray-400">المحددون: <span id="available-student-count">0</span></span>
            </div>
            @if($availableStudents->isEmpty())
                <p class="text-sm text-gray-400">كل طلاب الجامع النشطين مسجَّلون في الدورة، أو لا يوجد طلاب بعد.</p>
            @else
                <form method="POST" action="{{ route('admin.sharia-courses.students.existing', $course) }}" class="space-y-3">
                    @csrf
                    <input type="text" id="available-student-filter" placeholder="بحث سريع بالاسم..." class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2 max-h-72 overflow-y-auto border border-gray-100 rounded-xl p-3">
                        @foreach($availableStudents as $availableStudent)
                            <label class="flex items-center gap-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg px-2 py-1.5 available-student-option">
                                <input type="checkbox" name="student_ids[]" value="{{ $availableStudent->id }}" class="accent-emerald-600 available-student-check">
                                <span class="font-bold">{{ $availableStudent->name }}</span>
                                @if($availableStudent->classroom)<span class="text-xs text-gray-400">{{ $availableStudent->classroom->name }}</span>@endif
                            </label>
                        @endforeach
                    </div>
                    <button type="submit" class="bg-pine-800 hover:bg-pine-900 text-white text-sm font-bold px-5 py-2 rounded-lg">تسجيل المحددين في الدورة</button>
                </form>
            @endif
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <h3 class="font-black text-pine-950 mb-3">إضافة طالب للدورة (سجل مستقل)</h3>
            <form method="POST" action="{{ route('admin.sharia-courses.students.store', $course) }}" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                @csrf
                <input type="text" name="name" required maxlength="255" value="{{ old('name') }}" placeholder="الاسم" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <input type="text" name="phone" maxlength="30" value="{{ old('phone') }}" placeholder="الجوال" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <select name="gender" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">— الجنس —</option>
                    <option value="male" @selected(old('gender') === 'male')>ذكر</option>
                    <option value="female" @selected(old('gender') === 'female')>أنثى</option>
                </select>
                <input type="date" name="birth_date" value="{{ old('birth_date') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" aria-label="تاريخ الميلاد">
                <input type="text" name="guardian_phone" maxlength="30" value="{{ old('guardian_phone') }}" placeholder="جوال ولي الأمر" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <input type="text" name="notes" maxlength="2000" value="{{ old('notes') }}" placeholder="ملاحظات" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <div class="md:col-span-3">
                    <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-5 py-2 rounded-lg">إضافة الطالب</button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b bg-gray-50 font-bold text-gray-800">طلاب الدورة وحالة الحفظ</div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600">
                            <th class="px-4 py-3 text-right">الاسم</th>
                            <th class="px-4 py-3 text-right">الجوال</th>
                            <th class="px-4 py-3 text-right">الجنس</th>
                            <th class="px-4 py-3 text-right">ولي الأمر</th>
                            <th class="px-4 py-3 text-right">حالة الحفظ</th>
                            <th class="px-4 py-3 text-right">سجلات الحضور</th>
                            <th class="px-4 py-3 text-right">الحالة</th>
                            <th class="px-4 py-3 text-center">إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($students as $student)
                        <tr class="border-t">
                            <td class="px-4 py-3 font-bold text-gray-800">
                                {{ $student->name }}
                                @if($student->student_id)
                                    <span class="block text-[10px] text-gray-400">مسجَّل من طلاب الجامع</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $student->phone ?? '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $student->gender === 'male' ? 'ذكر' : ($student->gender === 'female' ? 'أنثى' : '—') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $student->guardian_phone ?? '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($student->memorization_status)
                                    <span class="px-2 py-0.5 rounded-full text-xs font-bold {{ $student->memorization_status->badgeClass() }}">{{ $student->memorization_status->label() }}</span>
                                @else
                                    <span class="text-xs text-gray-400">غير محدد</span>
                                @endif
                                <details class="inline-block text-right align-middle ms-1">
                                    <summary class="cursor-pointer text-xs text-blue-600 font-bold select-none">تعديل</summary>
                                    <form method="POST" action="{{ route('admin.sharia-courses.students.memorization', $student) }}" class="mt-2 bg-gray-50 border border-gray-200 rounded-xl p-3 space-y-2 min-w-64">
                                        @csrf
                                        @method('PATCH')
                                        <select name="memorization_status" class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm">
                                            <option value="">— غير محدد —</option>
                                            @foreach($memorizationStatuses as $memorizationStatus)
                                                <option value="{{ $memorizationStatus->value }}" @selected($student->memorization_status === $memorizationStatus)>{{ $memorizationStatus->label() }}</option>
                                            @endforeach
                                        </select>
                                        <textarea name="memorization_notes" rows="2" maxlength="2000" placeholder="ملاحظات الحفظ" class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm">{{ $student->memorization_notes }}</textarea>
                                        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-4 py-2 rounded-lg">حفظ حالة الحفظ</button>
                                        @if($student->memorization_updated_at)
                                            <div class="text-[10px] text-gray-400">آخر تحديث: {{ $student->memorization_updated_at->format('Y-m-d H:i') }}</div>
                                        @endif
                                    </form>
                                </details>
                            </td>
                            <td class="px-4 py-3">{{ $student->attendances_count }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'px-2 py-0.5 rounded-full text-xs font-bold',
                                    'bg-emerald-100 text-emerald-800' => $student->status === 'active',
                                    'bg-gray-100 text-gray-600' => $student->status !== 'active',
                                ])>{{ $student->status === 'active' ? 'نشط' : 'مؤرشف' }}</span>
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <details class="inline-block text-right">
                                    <summary class="cursor-pointer text-xs text-blue-600 font-bold select-none">تعديل</summary>
                                    <form method="POST" action="{{ route('admin.sharia-courses.students.update', $student) }}" class="mt-2 bg-gray-50 border border-gray-200 rounded-xl p-3 space-y-2 min-w-64">
                                        @csrf
                                        @method('PATCH')
                                        <input type="text" name="name" required value="{{ $student->name }}" class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm">
                                        <div class="grid grid-cols-2 gap-2">
                                            <input type="text" name="phone" value="{{ $student->phone }}" placeholder="الجوال" class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm">
                                            <select name="gender" class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm">
                                                <option value="">— الجنس —</option>
                                                <option value="male" @selected($student->gender === 'male')>ذكر</option>
                                                <option value="female" @selected($student->gender === 'female')>أنثى</option>
                                            </select>
                                            <input type="date" name="birth_date" value="{{ $student->birth_date?->format('Y-m-d') }}" class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm">
                                            <input type="text" name="guardian_phone" value="{{ $student->guardian_phone }}" placeholder="ولي الأمر" class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm">
                                        </div>
                                        <select name="status" class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm">
                                            <option value="active" @selected($student->status === 'active')>نشط</option>
                                            <option value="inactive" @selected($student->status === 'inactive')>مؤرشف</option>
                                        </select>
                                        <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm">{{ $student->notes }}</textarea>
                                        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-4 py-2 rounded-lg">حفظ</button>
                                    </form>
                                </details>
                                <form method="POST" action="{{ route('admin.sharia-courses.students.destroy', $student) }}" class="inline ms-2" onsubmit="return confirm('حذف الطالب سيحذف سجلات حضوره. متأكد؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-600 hover:underline">حذف</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">لا يوجد طلاب في الدورة بعد</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($tab === 'attendance')
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <h3 class="font-black text-pine-950 mb-3">اختيار الدرس / اليوم</h3>
            <form method="GET" action="{{ route('admin.sharia-courses.show', $course) }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                <input type="hidden" name="tab" value="attendance">
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-gray-600 mb-1">الدرس / المحاضرة</label>
                    <select name="lesson_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">— تحضير يومي عام —</option>
                        @foreach($lessons as $lesson)
                            <option value="{{ $lesson->id }}" @selected($selectedLessonId === $lesson->id)>
                                {{ $lesson->title }} — {{ $lesson->date->format('Y-m-d') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">التاريخ</label>
                    <input type="date" name="date" value="{{ $attendanceDate }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <button class="bg-pine-800 hover:bg-pine-900 text-white text-sm font-bold px-4 py-2 rounded-lg">عرض الطلاب</button>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b bg-gray-50 font-bold text-gray-800">تحضير {{ $attendanceDate }}</div>
            <x-sharia-attendance-marks
                :students="$students"
                :existing="$existing"
                :notes="$existingNotes"
                :action="route('admin.sharia-courses.attendance.store', $course)"
                :lesson-id="$selectedLessonId"
                :date="$attendanceDate" />
        </div>
    @endif

    @if($tab === 'report')
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b bg-gray-50 font-bold text-gray-800">تقرير الحضور لكل طالب</div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600">
                            <th class="px-4 py-3 text-right">الطالب</th>
                            <th class="px-4 py-3 text-right">حاضر</th>
                            <th class="px-4 py-3 text-right">غائب</th>
                            <th class="px-4 py-3 text-right">متأخر</th>
                            <th class="px-4 py-3 text-right">معذور</th>
                            <th class="px-4 py-3 text-right">النسبة</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($report as $row)
                        <tr class="border-t">
                            <td class="px-4 py-3 font-bold text-gray-800">{{ $row['student']->name }}</td>
                            <td class="px-4 py-3 text-green-700 font-bold">{{ $row['summary']['present'] }}</td>
                            <td class="px-4 py-3 text-red-700 font-bold">{{ $row['summary']['absent'] }}</td>
                            <td class="px-4 py-3 text-yellow-700 font-bold">{{ $row['summary']['late'] }}</td>
                            <td class="px-4 py-3 text-sky-700 font-bold">{{ $row['summary']['excused'] }}</td>
                            <td class="px-4 py-3 font-bold">{{ $row['summary']['percentage'] !== null ? $row['summary']['percentage'].'٪' : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">لا يوجد طلاب في الدورة بعد</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
    const availableFilter = document.getElementById('available-student-filter');
    const availableCount = document.getElementById('available-student-count');

    if (availableFilter) {
        availableFilter.addEventListener('input', () => {
            const query = availableFilter.value.trim();
            document.querySelectorAll('.available-student-option').forEach((option) => {
                option.classList.toggle('hidden', query !== '' && !option.textContent.includes(query));
            });
        });
    }

    const updateAvailableCount = () => {
        if (availableCount) {
            availableCount.textContent = document.querySelectorAll('.available-student-check:checked').length;
        }
    };

    document.querySelectorAll('.available-student-check').forEach((checkbox) => checkbox.addEventListener('change', updateAvailableCount));
    updateAvailableCount();
</script>
@endsection
