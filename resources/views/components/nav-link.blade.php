@props(['href', 'active' => false, 'icon' => null, 'label' => null, 'badge' => null, 'sub' => false])

@php
    if ($sub) {
        $base = 'group relative flex items-center gap-2.5 px-3 py-2 rounded-lg text-[13px] transition-all duration-300';
        $state = $active
            ? 'nav-link-active bg-gold-400/15 text-gold-100 font-bold'
            : 'text-emerald-50/65 hover:text-white hover:bg-white/[0.05]';
    } else {
        $base = 'group relative flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-[13.5px] transition-all duration-300 border-s-2';
        $state = $active
            ? 'nav-link-active border-gold-300/90 bg-white/[0.09] text-white font-bold shadow-[inset_0_1px_0_rgba(255,255,255,0.06)]'
            : 'border-transparent text-emerald-50/75 hover:text-white hover:bg-white/[0.06] hover:border-gold-300/25';
    }
@endphp

<a href="{{ $href }}" @if ($label) data-label="{{ $label }}" @endif
    {{ $attributes->merge(['class' => $base . ' ' . $state]) }}>
    @if ($icon)
        <x-icon :name="$icon"
            class="w-[18px] h-[18px] shrink-0 transition-transform duration-300 group-hover:scale-110 {{ $active ? 'text-gold-300' : 'text-emerald-200/60 group-hover:text-gold-200' }}" />
    @endif
    @if ($sub)
        <span
            class="w-1.5 h-1.5 rounded-full shrink-0 transition-colors {{ $active ? 'bg-gold-300 shadow-[0_0_0_3px_rgba(221,181,62,0.18)]' : 'bg-emerald-200/35 group-hover:bg-gold-200' }}"></span>
    @endif
    @if ($label)
        <span class="sidebar-label flex-1 min-w-0 truncate">{{ $label }}</span>
    @else
        <span class="flex-1 min-w-0 flex items-center gap-2">{{ $slot }}</span>
    @endif
    @if ($badge)
        <span
            class="nav-badge pulse-dot inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-gradient-to-l from-gold-400 to-gold-600 text-pine-950 text-[10px] font-black">{{ $badge }}</span>
    @endif
</a>
