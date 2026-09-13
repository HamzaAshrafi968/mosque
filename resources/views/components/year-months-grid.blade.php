@props([
    'year',
    'months',
    'routeName',
    'yearRouteName' => null,
    'extraParams' => [],
    'hafizCount' => null,
])

@php
    $yearRouteName = $yearRouteName ?? str_replace('.month', '.index', $routeName);
    $currentMonth = now()->format('Y-m');
@endphp

<div class="space-y-5">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="font-black text-pine-950 text-lg">سنة {{ $year }}</h3>
            <p class="text-xs text-gray-500 mt-0.5">اختر شهراً لعرض تفاصيل اختبارات الحفاظ</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route($yearRouteName, array_merge($extraParams, ['year' => $year - 1])) }}"
               class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-bold px-3 py-2 rounded-lg">السنة السابقة ←</a>
            <form method="GET" action="{{ route($yearRouteName) }}" class="flex items-center gap-2">
                @foreach($extraParams as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <input type="number" name="year" min="2000" max="2100" value="{{ $year }}" onchange="this.form.submit()"
                       class="w-24 border border-gray-300 rounded-lg px-3 py-2 text-sm text-center" aria-label="السنة">
            </form>
            <a href="{{ route($yearRouteName, array_merge($extraParams, ['year' => $year + 1])) }}"
               class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-bold px-3 py-2 rounded-lg">→ السنة التالية</a>
        </div>
    </div>

    @if($hafizCount !== null && (int) $hafizCount === 0)
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-10 text-center">
            <span class="inline-flex w-14 h-14 rounded-2xl bg-gold-50 text-gold-500 grid place-items-center mb-3"><x-icon name="hafiz" class="w-7 h-7" /></span>
            <p class="text-gray-500 font-bold">لا يوجد حفاظ مسجلون</p>
            <p class="text-gray-400 text-xs mt-1">أُكّد إتمام الحفظ أولاً لتظهر اختبارات الأشهر</p>
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            @foreach($months as $monthData)
                @php $isCurrent = $monthData['month'] === $currentMonth; @endphp
                <a href="{{ route($routeName, array_merge($extraParams, ['month' => $monthData['month']])) }}"
                   @class([
                       'rounded-2xl border bg-white p-4 shadow-sm hover:shadow-md transition block',
                       'border-gold-400 ring-1 ring-gold-300/60' => $isCurrent,
                       'border-gray-200' => ! $isCurrent,
                   ])>
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-black text-pine-950 text-sm leading-snug">{{ $monthData['label'] }}</div>
                            <div class="text-[11px] text-gray-400 font-bold mt-1">{{ $monthData['hafiz_count'] }} حافظ</div>
                        </div>
                        @if($isCurrent)
                            <span class="text-[10px] font-black bg-gold-100 text-gold-700 rounded-full px-2 py-0.5 shrink-0">الحالي</span>
                        @endif
                    </div>

                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @if($monthData['evaluated'] === 0)
                            <span class="text-[10px] font-bold bg-gray-100 text-gray-500 rounded-full px-2 py-0.5">لم يُفتح</span>
                        @else
                            @if($monthData['passed'] > 0)
                                <span class="text-[10px] font-bold bg-green-100 text-green-800 rounded-full px-2 py-0.5">ناجح {{ $monthData['passed'] }}</span>
                            @endif
                            @if($monthData['failed'] > 0)
                                <span class="text-[10px] font-bold bg-red-100 text-red-800 rounded-full px-2 py-0.5">راسب {{ $monthData['failed'] }}</span>
                            @endif
                            @if($monthData['tested'] > 0)
                                <span class="text-[10px] font-bold bg-sky-100 text-sky-800 rounded-full px-2 py-0.5">مُختبر {{ $monthData['tested'] }}</span>
                            @endif
                            @if($monthData['not_tested'] > 0)
                                <span class="text-[10px] font-bold bg-gray-100 text-gray-600 rounded-full px-2 py-0.5">لم يُختبر {{ $monthData['not_tested'] }}</span>
                            @endif
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
