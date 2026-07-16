@props(['href', 'active' => false])

<a href="{{ $href }}"
   class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm transition-all duration-200
          {{ $active ? 'bg-white/20 font-bold shadow-inner' : 'hover:bg-white/10 text-emerald-50/90' }}">
    {{ $slot }}
</a>
