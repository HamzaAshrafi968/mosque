@extends('layouts.app')

@section('title', 'الحضور والغياب')

@section('content')
<div class="bg-white rounded-xl shadow overflow-hidden mb-6 p-4">
    <form method="GET" action="{{ route('admin.attendance.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">التاريخ</label>
            <input type="date" name="date" value="{{ $date }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">النوع</label>
            <select name="type" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="students" @selected($type === 'students')>جلسات الطلاب</option>
                <option value="teachers" @selected($type === 'teachers')>حضور المعلمين</option>
            </select>
        </div>
        @if($type === 'students')
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الشعبة</label>
                <select name="section_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">كل الشعب</option>
                    @foreach($classrooms as $classroom)
                        <optgroup label="{{ $classroom->name }}">
                            @foreach($classroom->sections as $sec)
                                <option value="{{ $sec->id }}" @selected(request('section_id') == $sec->id)>{{ $sec->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
        @endif
        <div>
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg w-full">بحث</button>
        </div>
    </form>
</div>

@if($type === 'teachers')
    <div class="bg-white rounded-xl shadow overflow-hidden mb-6 p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">تسجيل حضور معلم لليوم</h3>
        <form method="POST" action="{{ route('admin.attendance.store') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">المعلم</label>
                <select name="teacher_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">اختر المعلم...</option>
                    @foreach($teachers as $teacher)
                        <option value="{{ $teacher->id }}" @selected(old('teacher_id') === $teacher->id)>{{ $teacher->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">التاريخ</label>
                <input type="date" name="date" value="{{ old('date', $date) }}" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الحالة</label>
                <select name="status" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    @foreach(\App\Enums\AttendanceStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(old('status', 'present') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ملاحظات</label>
                <input type="text" name="notes" value="{{ old('notes') }}" maxlength="500"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div class="md:col-span-4">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-5 py-2 rounded-lg">حفظ</button>
            </div>
        </form>
    </div>
@endif

@if($type === 'students')
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="p-4 border-b flex items-center justify-between flex-wrap gap-3">
            <h3 class="font-bold text-gray-800">جلسات الطلاب — {{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('l Y-m-d') }}</h3>
            <a href="{{ route('admin.attendance.create', ['date' => $date]) }}"
               class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">تسجيل جلسة حضور جديدة</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600">
                        <th class="px-4 py-3 text-right whitespace-nowrap">الشعبة</th>
                        <th class="px-4 py-3 text-center whitespace-nowrap">حاضر</th>
                        <th class="px-4 py-3 text-center whitespace-nowrap">غائب</th>
                        <th class="px-4 py-3 text-center whitespace-nowrap">متأخر</th>
                        <th class="px-4 py-3 text-center whitespace-nowrap">معذور</th>
                        <th class="px-4 py-3 text-center whitespace-nowrap">المجموع</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">سجله</th>
                        <th class="px-4 py-3 text-center whitespace-nowrap">إجراء</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sessions as $row)
                        <tr>
                            <td class="px-4 py-3 border-t font-bold whitespace-nowrap">
                                {{ $row['session']->section->classroom?->name }} / {{ $row['session']->section->name }}
                            </td>
                            <td class="px-4 py-3 border-t text-center text-green-700">{{ $row['present'] }}</td>
                            <td class="px-4 py-3 border-t text-center text-red-700">{{ $row['absent'] }}</td>
                            <td class="px-4 py-3 border-t text-center text-yellow-700">{{ $row['late'] }}</td>
                            <td class="px-4 py-3 border-t text-center text-sky-700">{{ $row['excused'] }}</td>
                            <td class="px-4 py-3 border-t text-center font-bold">{{ $row['total'] }}</td>
                            <td class="px-4 py-3 border-t whitespace-nowrap">{{ $row['session']->createdBy?->name ?? '—' }}</td>
                            <td class="px-4 py-3 border-t text-center whitespace-nowrap">
                                <a href="{{ route('admin.attendance.sessions.edit', $row['session']) }}" class="text-emerald-700 hover:underline text-sm">عرض / تعديل</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">لا توجد جلسات حضور مسجلة لهذا اليوم</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4 flex gap-3 flex-wrap">
        <a href="{{ route('admin.attendance.history') }}" class="bg-gray-700 hover:bg-gray-800 text-white text-sm font-bold px-4 py-2 rounded-lg">جدول الحضور التفصيلي (نسب)</a>
    </div>
@else
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600">
                        <th class="px-4 py-3 text-right whitespace-nowrap">المعلم</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">التاريخ</th>
                        <th class="px-4 py-3 text-center whitespace-nowrap">الحالة</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">ملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($teacherRows as $attendance)
                        <tr>
                            <td class="px-4 py-3 border-t whitespace-nowrap">{{ $attendance->teacher?->name }}</td>
                            <td class="px-4 py-3 border-t whitespace-nowrap">{{ $attendance->date->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 border-t text-center"><x-attendance-status-badge :status="$attendance->status" /></td>
                            <td class="px-4 py-3 border-t text-sm text-gray-500">{{ $attendance->notes }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-gray-500">لا توجد سجلات حضور للمعلمين</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
