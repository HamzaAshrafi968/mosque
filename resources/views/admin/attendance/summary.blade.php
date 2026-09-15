@extends('layouts.app')

@section('title', 'الحضور والغياب والتأخير')

@section('content')
@php
    $authorization = app(\App\Services\AuthorizationService::class);
    $can = fn (string $permission) => $authorization->can(auth()->user(), $permission);
@endphp
<div class="bg-white rounded-xl shadow overflow-hidden mb-6 p-4">
    <form method="GET" action="{{ route('admin.attendance.summary') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
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
        <div>
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg w-full">عرض</button>
        </div>
    </form>
</div>

<div class="mb-4 flex items-center justify-between flex-wrap gap-2">
    <h2 class="text-lg font-bold text-gray-800">
        حضور وغياب الطلاب
        <span class="text-sm font-normal text-gray-500">من {{ $from }} إلى {{ $to }}</span>
    </h2>
    <span class="text-sm text-gray-500">عدد الطلاب: {{ $rows->count() }}</span>
</div>

<x-attendance-summary :summary="$totals" />

<div class="bg-white rounded-xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-gray-600">
                    <th class="px-4 py-3 text-right whitespace-nowrap">الطالب</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">الصف</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">الشعبة</th>
                    <th class="px-4 py-3 text-center whitespace-nowrap">حاضر</th>
                    <th class="px-4 py-3 text-center whitespace-nowrap">غائب</th>
                    <th class="px-4 py-3 text-center whitespace-nowrap">متأخر</th>
                    <th class="px-4 py-3 text-center whitespace-nowrap">معذور</th>
                    <th class="px-4 py-3 text-center whitespace-nowrap">نسبة الحضور</th>
                    <th class="px-4 py-3 text-center whitespace-nowrap">إجراء</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    @php
                        $low = $row['percentage'] !== null && $row['percentage'] < 75;
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 border-t font-bold whitespace-nowrap">{{ $row['student']->name }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $row['student']->classroom?->name ?? '—' }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $row['student']->section?->name ?? '—' }}</td>
                        <td class="px-4 py-3 border-t text-center text-green-700 font-bold">{{ $row['present'] }}</td>
                        <td class="px-4 py-3 border-t text-center text-red-700 font-bold">{{ $row['absent'] }}</td>
                        <td class="px-4 py-3 border-t text-center text-yellow-700 font-bold">{{ $row['late'] }}</td>
                        <td class="px-4 py-3 border-t text-center text-sky-700 font-bold">{{ $row['excused'] }}</td>
                        <td class="px-4 py-3 border-t text-center whitespace-nowrap">
                            @if($row['percentage'] !== null)
                                <span @class([
                                    'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-black',
                                    'bg-red-50 text-red-600' => $low,
                                    'bg-emerald-50 text-emerald-700' => ! $low,
                                ])>
                                    {{ $row['percentage'] }}%
                                </span>
                                <div class="text-[11px] text-gray-400 font-normal mt-0.5" dir="ltr">
                                    {{ $row['attended'] }}/{{ $row['total'] }}
                                </div>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 border-t text-center whitespace-nowrap">
                            @if($can('students.view'))
                                <a href="{{ route('admin.students.show', $row['student']) }}" class="text-emerald-700 hover:underline text-sm">ملف الطالب</a>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-gray-500">لا يوجد طلاب مطابقون للبحث</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-4 py-3 border-t bg-gray-50 text-xs text-gray-500">
        قاعدة احتساب النسبة: حاضر ومتأخر = حضر، غائب = لا يحتسب حضوراً، معذور = مستبعد من المقام والبسط.
    </div>
</div>

<div class="mt-4 flex gap-3 flex-wrap">
    <a href="{{ route('admin.attendance.index', ['date' => $to]) }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">جلسات اليوم</a>
    @if($can('attendance.create'))
        <a href="{{ route('admin.attendance.create') }}" class="bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-bold px-4 py-2 rounded-lg">تسجيل جلسة حضور جديدة</a>
    @endif
    <a href="{{ route('admin.attendance.history', ['from' => $from, 'to' => $to]) }}" class="bg-gray-700 hover:bg-gray-800 text-white text-sm font-bold px-4 py-2 rounded-lg">الجدول التفصيلي (نسب)</a>
</div>
@endsection
