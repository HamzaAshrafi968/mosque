@props([
    'label',
    'value',
    'icon' => null,
    'tone' => 'default',
    'hint' => null,
    'valueDir' => null,
])

@php
    $tones = [
        'default' => ['icon' => 'bg-gray-100 text-gray-500', 'value' => 'text-gray-800'],
        'success' => ['icon' => 'bg-emerald-50 text-emerald-700', 'value' => 'text-emerald-700'],
        'warning' => ['icon' => 'bg-amber-50 text-amber-600', 'value' => 'text-amber-600'],
        'gold' => ['icon' => 'bg-gold-50 text-gold-600', 'value' => 'text-gold-700'],
        'muted' => ['icon' => 'bg-gray-100 text-gray-400', 'value' => 'text-gray-400'],
    ];

    $tone = $tones[$tone] ?? $tones['default'];
@endphp

<div {{ $attributes->merge(['class' => 'bg-white rounded-2xl shadow-sm border border-gray-200 p-4 flex items-start gap-3']) }}>
    @if($icon)
        <span class="grid place-items-center w-10 h-10 rounded-xl {{ $tone['icon'] }} shrink-0">
            <x-icon :name="$icon" class="w-5 h-5" />
        </span>
    @endif

    <div class="min-w-0 flex-1">
        <div class="text-[11px] font-bold text-gray-500">{{ $label }}</div>
        <div @class(['text-lg font-black mt-0.5 truncate', $tone['value']]) @if($valueDir) dir="{{ $valueDir }}" @endif>{{ $value }}</div>
        @if($hint)
            <div class="text-[11px] text-gray-400 mt-0.5">{{ $hint }}</div>
        @endif
    </div>
</div>
