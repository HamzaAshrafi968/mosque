@props(['page', 'ayahs', 'surahStarts'])

@php
    $starts = collect($surahStarts)->keyBy('id');
    $toArabic = static function (int $number): string {
        return strtr((string) $number, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);
    };
@endphp

<div class="rounded-3xl border-4 border-double border-gold-400/70 bg-[#fdfbf3] shadow-inner p-4 sm:p-7" dir="rtl">
    <div class="flex flex-wrap items-center justify-between gap-2 text-[11px] sm:text-xs font-bold text-pine-800/70 mb-4">
        <span>الجزء {{ $toArabic((int) ($ayahs->first()->juz ?? 1)) }}</span>
        <span class="px-3 py-1 rounded-full bg-gold-100 text-pine-900 border border-gold-300/70">
            صفحة {{ $toArabic($page) }} من {{ $toArabic(604) }}
        </span>
        <span>{{ $ayahs->last()?->surah?->name_arabic ?? '—' }}</span>
    </div>

    @if($ayahs->isEmpty())
        <div class="text-center py-10">
            <p class="text-amber-700 font-bold">لا توجد آيات لهذه الصفحة</p>
            <p class="text-amber-600/80 text-xs mt-1">بيانات الصفحات غير مهيأة — شغّل: php artisan quran:pages</p>
        </div>
    @else
        <div class="quran-font text-[1.55rem] sm:text-[1.9rem] leading-[2.7] text-pine-950 text-justify">
            @foreach($ayahs as $ayah)
                @if($ayah->ayah_number === 1 && $starts->has($ayah->surah_id))
                    <div class="my-4 py-2 text-center border-y-2 border-gold-300/70 bg-gold-50/70 rounded-lg not-italic">
                        <div class="text-lg sm:text-xl font-bold text-pine-900">سورة {{ $ayah->surah->name_arabic }}</div>
                    </div>
                @endif
                <span>{{ $ayah->text }}<span class="ayah-separator text-gold-600 mx-1.5">۝{{ $toArabic($ayah->ayah_number) }}</span> </span>
            @endforeach
        </div>
    @endif
</div>
