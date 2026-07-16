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
    <table class="w-full">
        @if($type === 'students')
            <thead>
                <tr class="bg-gray-50 text-gray-600 text-sm">
                    <th class="px-4 py-3 text-right">الاسم</th>
                    <th class="px-4 py-3 text-right">الجنس</th>
                    <th class="px-4 py-3 text-right">الصف</th>
                    <th class="px-4 py-3 text-right">الشعبة</th>
                    <th class="px-4 py-3 text-right">الحالة</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $student)
                    <tr>
                        <td class="px-4 py-3 border-t font-bold">{{ $student->name }}</td>
                        <td class="px-4 py-3 border-t">{{ $student->gender === 'male' ? 'ذكر' : 'أنثى' }}</td>
                        <td class="px-4 py-3 border-t">{{ $student->classroom?->name }}</td>
                        <td class="px-4 py-3 border-t">{{ $student->section?->name }}</td>
                        <td class="px-4 py-3 border-t">{{ $student->status === 'active' ? 'نشط' : 'مؤرشف' }}</td>
                    </tr>
                @endforeach
            </tbody>
        @elseif($type === 'teachers')
            <thead>
                <tr class="bg-gray-50 text-gray-600 text-sm">
                    <th class="px-4 py-3 text-right">الاسم</th>
                    <th class="px-4 py-3 text-right">الجنس</th>
                    <th class="px-4 py-3 text-right">التخصص</th>
                    <th class="px-4 py-3 text-right">عدد المواد</th>
                    <th class="px-4 py-3 text-right">الحالة</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $teacher)
                    <tr>
                        <td class="px-4 py-3 border-t font-bold">{{ $teacher->name }}</td>
                        <td class="px-4 py-3 border-t">{{ $teacher->gender === 'male' ? 'ذكر' : 'أنثى' }}</td>
                        <td class="px-4 py-3 border-t">{{ $teacher->specialty }}</td>
                        <td class="px-4 py-3 border-t">{{ $teacher->subjects_count }}</td>
                        <td class="px-4 py-3 border-t">
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
                    <th class="px-4 py-3 text-right">التاريخ</th>
                    <th class="px-4 py-3 text-right">الاسم</th>
                    <th class="px-4 py-3 text-right">الحالة</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $attendance)
                    <tr>
                        <td class="px-4 py-3 border-t">{{ $attendance->date->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 border-t">{{ $attendance->student?->name ?? $attendance->teacher?->name }}</td>
                        <td class="px-4 py-3 border-t">
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
                    <th class="px-4 py-3 text-right">الطالب</th>
                    <th class="px-4 py-3 text-right">المادة</th>
                    <th class="px-4 py-3 text-right">الاختبار</th>
                    <th class="px-4 py-3 text-right">الدرجة</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $grade)
                    <tr>
                        <td class="px-4 py-3 border-t font-bold">{{ $grade->student?->name }}</td>
                        <td class="px-4 py-3 border-t">{{ $grade->exam?->subject?->name }}</td>
                        <td class="px-4 py-3 border-t">{{ $grade->exam?->title }}</td>
                        <td class="px-4 py-3 border-t">{{ $grade->score }} / {{ $grade->exam?->total_marks }}</td>
                    </tr>
                @endforeach
            </tbody>
        @endif
    </table>
</div>
@endsection
