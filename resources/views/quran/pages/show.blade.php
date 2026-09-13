@extends('layouts.app')

@section('title', 'صفحة المصحف ' . $page)

@section('content')
<div class="max-w-4xl mx-auto space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-2xl font-extrabold text-gray-800">المصحف الشريف — الصفحة {{ $page }}</h2>
        <div class="flex items-center gap-2">
            @if($page > 1)
                <a href="{{ route('quran.pages.show', $page - 1) }}" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-bold px-4 py-2 rounded-lg">→ الصفحة السابقة</a>
            @endif
            @if($page < $maxPage)
                <a href="{{ route('quran.pages.show', $page + 1) }}" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-bold px-4 py-2 rounded-lg">الصفحة التالية ←</a>
            @endif
        </div>
    </div>

    @if(! $pagesSeeded)
        <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl p-6 text-center">
            <p class="font-bold">بيانات الصفحات غير مهيأة</p>
            <p class="text-sm mt-1">شغّل الأمر التالي لتعيين الصفحات والأجزاء: <code class="font-mono bg-white px-2 py-0.5 rounded">php artisan quran:pages</code></p>
        </div>
    @else
        <x-quran-page :page="$page" :ayahs="$ayahs" :surah-starts="$surahStarts" />
    @endif

    <div class="flex items-center justify-center gap-3 text-sm">
        <form method="GET" action="{{ route('quran.pages.show', $page) }}" class="flex items-center gap-2" onsubmit="const p = this.querySelector('input[name=page]'); window.location.href = '{{ url('quran/pages') }}/' + p.value; return false;">
            <label class="text-gray-500 font-bold">انتقل لصفحة:</label>
            <input type="number" name="page" min="1" max="{{ $maxPage }}" value="{{ $page }}" class="w-24 border border-gray-300 rounded-lg px-2 py-1.5 text-center">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-3 py-1.5 rounded-lg">اذهب</button>
        </form>
    </div>
</div>
@endsection
