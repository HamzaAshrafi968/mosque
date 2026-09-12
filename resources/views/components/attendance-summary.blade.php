@php
    $tiles = [
        'present' => ['label' => 'حاضر', 'icon' => 'check', 'text' => 'text-emerald-600', 'bg' => 'bg-emerald-100 text-emerald-600'],
        'absent' => ['label' => 'غائب', 'icon' => 'x', 'text' => 'text-red-500', 'bg' => 'bg-red-100 text-red-500'],
        'late' => ['label' => 'متأخر', 'icon' => 'clock', 'text' => 'text-amber-500', 'bg' => 'bg-amber-100 text-amber-500'],
        'excused' => ['label' => 'معذور', 'icon' => 'user-check', 'text' => 'text-sky-600', 'bg' => 'bg-sky-100 text-sky-600'],
    ];
    $pct = $summary['percentage'] ?? null;
    $low = $pct !== null && $pct < 75;
@endphp

<div class="grid grid-cols-2 xl:grid-cols-5 gap-3.5 sm:gap-4 mb-7">
    @foreach($tiles as $key => $tile)
        <div class="rounded-2xl bg-white border border-pine-950/[0.06] shadow-[0_1px_3px_rgba(5,32,25,0.05)] p-4 sm:p-5 flex items-center justify-between gap-2 card-hover">
            <div>
                <div class="text-2xl sm:text-3xl font-black tabular-nums leading-none {{ $tile['text'] }}">{{ $summary[$key] }}</div>
                <div class="text-[11px] text-gray-400 font-bold mt-2">{{ $tile['label'] }}</div>
            </div>
            <span class="w-10 h-10 rounded-xl grid place-items-center {{ $tile['bg'] }} shrink-0">
                <x-icon name="{{ $tile['icon'] }}" class="w-5 h-5" />
            </span>
        </div>
    @endforeach

    <div class="col-span-2 xl:col-span-1 rounded-2xl {{ $low ? 'bg-gradient-to-br from-red-500 to-red-700' : 'bg-gradient-to-br from-emerald-500 via-pine-600 to-pine-800' }} text-white p-4 sm:p-5 flex flex-col justify-between shadow-lg shadow-pine-900/20">
        <div class="flex items-center justify-between">
            <div class="text-[11px] text-white/80 font-bold">نسبة الحضور</div>
            <span class="w-8 h-8 rounded-lg bg-white/15 grid place-items-center"><x-icon name="target" class="w-4 h-4" /></span>
        </div>
        <div class="mt-3">
            <div class="text-3xl font-black tabular-nums leading-none">{{ $pct !== null ? $pct.'٪' : '—' }}</div>
            <div class="mt-3 h-1.5 rounded-full bg-white/20 overflow-hidden">
                <div class="h-full rounded-full bg-gold-300" style="width: {{ min(100, (float) $pct) }}%"></div>
            </div>
        </div>
    </div>
</div>
