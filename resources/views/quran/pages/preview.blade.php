@if(! $pagesSeeded)
    <div class="p-6 text-center text-amber-700 bg-amber-50 rounded-2xl">
        <p class="font-bold">بيانات الصفحات غير مهيأة</p>
        <p class="text-xs mt-1">شغّل: php artisan quran:pages</p>
    </div>
@else
    <div class="space-y-6">
        @foreach($pages as $pageData)
            <x-quran-page
                :page="$pageData['page']"
                :ayahs="$pageData['ayahs']"
                :surah-starts="$pageData['surahStarts']" />
        @endforeach
    </div>
@endif
