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
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <input type="text" name="name" value="{{ $section->name }}" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2">
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
    @if($section->status === 'active' && $availableStudents->isNotEmpty())
        <div class="p-4 border-t bg-gray-50">
            <form method="POST" action="{{ route('admin.sections.students.store', $section) }}" class="flex flex-col sm:flex-row gap-3 sm:items-end">
                @csrf
                <div class="flex-1">
                    <select name="student_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        <option value="">تسجيل طالب غير مقيد في هذه الشعبة...</option>
                        @foreach($availableStudents as $student)
                            <option value="{{ $student->id }}">{{ $student->name }} @if($student->guardian_name)({{ $student->guardian_name }})@endif</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">تسجيل في الشعبة</button>
            </form>
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

@section('scripts')
<script>
    const toggleBtn = document.querySelector('[data-toggle-section-edit]');
    const editForm = document.getElementById('section-edit-form');
    if (toggleBtn && editForm) {
        toggleBtn.addEventListener('click', () => editForm.classList.toggle('hidden'));
    }
</script>
@endsection
