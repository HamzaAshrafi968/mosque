@extends('layouts.app')

@section('title', 'طلاب شعبة '.$section->name)

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">طلاب شعبة {{ $section->name }}</h1>
        <p class="text-gray-500 text-sm mt-1">{{ $section->classroom?->name }} — الفترة {{ $from }} إلى {{ $to }}</p>
    </div>
    <div class="flex gap-2 flex-wrap">
        <a href="{{ route('teacher.attendance.create', ['section_id' => $section->id]) }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">تسجيل الحضور</a>
        <a href="{{ route('teacher.attendance.history', ['section_id' => $section->id]) }}" class="bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-bold px-4 py-2 rounded-lg">سجل الحضور</a>
    </div>
</div>

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-gray-500 text-right">
                    <th class="px-4 py-3 font-medium">الطالب</th>
                    <th class="px-4 py-3 font-medium">حاضر</th>
                    <th class="px-4 py-3 font-medium">غائب</th>
                    <th class="px-4 py-3 font-medium">متأخر</th>
                    <th class="px-4 py-3 font-medium">معذور</th>
                    <th class="px-4 py-3 font-medium">نسبة الحضور</th>
                    <th class="px-4 py-3 font-medium">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($roster as $stats)
                    <tr class="border-t border-gray-100">
                        <td class="px-4 py-3 font-bold text-gray-800">{{ $stats['student']->name }}</td>
                        <td class="px-4 py-3 text-emerald-600">{{ $stats['present'] }}</td>
                        <td class="px-4 py-3 text-red-500">{{ $stats['absent'] }}</td>
                        <td class="px-4 py-3 text-amber-500">{{ $stats['late'] }}</td>
                        <td class="px-4 py-3 text-blue-500">{{ $stats['excused'] }}</td>
                        <td class="px-4 py-3">
                            @if($stats['percentage'] !== null)
                                <span class="px-2 py-1 rounded-lg text-xs font-bold {{ $stats['percentage'] < 75 ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700' }}">
                                    {{ $stats['percentage'] }}٪
                                </span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('teacher.quran-review.create', ['student_id' => $stats['student']->id]) }}" class="text-xs text-emerald-700 hover:underline">مراجعة</a>
                            <span class="text-gray-200 mx-1">|</span>
                            <a href="{{ route('teacher.reward-points.create', ['student_id' => $stats['student']->id]) }}" class="text-xs text-amber-600 hover:underline">نقاط</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-400">لا يوجد طلاب نشطون في هذه الشعبة</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
