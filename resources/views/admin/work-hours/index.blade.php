@extends('layouts.app')

@section('title', 'ساعات عمل المشرفين')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-800">ساعات عمل المشرفين</h2>
            <p class="text-sm text-gray-500 mt-1">جدول أسبوعي متكرر لكل معلم/مشرف — الإجمالي الأسبوعي والشهري محسوبان تلقائياً</p>
        </div>
        <a href="{{ route('admin.payroll.index') }}" class="text-sm font-bold text-emerald-700 hover:underline">رواتب المعلمين ←</a>
    </div>

    <form method="GET" action="{{ route('admin.work-hours.index') }}" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
        <div class="md:col-span-2">
            <label class="block text-xs font-bold text-gray-600 mb-1">بحث بالاسم</label>
            <input type="text" name="search" value="{{ $search }}" placeholder="اسم المعلم" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">اليوم</label>
            <select name="day" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">كل الأيام</option>
                @foreach($days as $dayOption)
                    <option value="{{ $dayOption->value }}" @selected($day === $dayOption->value)>{{ $dayOption->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">شهر الإجمالي</label>
            <input type="month" name="month" value="{{ $monthInput }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">تصفية</button>
    </form>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600">
                        <th class="px-4 py-3 text-right">المعلم</th>
                        <th class="px-4 py-3 text-right">عدد الفترات</th>
                        <th class="px-4 py-3 text-right">الإجمالي الأسبوعي</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">إجمالي {{ \App\Support\QuranProgramSettings::monthLabel($month->format('Y-m')) }}</th>
                        <th class="px-4 py-3 text-right">الراتب الشهري</th>
                        <th class="px-4 py-3 text-right">فترات اليوم</th>
                        <th class="px-4 py-3 text-center">إجراء</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($teachers as $teacher)
                    @php
                        $todayHours = $teacher->workHours->where('day_of_week', now()->dayOfWeek);
                        $total = $totals[$teacher->id] ?? 0;
                        $monthly = $monthlyTotals[$teacher->id] ?? 0;
                    @endphp
                    <tr class="border-t">
                        <td class="px-4 py-3 whitespace-nowrap font-bold text-gray-800">{{ $teacher->name }}</td>
                        <td class="px-4 py-3">{{ $teacher->workHours->count() }} فترة</td>
                        <td class="px-4 py-3">
                            <span class="font-bold text-emerald-700">{{ $total > 0 ? $total.' ساعة' : '—' }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="font-bold text-pine-800">{{ $monthly > 0 ? $monthly.' ساعة' : '—' }}</span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if($teacher->monthly_salary !== null)
                                <span class="font-bold text-gray-700" dir="ltr">{{ number_format((float) $teacher->monthly_salary, 2) }}</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @forelse($todayHours as $hour)
                                <span class="inline-flex items-center gap-1 text-xs bg-pine-50 text-pine-800 rounded-lg px-2 py-1 me-1 mb-1">
                                    {{ substr($hour->start_time, 0, 5) }} — {{ substr($hour->end_time, 0, 5) }}
                                </span>
                            @empty
                                <span class="text-gray-300">—</span>
                            @endforelse
                        </td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            <a href="{{ route('admin.teachers.work-hours.index', $teacher) }}" class="text-emerald-700 hover:underline text-xs font-bold">إدارة الساعات</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">لا يوجد معلمون مطابقون</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-100">{{ $teachers->links() }}</div>
    </div>
</div>
@endsection
