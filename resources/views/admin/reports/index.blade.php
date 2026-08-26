@extends('layouts.app')

@section('title', 'التقارير')

@section('content')
<div class="bg-white rounded-xl shadow overflow-hidden mb-6">
    <div class="border-b">
        <a href="{{ route('admin.reports.index', ['type' => 'students']) }}"
           @class(['px-4 py-2 inline-block text-sm font-bold', 'bg-emerald-700 text-white' => $type === 'students', 'text-gray-600 hover:bg-gray-50' => $type !== 'students'])>
            تقرير الطلاب
        </a>
        <a href="{{ route('admin.reports.index', ['type' => 'teachers']) }}"
           @class(['px-4 py-2 inline-block text-sm font-bold', 'bg-emerald-700 text-white' => $type === 'teachers', 'text-gray-600 hover:bg-gray-50' => $type !== 'teachers'])>
            تقرير المعلمين
        </a>
        <a href="{{ route('admin.reports.index', ['type' => 'attendance']) }}"
           @class(['px-4 py-2 inline-block text-sm font-bold', 'bg-emerald-700 text-white' => $type === 'attendance', 'text-gray-600 hover:bg-gray-50' => $type !== 'attendance'])>
            تقرير الحضور والغياب
        </a>
        <a href="{{ route('admin.reports.index', ['type' => 'grades']) }}"
           @class(['px-4 py-2 inline-block text-sm font-bold', 'bg-emerald-700 text-white' => $type === 'grades', 'text-gray-600 hover:bg-gray-50' => $type !== 'grades'])>
            تقرير النتائج
        </a>
    </div>
</div>

@if($type === 'attendance' || $type === 'grades')
<div class="bg-white rounded-xl shadow overflow-hidden p-4 mb-6">
    <form method="GET" action="{{ route('admin.reports.index') }}" class="grid grid-cols-2 md:grid-cols-4 gap-3 items-end">
        <input type="hidden" name="type" value="{{ $type }}">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">من</label>
            <input type="date" name="from" value="{{ $from }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">إلى</label>
            <input type="date" name="to" value="{{ $to }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg w-full">بحث</button>
        </div>
    </form>
</div>
@endif

<div class="mb-4">
    <button onclick="window.print()" class="bg-gray-700 hover:bg-gray-800 text-white font-bold px-4 py-2 rounded-lg">طباعة</button>
</div>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            @if($type === 'students')
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-sm">
                        <th class="px-4 py-3 text-right whitespace-nowrap">الاسم</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">الجنس</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">الصف</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">الشعبة</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $student)
                        <tr>
                            <td class="px-4 py-3 border-t font-bold whitespace-nowrap">{{ $student->name }}</td>
                            <td class="px-4 py-3 border-t whitespace-nowrap">{{ $student->gender === 'male' ? 'ذكر' : 'أنثى' }}</td>
                            <td class="px-4 py-3 border-t whitespace-nowrap">{{ $student->classroom?->name }}</td>
                            <td class="px-4 py-3 border-t whitespace-nowrap">{{ $student->section?->name }}</td>
                            <td class="px-4 py-3 border-t whitespace-nowrap">{{ $student->status === 'active' ? 'نشط' : 'مؤرشف' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            @elseif($type === 'teachers')
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-sm">
                        <th class="px-4 py-3 text-right whitespace-nowrap">الاسم</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">الجنس</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">التخصص</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">عدد المواد</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $teacher)
                        <tr>
                            <td class="px-4 py-3 border-t font-bold whitespace-nowrap">{{ $teacher->name }}</td>
                            <td class="px-4 py-3 border-t whitespace-nowrap">{{ $teacher->gender === 'male' ? 'ذكر' : 'أنثى' }}</td>
                            <td class="px-4 py-3 border-t whitespace-nowrap">{{ $teacher->specialty }}</td>
                            <td class="px-4 py-3 border-t whitespace-nowrap">{{ $teacher->subjects_count }}</td>
                            <td class="px-4 py-3 border-t whitespace-nowrap">
                                <span @class([
                                    'px-2 py-1 rounded-full text-xs font-bold',
                                    'bg-green-100 text-green-800' => $teacher->is_active,
                                    'bg-red-100 text-red-800' => !$teacher->is_active,
                                ])>
                                    {{ $teacher->is_active ? 'نشط' : 'غير نشط' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            @elseif($type === 'attendance')
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-sm">
                        <th class="px-4 py-3 text-right whitespace-nowrap">التاريخ</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">الاسم</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $attendance)
                        <tr>
                            <td class="px-4 py-3 border-t whitespace-nowrap">{{ $attendance->date->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 border-t whitespace-nowrap">{{ $attendance->student?->name ?? $attendance->teacher?->name }}</td>
                            <td class="px-4 py-3 border-t whitespace-nowrap">
                                @if($attendance->status === 'present')
                                    <span class="px-2 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800">حاضر</span>
                                @elseif($attendance->status === 'absent')
                                    <span class="px-2 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800">غائب</span>
                                @elseif($attendance->status === 'late')
                                    <span class="px-2 py-1 rounded-full text-xs font-bold bg-yellow-100 text-yellow-800">متأخر</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            @elseif($type === 'grades')
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-sm">
                        <th class="px-4 py-3 text-right whitespace-nowrap">الطالب</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">المادة</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">الاختبار</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">الدرجة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $grade)
                        <tr>
                            <td class="px-4 py-3 border-t font-bold whitespace-nowrap">{{ $grade->student?->name }}</td>
                            <td class="px-4 py-3 border-t whitespace-nowrap">{{ $grade->exam?->subject?->name }}</td>
                            <td class="px-4 py-3 border-t whitespace-nowrap">{{ $grade->exam?->title }}</td>
                            <td class="px-4 py-3 border-t whitespace-nowrap">{{ $grade->score }} / {{ $grade->exam?->total_marks }}</td>
                        </tr>
                    @endforeach
                </tbody>
            @endif
        </table>
    </div>
</div>
@endsection
