@props([
    'name' => '',
    'src' => null,
    'size' => 'md',
    'class' => '',
    'fallbackClass' => null,
])

@php
    $sizes = [
        'xs' => 'w-7 h-7 text-[10px]',
        'sm' => 'w-9 h-9 text-xs',
        'md' => 'w-11 h-11 text-sm',
        'lg' => 'w-14 h-14 text-base',
        'xl' => 'w-20 h-20 text-2xl',
        '2xl' => 'w-28 h-28 text-4xl',
    ];
    $sizeClasses = $sizes[$size] ?? $sizes['md'];
    $letter = trim((string) $name) !== '' ? mb_substr(trim($name), 0, 1) : '؟';
    $bg = $fallbackClass ?? 'bg-gradient-to-br from-emerald-500 to-pine-800';
@endphp

<span
    {{ $attributes->merge(['class' => 'inline-flex items-center justify-center shrink-0 select-none overflow-hidden rounded-full '.$sizeClasses.' '.$class]) }}
>
    @if ($src)
        <img src="{{ $src }}" alt="{{ $name }}" loading="lazy" class="w-full h-full object-cover">
    @else
        <span class="w-full h-full grid place-items-center font-black text-white {{ $bg }}">
            {{ $letter }}
        </span>
    @endif
</span>
