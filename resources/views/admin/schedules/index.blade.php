@extends('layouts.app')

@section('title', 'الجداول الدراسية')

@php
    $days = [0 => 'الأحد', 1 => 'الاثنين', 2 => 'الثلاثاء', 3 => 'الأربعاء', 4 => 'الخميس', 5 => 'الجمعة', 6 => 'السبت'];
@endphp

@section('content')
<div class="bg-white rounded-xl shadow overflow-hidden p-4 mb-6">
    <form method="GET" action="{{ route('admin.schedules.index') }}" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end mb-4">
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
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg w-full">بحث</button>
        </div>
    </form>

    <details class="mb-2">
        <summary class="cursor-pointer text-emerald-700 font-bold mb-2">إضافة حصة جديدة</summary>
        <form method="POST" action="{{ route('admin.schedules.store') }}" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
            @csrf
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
                <label class="block text-sm font-medium text-gray-700 mb-1">المادة</label>
                <select name="subject_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
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
                <label class="block text-sm font-medium text-gray-700 mb-1">وقت البداية</label>
                <input type="time" name="starts_at" required value="{{ old('starts_at') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">وقت النهاية</label>
                <input type="time" name="ends_at" required value="{{ old('ends_at') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>
            <div>
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg w-full">إضافة</button>
            </div>
        </form>
    </details>
</div>

<div class="mb-4">
    <button onclick="window.print()" class="bg-gray-700 hover:bg-gray-800 text-white font-bold px-4 py-2 rounded-lg">طباعة</button>
</div>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <table class="w-full">
        <thead>
            <tr class="bg-gray-50 text-gray-600 text-sm">
                <th class="px-4 py-3 text-right">اليوم</th>
                <th class="px-4 py-3 text-right">الوقت</th>
                <th class="px-4 py-3 text-right">الصف</th>
                <th class="px-4 py-3 text-right">الشعبة</th>
                <th class="px-4 py-3 text-right">المادة</th>
                <th class="px-4 py-3 text-right">المعلم</th>
                <th class="px-4 py-3 text-right">حذف</th>
            </tr>
        </thead>
        <tbody>
            @forelse($schedules as $schedule)
                <tr>
                    <td class="px-4 py-3 border-t font-bold">{{ $days[$schedule->day_of_week] }}</td>
                    <td class="px-4 py-3 border-t">{{ $schedule->starts_at }}–{{ $schedule->ends_at }}</td>
                    <td class="px-4 py-3 border-t">{{ $schedule->classroom?->name }}</td>
                    <td class="px-4 py-3 border-t">{{ $schedule->section?->name }}</td>
                    <td class="px-4 py-3 border-t">{{ $schedule->subject?->name }}</td>
                    <td class="px-4 py-3 border-t">{{ $schedule->teacher?->name }}</td>
                    <td class="px-4 py-3 border-t">
                        <form method="POST" action="{{ route('admin.schedules.destroy', $schedule) }}" onsubmit="return confirm('هل أنت متأكد؟')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline text-sm">حذف</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-6 text-center text-gray-500">لا توجد جداول</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
