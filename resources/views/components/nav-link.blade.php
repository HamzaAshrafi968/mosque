@props(['href', 'active' => false])

<a href="{{ $href }}"
   class="block px-3 py-2 rounded-lg text-sm {{ $active ? 'bg-emerald-700 font-bold' : 'hover:bg-emerald-800' }}">
    {{ $slot }}
</a>
