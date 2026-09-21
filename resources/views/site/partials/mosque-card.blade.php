@php
    /** @var \App\Models\Tenant $mosque */
    $logoUrl = $mosque->logoUrl();
@endphp

<a href="{{ route('site.mosques.show', $mosque) }}"
    class="card-hover group bg-white border border-pine-100 rounded-3xl p-5 flex flex-col gap-4">
    <div class="flex items-center gap-3">
        <span class="w-12 h-12 rounded-2xl bg-pine-50 text-pine-700 grid place-items-center overflow-hidden shrink-0">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="شعار {{ $mosque->name }}" class="w-full h-full object-cover" loading="lazy">
            @else
                <x-icon name="mosque" class="w-6 h-6" />
            @endif
        </span>
        <div class="min-w-0">
            <h3 class="font-black text-lg text-pine-950 truncate group-hover:text-emerald-700 transition">
                {{ $mosque->name }}
            </h3>
            @if (filled($mosque->address))
                <p class="text-xs text-gray-500 font-semibold truncate">{{ $mosque->address }}</p>
            @endif
        </div>
    </div>

    @if (filled($mosque->description))
        <p class="text-sm text-gray-600 font-medium leading-relaxed line-clamp-3">{{ $mosque->description }}</p>
    @endif

    <div class="mt-auto flex items-center gap-3 text-xs font-bold text-pine-700">
        @if (filled($mosque->phone))
            <span class="inline-flex items-center gap-1.5" dir="ltr">
                <x-icon name="profile" class="w-3.5 h-3.5" />
                {{ $mosque->phone }}
            </span>
        @endif
        <span class="ms-auto inline-flex items-center gap-1 text-emerald-700">
            التفاصيل
            <x-icon name="chevrons-left" class="w-4 h-4" />
        </span>
    </div>
</a>
