@php
    $statusLabel = ['present' => 'حاضر', 'absent' => 'غائب', 'late' => 'متأخر', 'excused' => 'معذور'];
    $statusColor = ['present' => 'bg-emerald-100 text-emerald-700', 'absent' => 'bg-red-100 text-red-700', 'late' => 'bg-amber-100 text-amber-700', 'excused' => 'bg-blue-100 text-blue-700'];
@endphp
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow p-4 text-center">
        <div class="text-2xl font-bold text-emerald-600">{{ $summary['present'] }}</div>
        <div class="text-sm text-gray-500 mt-1">حاضر</div>
    </div>
    <div class="bg-white rounded-xl shadow p-4 text-center">
        <div class="text-2xl font-bold text-red-500">{{ $summary['absent'] }}</div>
        <div class="text-sm text-gray-500 mt-1">غائب</div>
    </div>
    <div class="bg-white rounded-xl shadow p-4 text-center">
        <div class="text-2xl font-bold text-amber-500">{{ $summary['late'] }}</div>
        <div class="text-sm text-gray-500 mt-1">متأخر</div>
    </div>
    <div class="bg-white rounded-xl shadow p-4 text-center">
        <div class="text-2xl font-bold text-blue-500">{{ $summary['excused'] }}</div>
        <div class="text-sm text-gray-500 mt-1">معذور</div>
    </div>
    <div class="col-span-2 lg:col-span-1 bg-emerald-700 text-white rounded-xl shadow p-4 text-center">
        <div class="text-2xl font-bold">{{ $summary['percentage'] !== null ? $summary['percentage'].'٪' : '—' }}</div>
        <div class="text-sm text-emerald-100 mt-1">نسبة الحضور</div>
    </div>
</div>
