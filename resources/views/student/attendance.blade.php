@extends('layouts.app')

@section('title', 'الحضور والغياب')

@section('content')
<h1 class="text-2xl font-bold text-gray-800 mb-6">الحضور والغياب</h1>

<x-attendance-summary :summary="$summary" />

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <h2 class="text-lg font-bold text-gray-800 p-4 border-b">سجل الحضور</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-gray-500 text-right">
                    <th class="px-4 py-3 font-medium">التاريخ</th>
                    <th class="px-4 py-3 font-medium">الشعبة</th>
                    <th class="px-4 py-3 font-medium">الحالة</th>
                    <th class="px-4 py-3 font-medium">ملاحظة</th>
                </tr>
            </thead>
            <tbody>
                @forelse($history as $row)
                    <tr class="border-t border-gray-100">
                        <td class="px-4 py-3">{{ $row['date'] }}</td>
                        <td class="px-4 py-3">{{ $row['section'] ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @php
                                $labels = ['present' => 'حاضر', 'absent' => 'غائب', 'late' => 'متأخر', 'excused' => 'معذور'];
                                $colors = ['present' => 'bg-emerald-100 text-emerald-700', 'absent' => 'bg-red-100 text-red-700', 'late' => 'bg-amber-100 text-amber-700', 'excused' => 'bg-blue-100 text-blue-700'];
                            @endphp
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold {{ $colors[$row['status']->value] ?? 'bg-gray-100 text-gray-600' }}">
                                {{ $labels[$row['status']->value] ?? $row['status']->value }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $row['note'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-gray-400">لا توجد سجلات حضور بعد</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
