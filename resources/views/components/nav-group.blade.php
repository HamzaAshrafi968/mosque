@props(['icon' => null, 'label' => '', 'active' => false, 'open' => false])

@php
    $isOpen = $open || $active;
    $base = 'group relative w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-[13.5px] transition-all duration-300 border-s-2';
    $state = $active
        ? 'nav-group-active border-gold-300/90 bg-white/[0.09] text-white font-bold shadow-[inset_0_1px_0_rgba(255,255,255,0.06)]'
        : 'border-transparent text-emerald-50/75 hover:text-white hover:bg-white/[0.06] hover:border-gold-300/25';
@endphp

<div class="nav-group" data-nav-group @if ($isOpen) data-open @endif>
    <button type="button" data-nav-group-toggle data-label="{{ $label }}"
        aria-expanded="{{ $isOpen ? 'true' : 'false' }}" {{ $attributes->merge(['class' => $base . ' ' . $state]) }}>
        @if ($icon)
            <x-icon :name="$icon"
                class="w-[18px] h-[18px] shrink-0 transition-transform duration-300 group-hover:scale-110 {{ $active ? 'text-gold-300' : 'text-emerald-200/60 group-hover:text-gold-200' }}" />
        @endif
        <span class="sidebar-label flex-1 min-w-0 text-start truncate">{{ $label }}</span>
        <x-icon name="chevron"
            class="sidebar-chevron w-4 h-4 shrink-0 transition-transform duration-300 {{ $active ? 'text-gold-300' : 'text-emerald-200/50' }}" />
    </button>

    <div class="nav-group-panel">
        <div>
            <div class="mt-1 mb-0.5 ms-5 ps-3 border-s border-white/10 space-y-0.5">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
