@props(['counts'])

<div class="flex items-center gap-1.5 flex-wrap justify-end text-[11px] font-bold">
    <span class="rounded-full bg-pine-100 text-pine-800 px-2 py-0.5 whitespace-nowrap">{{ $counts['total'] }} طالب</span>
    <span class="rounded-full bg-emerald-50 text-emerald-700 px-2 py-0.5 whitespace-nowrap">حاضر {{ $counts['present'] }}</span>
    <span class="rounded-full bg-amber-50 text-amber-700 px-2 py-0.5 whitespace-nowrap">متأخر {{ $counts['late'] }}</span>
    <span class="rounded-full bg-red-50 text-red-600 px-2 py-0.5 whitespace-nowrap">غائب {{ $counts['absent'] }}</span>
    <span class="rounded-full bg-sky-50 text-sky-700 px-2 py-0.5 whitespace-nowrap">إذن {{ $counts['excused'] }}</span>
    @if($counts['unrecorded'] > 0)
        <span class="rounded-full bg-gray-100 text-gray-600 px-2 py-0.5 whitespace-nowrap">لم يُسجَّل {{ $counts['unrecorded'] }}</span>
    @endif
</div>
