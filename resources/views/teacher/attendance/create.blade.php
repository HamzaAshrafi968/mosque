@extends('layouts.app')

@section('title', 'تسجيل الحضور')

@section('content')
<div class="bg-white rounded-xl shadow overflow-hidden mb-6">
    <h2 class="text-lg font-bold text-gray-800 p-4 border-b">اختيار الصف والتاريخ</h2>
    <div class="p-4">
        <form method="GET" action="{{ route('teacher.attendance.create') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الصف</label>
                <select name="classroom_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    <option value="">اختر الصف</option>
                    @foreach($classrooms as $classroom)
                        <option value="{{ $classroom->id }}" @selected($classroomId === $classroom->id)>{{ $classroom->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الشعبة</label>
                <select name="section_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    <option value="">كل الشعب</option>
                    @foreach($classrooms as $classroom)
                        @foreach($classroom->sections as $section)
                            <option value="{{ $section->id }}" @selected($sectionId === $section->id)>{{ $classroom->name }} - {{ $section->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">التاريخ</label>
                <input type="date" name="date" value="{{ $date }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>
            <div class="flex items-end">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">عرض الطلاب</button>
            </div>
        </form>
    </div>
</div>

@if($students->isNotEmpty())
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <h2 class="text-lg font-bold text-gray-800 p-4 border-b">تسجيل الحضور</h2>
        <form method="POST" action="{{ route('teacher.attendance.store') }}">
            @csrf
            <input type="hidden" name="date" value="{{ $date }}">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600 text-sm">
                            <th class="px-4 py-3 text-right whitespace-nowrap">الاسم</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap">الجنس</th>
                            <th class="px-4 py-3 text-center whitespace-nowrap">حالة الحضور</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($students as $student)
                            <tr>
                                <td class="px-4 py-3 border-t whitespace-nowrap">{{ $student->name }}</td>
                                <td class="px-4 py-3 border-t whitespace-nowrap">{{ $student->gender === 'male' ? 'ذكر' : 'أنثى' }}</td>
                                <td class="px-4 py-3 border-t">
                                    <div class="flex justify-center gap-4">
                                        <label class="inline-flex items-center gap-1">
                                            <input type="radio" name="statuses[{{ $student->id }}]" value="present"
                                                   @checked($existing->get($student->id, 'present') === 'present')>
                                            <span class="text-sm text-green-700 whitespace-nowrap">حاضر</span>
                                        </label>
                                        <label class="inline-flex items-center gap-1">
                                            <input type="radio" name="statuses[{{ $student->id }}]" value="absent"
                                                   @checked($existing->get($student->id) === 'absent')>
                                            <span class="text-sm text-red-700 whitespace-nowrap">غائب</span>
                                        </label>
                                        <label class="inline-flex items-center gap-1">
                                            <input type="radio" name="statuses[{{ $student->id }}]" value="late"
                                                   @checked($existing->get($student->id) === 'late')>
                                            <span class="text-sm text-yellow-700 whitespace-nowrap">متأخر</span>
                                        </label>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">حفظ الحضور</button>
            </div>
        </form>
    </div>
@endif
@endsection
