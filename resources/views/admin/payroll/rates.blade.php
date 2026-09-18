@extends('layouts.app')

@section('title', 'أسعار الساعة')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('admin.payroll.index') }}" class="text-sm text-emerald-700 hover:text-emerald-800">← رواتب المعلمين</a>
            <h2 class="text-2xl font-extrabold text-gray-800 mt-1">أسعار الساعة</h2>
            <p class="text-sm text-gray-500 mt-1">
                سجل تاريخي — كل فترة عمل تُسعَّر بسعر تاريخها، وتعديل السعر لا يمس الكشوف المغلقة.
            </p>
        </div>
        <form method="GET" action="{{ route('admin.payroll.rates.index') }}" class="flex items-center gap-2">
            <input type="text" name="q" value="{{ $search }}" placeholder="بحث باسم المعلم"
                   class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
            <button class="text-xs font-bold text-gray-600 hover:text-gray-800">بحث</button>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
        <h3 class="font-black text-pine-950 mb-3">إضافة سعر</h3>
        <form method="POST" action="{{ route('admin.payroll.rates.store') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2 items-end">
            @csrf
            <div class="lg:col-span-2">
                <label class="block text-[11px] font-bold text-gray-500 mb-0.5">المعلم</label>
                <select name="teacher_id" required class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm">
                    <option value="">اختر المعلم…</option>
                    @foreach($teachers as $teacher)
                        <option value="{{ $teacher->id }}" @selected((string) old('teacher_id') === (string) $teacher->id)>
                            {{ $teacher->name }} ({{ $teacher->pay_type->label() }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-500 mb-0.5">السعر</label>
                <input type="number" step="0.01" min="0.01" name="rate" required value="{{ old('rate') }}"
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
                                <form method="POST" action="{{ route('admin.payroll.rates.destroy', $rate) }}"
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
