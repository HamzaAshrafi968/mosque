@props(['href', 'active' => false, 'icon' => null])

@php
    $base = 'group flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-[13.5px] transition-all duration-300 border-s-2';
    $state = $active
        ? 'border-gold-300/90 bg-white/[0.09] text-white font-bold shadow-[inset_0_1px_0_rgba(255,255,255,0.06)]'
        : 'border-transparent text-emerald-50/75 hover:text-white hover:bg-white/[0.06] hover:border-gold-300/25';
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => $base.' '.$state]) }}>
    @if($icon)
        <x-icon :name="$icon" class="w-[18px] h-[18px] shrink-0 transition-transform duration-300 group-hover:scale-110 {{ $active ? 'text-gold-300' : 'text-emerald-200/60 group-hover:text-gold-200' }}" />
    @endif
    <span class="flex-1 min-w-0 flex items-center gap-2">{{ $slot }}</span>
</a>
