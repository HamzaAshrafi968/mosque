@extends('layouts.app')

@section('title', 'دفعاتي')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">دفعاتي</h1>
        <p class="text-sm text-gray-500 mt-1">مدير الجامع هو من يودع لك الدفعات — تصلك هنا فوراً مع إشعار.</p>
    </div>
    <a href="{{ route('teacher.work-hours.index') }}" class="text-sm text-emerald-700 hover:underline">ساعات عملي ←</a>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl shadow p-5 text-center">
        <div class="text-sm text-gray-500 mb-1">الراتب الشهري</div>
        @if($salary !== null)
            <div class="text-2xl font-bold text-gray-800" dir="ltr">{{ number_format($salary, 2) }}</div>
        @else
            <div class="text-2xl font-bold text-gray-300">—</div>
        @endif
    </div>
    <div class="bg-white rounded-2xl shadow p-5 text-center">
        <div class="text-sm text-gray-500 mb-1">ساعات {{ \App\Support\QuranProgramSettings::monthLabel(now()->format('Y-m')) }}</div>
        <div class="text-2xl font-bold text-emerald-700">{{ $monthlyHours }} <span class="text-sm font-bold">ساعة</span></div>
    </div>
    <div class="bg-white rounded-2xl shadow p-5 text-center">
        <div class="text-sm text-gray-500 mb-1">ساعات منذ آخر دفعة</div>
        <div @class(['text-2xl font-bold', $hoursSinceLastPayment > 0 ? 'text-amber-600' : 'text-gray-400'])>
            {{ $hoursSinceLastPayment }} <span class="text-sm font-bold">ساعة</span>
        </div>
    </div>
    <div class="bg-white rounded-2xl shadow p-5 text-center">
        <div class="text-sm text-gray-500 mb-1">آخر دفعة</div>
        @if($lastPayment)
            <div class="text-2xl font-bold text-emerald-600" dir="ltr">{{ number_format((float) $lastPayment->amount, 2) }}</div>
            <div class="text-[11px] text-gray-400 mt-1">{{ $lastPayment->created_at->format('Y-m-d') }}</div>
        @else
            <div class="text-2xl font-bold text-gray-300">—</div>
        @endif
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-2xl shadow p-5 text-center">
        <div class="text-sm text-gray-500 mb-1">إجمالي المقبوضات</div>
        <div class="text-3xl font-bold text-emerald-600">{{ number_format($received, 2) }}</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-5 text-center">
        <div class="text-sm text-gray-500 mb-1">المسلّم والمدفوع (منها إيرادات الجامع)</div>
        <div class="text-3xl font-bold text-red-500">{{ number_format($handed, 2) }}</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-5 text-center {{ $remaining >= 0 ? 'bg-emerald-700' : 'bg-amber-600' }}">
        <div class="text-sm text-emerald-100 mb-1">المتبقي بيدك</div>
        <div class="text-3xl font-bold text-white">{{ number_format($remaining, 2) }}</div>
    </div>
</div>

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="p-4 border-b flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-lg font-bold text-gray-800">الدفعات الواردة إليّ</h2>
        <span class="text-xs text-gray-400">مدفوع هذا الشهر: <span class="font-bold text-gray-600" dir="ltr">{{ number_format($paidInMonth, 2) }}</span></span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-gray-500 text-right">
                    <th class="px-4 py-3 font-medium">التاريخ</th>
                    <th class="px-4 py-3 font-medium">البيان</th>
                    <th class="px-4 py-3 font-medium">المبلغ</th>
                    <th class="px-4 py-3 font-medium">سجّلها</th>
                    <th class="px-4 py-3 font-medium">الحالة</th>
                </tr>
            </thead>
            <tbody>
                @forelse($deposits as $tx)
                    <tr class="border-t border-gray-100">
                        <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ $tx->created_at->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $tx->description ?? 'دفعة' }}</td>
                        <td class="px-4 py-3 font-bold text-emerald-600 whitespace-nowrap">
                            +{{ number_format((float) $tx->amount, 2) }}
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $tx->creator?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if($tx->reversal)
                                <span class="text-xs px-2 py-1 rounded-lg bg-red-100 text-red-700 font-bold">معكوسة</span>
                            @else
                                <span class="text-xs px-2 py-1 rounded-lg bg-emerald-100 text-emerald-700 font-bold">مُودعة</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-400">لم تُودع لك أي دفعة بعد</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4">{{ $deposits->links() }}</div>
</div>
@endsection
