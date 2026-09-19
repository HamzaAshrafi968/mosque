@extends('layouts.app')

@section('title', 'أسعار الساعة')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800 mb-2">أسعار الساعة</h1>
        <p class="text-sm text-gray-500">حدّد سعر ساعة كل أستاذ — يُحتسب راتبه تلقائياً من فترات عمله المسجّلة.</p>
    </div>

    @include('admin.settings._tabs')

    <div class="rounded-2xl border border-emerald-100 bg-emerald-50/60 p-4 text-sm text-emerald-900 leading-relaxed">
        <span class="font-bold">كيف يُحتسب الراتب؟</span>
        راتب الشهر = مجموع (دقائق كل فترة عمل × سعر الساعة في تاريخها) ÷ 60.
        مثال: 10 ساعات × 5,000 = 50,000. الأيام بلا سعر تظهر كتنبيه ولا تُحتسب بصفر،
        وتعديل السعر لا يمس الكشوف المغلقة.
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
        <h3 class="font-black text-pine-950 mb-3">إضافة سعر</h3>
        <form method="POST" action="{{ route('admin.settings.hourly-rates.store') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2 items-end">
            @csrf
            <div class="lg:col-span-2">
                <label class="block text-[11px] font-bold text-gray-500 mb-0.5">المعلم</label>
                <select name="teacher_id" required class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm">
                    <option value="">اختر المعلم…</option>
                    @foreach($teachers as $teacher)
                        <option value="{{ $teacher->id }}" @selected((string) old('teacher_id', $selectedTeacherId) === (string) $teacher->id)>
                            {{ $teacher->name }} ({{ $teacher->pay_type->label() }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-500 mb-0.5">سعر الساعة</label>
                <input type="number" step="0.01" min="0.01" name="rate" required value="{{ old('rate') }}" placeholder="5000"
                       class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm" dir="ltr">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-500 mb-0.5">ساري من</label>
                <input type="date" name="effective_from" required value="{{ old('effective_from', now()->toDateString()) }}"
                       class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm" dir="ltr">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-500 mb-0.5">ساري إلى (اختياري)</label>
                <div class="flex gap-2">
                    <input type="date" name="effective_to" value="{{ old('effective_to') }}"
                           class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm" dir="ltr">
                    <button class="shrink-0 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">إضافة</button>
                </div>
            </div>
        </form>
        <p class="text-[11px] text-gray-400 mt-2">عند الحفظ يُحوَّل الأستاذ تلقائياً إلى نوع الأجر «بالساعة» إن لم يكن كذلك.</p>
    </div>

    @if($activeRates->isNotEmpty())
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="font-black text-pine-950">ملخص {{ $monthLabel }} — الأسعار السارية</h3>
                <p class="text-xs text-gray-500 mt-1">يُحتسب حياً من فترات العمل المسجّلة في الشهر الحالي.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <caption class="sr-only">ملخص الشهر الحالي للأسعار السارية</caption>
                    <thead>
                        <tr class="bg-gray-50 text-gray-600">
                            <th scope="col" class="px-4 py-3 text-right">الأستاذ</th>
                            <th scope="col" class="px-4 py-3 text-right">السعر الساري</th>
                            <th scope="col" class="px-4 py-3 text-center">نوع الأجر</th>
                            <th scope="col" class="px-4 py-3 text-right">ساعات الشهر</th>
                            <th scope="col" class="px-4 py-3 text-right">الراتب المتوقع</th>
                            <th scope="col" class="px-4 py-3 text-center">حالة التسعير</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($activeRates as $rate)
                        @php $summary = $summaries[$rate->teacher_id] ?? null; @endphp
                        <tr class="border-t">
                            <td class="px-4 py-3 whitespace-nowrap font-bold text-gray-800">{{ $rate->teacher?->name ?? '—' }}</td>
                            <td class="px-4 py-3 font-black text-emerald-700" dir="ltr">{{ number_format((float) $rate->rate, 2) }}</td>
                            <td class="px-4 py-3 text-center">
                                @if($rate->teacher?->pay_type === \App\Enums\PayType::Hourly)
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">بالساعة</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800">شهري — لن يُحتسب بالساعات</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap" dir="ltr">{{ $summary !== null ? \App\Models\WorkSlot::formatMinutes($summary['total_minutes']) : '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap font-bold text-gray-800" dir="ltr">
                                {{ $summary !== null ? number_format($summary['gross'], 2).' '.$currency : '—' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($summary === null)
                                    <span class="text-xs text-gray-400">—</span>
                                @elseif($summary['missing_rates'] !== [])
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800"
                                          title="{{ implode('، ', $summary['missing_rates']) }}">أيام بلا سعر: {{ count($summary['missing_rates']) }}</span>
                                @elseif($summary['total_minutes'] === 0)
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-gray-100 text-gray-500">لا فترات مسجّلة</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">مكتمل التسعير</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 class="font-black text-pine-950">سجل الأسعار</h3>
        <form method="GET" action="{{ route('admin.settings.hourly-rates.index') }}" class="flex items-center gap-2">
            <input type="text" name="q" value="{{ $search }}" placeholder="بحث باسم المعلم"
                   class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
            <button class="text-xs font-bold text-gray-600 hover:text-gray-800">بحث</button>
        </form>
    </div>

    @if($rates->isEmpty())
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-10 text-center text-gray-400 font-bold">لا توجد أسعار مسجلة</div>
    @else
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <caption class="sr-only">سجل أسعار الساعة</caption>
                    <thead>
                        <tr class="bg-gray-50 text-gray-600">
                            <th scope="col" class="px-4 py-3 text-right">المعلم</th>
                            <th scope="col" class="px-4 py-3 text-right">السعر</th>
                            <th scope="col" class="px-4 py-3 text-right">ساري من</th>
                            <th scope="col" class="px-4 py-3 text-right">ساري إلى</th>
                            <th scope="col" class="px-4 py-3 text-center">الحالة</th>
                            <th scope="col" class="px-4 py-3 text-right">أضافه</th>
                            <th scope="col" class="px-4 py-3 text-center">إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($rates as $rate)
                        <tr class="border-t">
                            <td class="px-4 py-3 whitespace-nowrap font-bold text-gray-800">{{ $rate->teacher?->name ?? '—' }}</td>
                            <td class="px-4 py-3 font-black text-emerald-700" dir="ltr">{{ number_format((float) $rate->rate, 2) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap" dir="ltr">{{ $rate->effective_from->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap" dir="ltr">{{ $rate->effective_to?->format('Y-m-d') ?? 'مفتوح' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $rate->statusBadgeClasses() }}">{{ $rate->statusLabel() }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $rate->creator?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <form method="POST" action="{{ route('admin.settings.hourly-rates.destroy', $rate) }}"
                                      onsubmit="return confirm('حذف سعر الساعة؟ الكشوف المغلقة لا تتأثر.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-bold">حذف</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-gray-100">{{ $rates->links() }}</div>
        </div>
    @endif
</div>
@endsection
