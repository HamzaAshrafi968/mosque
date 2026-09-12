@props([
    'label' => 'الصورة الشخصية',
    'help' => 'JPG أو PNG أو WebP — بحد أقصى 2MB',
    'currentSrc' => null,
    'currentName' => '؟',
    'name' => 'photo',
    'showRemove' => true,
    'inputId' => null,
])

@php
    $uid = $inputId ?? 'photo-'.Str::random(8);
    $hasPhoto = (bool) $currentSrc;
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-4', 'data-photo-field' => '']) }}>
    <div class="relative w-24 h-24 rounded-2xl overflow-hidden shrink-0 border-2 {{ $hasPhoto ? 'border-solid border-pine-200 bg-white' : 'border-dashed border-pine-300 bg-pine-100/40' }} grid place-items-center">
        <img
            data-photo-preview
            src="{{ $currentSrc ?? '' }}"
            alt="{{ $label }}"
            class="absolute inset-0 w-full h-full object-cover {{ $hasPhoto ? '' : 'hidden' }}"
        >
        <div data-photo-empty class="text-center text-pine-500 {{ $hasPhoto ? 'hidden' : '' }}">
            <x-icon name="camera" class="w-7 h-7 mx-auto mb-1 text-pine-400" />
            <span class="text-[10px] font-bold block">لا صورة</span>
        </div>
    </div>

    <div class="space-y-2">
        <div>
            <p class="text-sm font-bold text-pine-950">{{ $label }}</p>
            <p class="text-[11px] text-gray-400 font-medium mt-0.5">{{ $help }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <label for="{{ $uid }}" class="inline-flex items-center gap-1.5 cursor-pointer rounded-xl bg-pine-950 hover:bg-pine-800 text-white text-xs font-bold px-3.5 py-2 transition active:scale-95">
                <x-icon name="camera" class="w-4 h-4" />
                اختيار الصورة
            </label>

            @if ($showRemove)
                <label class="inline-flex items-center gap-1.5 text-xs font-semibold text-red-500 cursor-pointer hover:text-red-700 transition">
                    <input type="checkbox" name="remove_photo" value="1" data-photo-remove class="rounded border-gray-300 text-red-500 focus:ring-red-400">
                    إزالة الصورة الحالية
                </label>
            @endif
        </div>
    </div>

    <input
        id="{{ $uid }}"
        data-photo-input
        type="file"
        name="{{ $name }}"
        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
        class="sr-only"
    >

    @error($name)
        <p class="basis-full text-xs font-semibold text-red-600">{{ $message }}</p>
    @enderror
    @error('remove_photo')
        <p class="basis-full text-xs font-semibold text-red-600">{{ $message }}</p>
    @enderror
</div>
