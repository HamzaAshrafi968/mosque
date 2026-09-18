@props(['minutes' => 0, 'muted' => false])

@php
    $formatted = \App\Models\WorkSlot::formatMinutes((int) $minutes);
    $zero = (int) $minutes <= 0;
@endphp

<span {{ $attributes->merge(['class' => $muted || $zero ? 'text-gray-300' : '']) }}>{{ $formatted }}</span>
