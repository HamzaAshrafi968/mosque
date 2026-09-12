@extends('layouts.app')

@section('title', 'درجات '.$child->name)

@section('content')
@include('guardian.children.partials.header')

@php
    $passedCount = $grades->filter(fn ($row) => (float) $row['grade']->score >= (int) ($row['grade']->exam->pass_marks ?? 0))->count();
@endphp

@if($grades->isNotEmpty())
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        @foreach([
            ['إجمالي النتائج المنشورة', count($grades), 'grades', 'text-pine-950', 'bg-pine-100 text-pine-700'],
            ['نتائج ناجحة', $passedCount, 'check', 'text-emerald-700', 'bg-emerald-50 text-emerald-600'],
            ['نتائج تحتاج متابعة', count($grades) - $passedCount, 'alert', 'text-red-600', 'bg-red-50 text-red-500'],
        ] as $i => [$label, $value, $icon, $valueColor, $tint])
            <div class="reveal rd-{{ $i + 1 }} rounded-2xl bg-white border border-pine-950/[0.06] shadow-[0_1px_3px_rgba(5,32,25,0.05)] p-5 flex items-center gap-4">
                <span class="w-11 h-11 shrink-0 rounded-xl grid place-items-center {{ $tint }}"><x-icon name="{{ $icon }}" class="w-5 h-5" /></span>
                <div>
                    <div class="text-2xl font-black tabular-nums leading-none {{ $valueColor }}">{{ $value }}</div>
                    <div class="text-[11px] text-gray-400 font-bold mt-1.5">{{ $label }}</div>
                </div>
            </div>
        @endforeach
    </div>
@endif

<div class="reveal rounded-2xl bg-white border border-pine-950/[0.06] shadow-[0_1px_3px_rgba(5,32,25,0.05)] overflow-hidden">
    <header class="flex items-center justify-between px-5 sm:px-6 py-4 border-b border-gray-100">
        <h2 class="font-black text-pine-950 flex items-center gap-2.5 text-base">
            <span class="w-8 h-8 rounded-lg bg-gold-50 text-gold-600 grid place-items-center"><x-icon name="grades" class="w-4 h-4" /></span>
            الدرجات المنشورة
        </h2>
        <span class="text-[11px] font-bold text-gray-400">تظهر النتائج بعد اعتمادها من الإدارة فقط</span>
    </header>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50/80 text-gray-500 text-right text-xs">
                    <th class="px-5 py-3 font-bold">الامتحان</th>
                    <th class="px-5 py-3 font-bold">المادة</th>
                    <th class="px-5 py-3 font-bold">التاريخ</th>
                    <th class="px-5 py-3 font-bold">الدرجة</th>
                    <th class="px-5 py-3 font-bold">النسبة</th>
                    <th class="px-5 py-3 font-bold">النتيجة</th>
                </tr>
            </thead>
            <tbody>
                @forelse($grades as $row)
                    @php
                        $grade = $row['grade'];
                        $exam = $grade->exam;
                        $passed = (float) $grade->score >= (int) ($exam->pass_marks ?? 0);
                        $pctValue = (float) ($row['percentage'] ?? 0);
                    @endphp
                    <tr class="border-t border-gray-50 hover:bg-pine-100/25 transition">
                        <td class="px-5 py-3.5 font-black text-pine-950 whitespace-nowrap">{{ $exam->title }}</td>
                        <td class="px-5 py-3.5 text-gray-600 font-semibold whitespace-nowrap">{{ $exam->subject?->name ?? '—' }}</td>
                        <td class="px-5 py-3.5 text-gray-400 font-semibold whitespace-nowrap">{{ $exam->exam_date->format('Y/m/d') }}</td>
                        <td class="px-5 py-3.5 font-black text-pine-950 tabular-nums whitespace-nowrap">{{ $grade->score }} <span class="text-gray-300 font-bold">/ {{ $exam->total_marks }}</span></td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <div class="w-20 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                    <div class="h-full rounded-full {{ $passed ? 'bg-gradient-to-l from-emerald-400 to-emerald-600' : 'bg-gradient-to-l from-red-300 to-red-500' }}" style="width: {{ min(100, $pctValue) }}%"></div>
                                </div>
                                <span class="text-xs font-black text-gray-600 tabular-nums">{{ $row['percentage'] }}٪</span>
                            </div>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-black ring-1 {{ $passed ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-red-50 text-red-600 ring-red-200' }}">
                                <span class="w-1.5 h-1.5 rounded-full bg-current"></span>{{ $passed ? 'ناجح' : 'راسب' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-14 text-center">
                            <span class="inline-flex w-16 h-16 rounded-2xl bg-gray-50 text-gray-300 grid place-items-center mb-4"><x-icon name="grades" class="w-8 h-8" /></span>
                            <p class="text-gray-400 font-black">لم تُنشر أي درجات بعد</p>
                            <p class="text-gray-300 text-xs font-medium mt-1.5">تُنشر الدرجات هنا بعد اعتمادها من إدارة الجامع</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
