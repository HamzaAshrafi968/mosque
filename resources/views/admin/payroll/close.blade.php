@extends('layouts.app')

@section('title', 'إغلاق الشهر')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('admin.payroll.index', ['month' => $monthInput]) }}" class="text-sm text-emerald-700 hover:text-emerald-800">← رواتب المعلمين</a>
            <h2 class="text-2xl font-extrabold text-gray-800 mt-1">إغلاق كشوف الشهر</h2>
            <p class="text-sm text-gray-500 mt-1">
                راجع التحذيرات قبل الإغلاق — الإغلاق يثبّت اللقطة ويمنع تعديل فترات الشهر.
            </p>
        </div>
        <form method="GET" action="{{ route('admin.payroll.close-preview') }}" class="flex items-center gap-2">
            <input type="month" name="month" value="{{ $monthInput }}" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
            <button class="text-xs font-bold text-gray-600 hover:text-gray-800">عرض الشهر</button>
        </form>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-x-reverse divide-gray-100 bg-white rounded-2xl shadow-sm border border-gray-200 text-center overflow-hidden">
        <div class="p-4">
            <div class="text-lg font-black text-emerald-700">{{ \App\Models\WorkSlot::formatMinutes($totals['minutes']) }}</div>
            <div class="text-[11px] text-gray-500 mt-0.5">ساعات {{ \App\Support\QuranProgramSettings::monthLabel($monthInput) }}</div>
        </div>
        <div class="p-4">
            <div class="text-lg font-black text-gray-800" dir="ltr">{{ number_format($totals['gross'], 2) }}</div>
            <div class="text-[11px] text-gray-500 mt-0.5">إجمالي الرواتب</div>
        </div>
        <div class="p-4">
            <div class="text-lg font-black text-emerald-700" dir="ltr">{{ number_format($totals['paid'], 2) }}</div>
            <div class="text-[11px] text-gray-500 mt-0.5">المدفوع</div>
        </div>
        <div class="p-4">
            <div class="text-lg font-black text-amber-600" dir="ltr">{{ number_format($totals['remaining'], 2) }}</div>
            <div class="text-[11px] text-gray-500 mt-0.5">المتبقي</div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <caption class="sr-only">معاينة إغلاق كشوف {{ $monthInput }}</caption>
                <thead>
                    <tr class="bg-gray-50 text-gray-600">
                        <th scope="col" class="px-4 py-3 text-right">المعلم</th>
                        <th scope="col" class="px-4 py-3 text-right">الفعلي</th>
                        <th scope="col" class="px-4 py-3 text-right">الإجمالي</th>
                        <th scope="col" class="px-4 py-3 text-right">المدفوع</th>
                        <th scope="col" class="px-4 py-3 text-right">المتبقي</th>
                        <th scope="col" class="px-4 py-3 text-right">الحالة</th>
                        <th scope="col" class="px-4 py-3 text-right">تحذيرات</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($teachers as $teacher)
                    @php $summary = $summaries[$teacher->id] ?? null; @endphp
                    @if($summary)
                        <tr class="border-t">
                            <td class="px-4 py-3 whitespace-nowrap font-bold text-gray-800">{{ $teacher->name }}</td>
                            <td class="px-4 py-3">
                                <x-duration :minutes="$summary['total_minutes']" class="font-bold" />
                            </td>
                            <td class="px-4 py-3 font-bold text-gray-800" dir="ltr">{{ number_format($summary['gross'], 2) }}</td>
                            <td class="px-4 py-3 text-emerald-700" dir="ltr">{{ number_format($summary['paid'], 2) }}</td>
                            <td class="px-4 py-3 font-bold" dir="ltr">
                                <span @class(['text-amber-600' => $summary['remaining'] > 0, 'text-gray-300' => $summary['remaining'] <= 0])>
                                    {{ number_format($summary['remaining'], 2) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if($summary['status'] === \App\Enums\PayrollStatus::Closed)
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-gray-200 text-gray-600">مغلق</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $summary['state']->badgeClasses() }}">{{ $summary['state']->label() }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @forelse($warnings[$teacher->id] ?? [] as $warning)
                                    <div class="text-[11px] text-amber-700">{{ $warning }}</div>
                                @empty
                                    <span class="text-[11px] text-gray-300">—</span>
                                @endforelse
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">لا يوجد معلمون</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
        <h3 class="font-black text-pine-950 mb-2">تأكيد الإغلاق</h3>
        <p class="text-sm text-gray-500 mb-3">
            سيتم إغلاق كل الكشوف المفتوحة التي لها ساعات أو راتب. اكتب الشهر <span class="font-bold" dir="ltr">{{ $monthInput }}</span> للتأكيد.
        </p>
        <form method="POST" action="{{ route('admin.payroll.close-all') }}" class="flex flex-wrap items-end gap-2"
              onsubmit="return confirm('إغلاق كل كشوف هذا الشهر؟')">
            @csrf
            <input type="hidden" name="month" value="{{ $monthInput }}">
            <div>
                <label class="block text-[11px] font-bold text-gray-500 mb-0.5">تأكيد الشهر</label>
                <input type="text" name="confirm_month" required placeholder="{{ $monthInput }}"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm" dir="ltr">
            </div>
            <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">إغلاق كل الكشوف</button>
        </form>
    </div>
</div>
@endsection
