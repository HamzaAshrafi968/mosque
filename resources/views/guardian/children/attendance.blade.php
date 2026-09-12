@extends('layouts.app')

@section('title', 'حضور '.$child->name)

@section('content')
@include('guardian.children.partials.header')

<div class="reveal">
    <x-attendance-summary :summary="$summary" />
</div>

<div class="reveal rd-1 rounded-2xl bg-white border border-pine-950/[0.06] shadow-[0_1px_3px_rgba(5,32,25,0.05)] overflow-hidden">
    <header class="flex items-center justify-between px-5 sm:px-6 py-4 border-b border-gray-100">
        <h2 class="font-black text-pine-950 flex items-center gap-2.5 text-base">
            <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 grid place-items-center"><x-icon name="history" class="w-4 h-4" /></span>
            سجل الحضور
        </h2>
        <span class="text-[11px] font-bold text-gray-400">{{ count($history) }} جلسة</span>
    </header>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50/80 text-gray-500 text-right text-xs">
                    <th class="px-5 py-3 font-bold">التاريخ</th>
                    <th class="px-5 py-3 font-bold">الشعبة</th>
                    <th class="px-5 py-3 font-bold">الحالة</th>
                    <th class="px-5 py-3 font-bold">ملاحظة</th>
                </tr>
            </thead>
            <tbody>
                @forelse($history as $row)
                    @php
                        $labels = ['present' => 'حاضر', 'absent' => 'غائب', 'late' => 'متأخر', 'excused' => 'معذور'];
                        $colors = [
                            'present' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                            'absent' => 'bg-red-50 text-red-600 ring-red-200',
                            'late' => 'bg-amber-50 text-amber-600 ring-amber-200',
                            'excused' => 'bg-sky-50 text-sky-600 ring-sky-200',
                        ];
                        $key = $row['status']->value ?? null;
                    @endphp
                    <tr class="border-t border-gray-50 hover:bg-pine-100/25 transition">
                        <td class="px-5 py-3.5 font-bold text-pine-950 whitespace-nowrap">{{ $row['date'] }}</td>
                        <td class="px-5 py-3.5 text-gray-500 font-semibold whitespace-nowrap">{{ $row['section'] ?? '—' }}</td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-black ring-1 {{ $colors[$key] ?? 'bg-gray-50 text-gray-500 ring-gray-200' }}">
                                <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                {{ $labels[$key] ?? ($row['status'] ?? '—') }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-gray-500">{{ $row['note'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center">
                            <span class="inline-flex w-14 h-14 rounded-2xl bg-gray-50 text-gray-300 grid place-items-center mb-3"><x-icon name="attendance" class="w-7 h-7" /></span>
                            <p class="text-gray-400 font-black text-sm">لا توجد سجلات حضور بعد</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
