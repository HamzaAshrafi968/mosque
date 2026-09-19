@props([
    'monthInput',
    'routeName',
    'query' => [],
])

@php
    $current = \Carbon\CarbonImmutable::createFromFormat('Y-m', $monthInput) ?: \Carbon\CarbonImmutable::now();
    $prev = $current->subMonth()->format('Y-m');
    $next = $current->addMonth()->format('Y-m');
    $label = \App\Support\QuranProgramSettings::monthLabel($monthInput);
    $base = array_filter($query, fn ($value) => $value !== null && $value !== '');
@endphp

<div {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 bg-white border border-gray-200 rounded-2xl p-1.5 shadow-sm']) }}>
    <a href="{{ route($routeName, array_merge($base, ['month' => $prev])) }}"
       class="grid place-items-center w-9 h-9 rounded-xl text-gray-500 hover:bg-gray-100 hover:text-emerald-700 transition-colors"
       aria-label="الشهر السابق" title="الشهر السابق">
        <x-icon name="chevrons-right" class="w-4 h-4" />
    </a>

    <form method="GET" action="{{ route($routeName, $base) }}" class="flex items-center">
        @foreach($base as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
        <div class="text-center px-2 min-w-[9.5rem]">
            <div class="text-sm font-black text-gray-800">{{ $label }}</div>
            <input type="month" name="month" value="{{ $monthInput }}" dir="ltr"
                   onchange="this.form.submit()"
                   class="mt-0.5 w-full text-[10px] text-gray-400 bg-transparent border-0 p-0 focus:ring-0 cursor-pointer"
                   aria-label="اختيار الشهر">
        </div>
    </form>

    <a href="{{ route($routeName, array_merge($base, ['month' => $next])) }}"
       class="grid place-items-center w-9 h-9 rounded-xl text-gray-500 hover:bg-gray-100 hover:text-emerald-700 transition-colors"
       aria-label="الشهر التالي" title="الشهر التالي">
        <x-icon name="chevrons-left" class="w-4 h-4" />
    </a>
</div>
