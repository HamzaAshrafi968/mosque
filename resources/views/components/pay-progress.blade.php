@props([
    'paid' => 0,
    'gross' => 0,
    'showLabel' => true,
])

@php
    $paid = (float) $paid;
    $gross = (float) $gross;
    $percent = $gross > 0 ? (int) min(100, round($paid / $gross * 100)) : 0;
    $complete = $gross > 0 && $paid + 0.001 >= $gross;
@endphp

<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    @if($showLabel)
        <div class="flex items-center justify-between text-[10px] font-bold mb-1">
            <span @class(['text-emerald-700' => ! $complete, 'text-gray-400' => $complete])>{{ $percent }}%</span>
            @if($complete)
                <span class="text-emerald-700">مكتمل</span>
            @endif
        </div>
    @endif

    <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden" role="progressbar"
         aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
        <div @class(['h-full rounded-full transition-all', 'bg-emerald-600' => ! $complete, 'bg-emerald-400' => $complete])
             style="width: {{ $percent }}%"></div>
    </div>
</div>
