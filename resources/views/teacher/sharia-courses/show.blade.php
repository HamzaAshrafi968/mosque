@extends('layouts.app')

@section('title', $course->name)

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div>
        <a href="{{ route('teacher.sharia-courses.index') }}" class="text-sm text-emerald-700 hover:text-emerald-800">← الدورات الشرعية</a>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
        <div class="flex items-center gap-3 flex-wrap">
            <h2 class="text-2xl font-extrabold text-gray-800">{{ $course->name }}</h2>
            <span @class([
                'px-2.5 py-1 rounded-full text-xs font-bold',
                'bg-gray-100 text-gray-600' => $course->status->value === 'draft',
                'bg-emerald-100 text-emerald-800' => $course->status->value === 'active',
                'bg-sky-100 text-sky-800' => $course->status->value === 'completed',
                'bg-red-100 text-red-800' => $course->status->value === 'cancelled',
            ])>{{ $course->status->label() }}</span>
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

    @php
        $tabs = [
            'lessons' => 'الدروس والمحاضرات',
            'students' => 'الطلاب وحالة الحفظ',
            'attendance' => 'الحضور والغياب',
            'report' => 'التقرير',
        ];
    @endphp
    <div class="flex flex-wrap gap-2">
        @foreach($tabs as $key => $label)
            <a href="{{ route('teacher.sharia-courses.show', ['course' => $course, 'tab' => $key]) }}"
               @class([
                   'px-4 py-2 rounded-lg text-sm font-bold border',
                   'bg-emerald-700 text-white border-emerald-700' => $tab === $key,
                   'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' => $tab !== $key,
               ])>{{ $label }}</a>
        @endforeach
    </div>

    @if($tab === 'lessons')
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
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">لا توجد دروس أو محاضرات بعد</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($tab === 'students')
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b bg-gray-50 font-bold text-gray-800">
                طلاب الدورة وحالة الحفظ
                @unless($isSupervisor)
                    <span class="text-xs font-normal text-gray-400">— التعديل متاح لمشرفي الدورة</span>
                @endunless
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600">
                            <th class="px-4 py-3 text-right">الاسم</th>
                            <th class="px-4 py-3 text-right">الجوال</th>
                            <th class="px-4 py-3 text-right">الجنس</th>
                            <th class="px-4 py-3 text-right">حالة الحفظ</th>
                            <th class="px-4 py-3 text-right">سجلات الحضور</th>
                            <th class="px-4 py-3 text-right">الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($students as $student)
                        <tr class="border-t">
                            <td class="px-4 py-3 font-bold text-gray-800">{{ $student->name }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $student->phone ?? '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $student->gender === 'male' ? 'ذكر' : ($student->gender === 'female' ? 'أنثى' : '—') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($student->memorization_status)
                                    <span class="px-2 py-0.5 rounded-full text-xs font-bold {{ $student->memorization_status->badgeClass() }}">{{ $student->memorization_status->label() }}</span>
                                @else
                                    <span class="text-xs text-gray-400">غير محدد</span>
                                @endif
                                @if($isSupervisor)
                                    <details class="inline-block text-right align-middle ms-1">
                                        <summary class="cursor-pointer text-xs text-blue-600 font-bold select-none">تعديل</summary>
                                        <form method="POST" action="{{ route('teacher.sharia-courses.students.memorization', $student) }}" class="mt-2 bg-gray-50 border border-gray-200 rounded-xl p-3 space-y-2 min-w-64">
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
                                        </form>
                                    </details>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $student->attendances_count }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'px-2 py-0.5 rounded-full text-xs font-bold',
                                    'bg-emerald-100 text-emerald-800' => $student->status === 'active',
                                    'bg-gray-100 text-gray-600' => $student->status !== 'active',
                                ])>{{ $student->status === 'active' ? 'نشط' : 'مؤرشف' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">لا يوجد طلاب في الدورة بعد</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($tab === 'attendance')
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <h3 class="font-black text-pine-950 mb-3">اختيار الدرس / اليوم</h3>
            <form method="GET" action="{{ route('teacher.sharia-courses.show', $course) }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
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
                :action="route('teacher.sharia-courses.attendance.store', $course)"
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
