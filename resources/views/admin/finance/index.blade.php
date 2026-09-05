@extends('layouts.app')

@section('title', 'العمليات المالية')

@section('content')
<div class="mb-4 flex gap-2">
    @foreach(\App\Enums\FinancePersonType::cases() as $type)
        <a href="{{ route('admin.finance.index', ['type' => $type->value]) }}"
           @class([
               'px-4 py-2 rounded-lg text-sm font-bold border',
               'bg-emerald-700 text-white border-emerald-700' => $type->value === request('type', 'student'),
               'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' => $type->value !== request('type', 'student'),
           ])>
            {{ $type->value === 'student' ? 'الطلاب' : 'الأساتذة' }}
        </a>
    @endforeach
</div>

<div class="bg-white rounded-xl shadow overflow-hidden mb-6 p-4">
    <form method="GET" action="{{ route('admin.finance.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        <input type="hidden" name="type" value="{{ $type->value }}">
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">بحث بالاسم</label>
            <input type="text" name="q" value="{{ $q }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <label class="inline-flex items-center gap-2 text-sm text-gray-700 pb-2">
            <input type="checkbox" name="owing" value="1" @checked($owing) class="accent-emerald-600">
            فقط من عليهم مستحقات
        </label>
        <div>
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg w-full">بحث</button>
        </div>
    </form>
</div>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <div class="px-4 py-3 bg-gray-50 border-b text-xs text-gray-600">
        الرصيد مستخرج من سجل العمليات دائماً (غير مخزّن): المطلوب = مستحقات + استرداد + تحويلات صادرة + تسويات − مدفوعات − تحويلات واردة. الموجب يعني أن المبلغ مطلوب من الشخص.
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-gray-600">
                    <th class="px-4 py-3 text-right whitespace-nowrap">الاسم</th>
                    <th class="px-4 py-3 text-center whitespace-nowrap">مستحقات</th>
                    <th class="px-4 py-3 text-center whitespace-nowrap">مدفوعات</th>
                    <th class="px-4 py-3 text-center whitespace-nowrap">استرداد</th>
                    <th class="px-4 py-3 text-center whitespace-nowrap">تحويلات واردة</th>
                    <th class="px-4 py-3 text-center whitespace-nowrap">صادرة</th>
                    <th class="px-4 py-3 text-center whitespace-nowrap">الرصيد المطلوب</th>
                    <th class="px-4 py-3 text-center whitespace-nowrap">إجراء</th>
                </tr>
            </thead>
            <tbody>
                @forelse($people as $row)
                    @php $summary = $row['summary']; @endphp
                    <tr>
                        <td class="px-4 py-3 border-t font-bold whitespace-nowrap">{{ $row['person']->name }}</td>
                        <td class="px-4 py-3 border-t text-center" dir="ltr">{{ number_format($summary['charges'], 2) }}</td>
                        <td class="px-4 py-3 border-t text-center text-emerald-700" dir="ltr">{{ number_format($summary['payments'], 2) }}</td>
                        <td class="px-4 py-3 border-t text-center" dir="ltr">{{ number_format($summary['refunds'], 2) }}</td>
                        <td class="px-4 py-3 border-t text-center" dir="ltr">{{ number_format($summary['received'], 2) }}</td>
                        <td class="px-4 py-3 border-t text-center" dir="ltr">{{ number_format($summary['sent'], 2) }}</td>
                        <td class="px-4 py-3 border-t text-center whitespace-nowrap">
                            <span @class([
                                'px-2 py-1 rounded-full text-xs font-bold',
                                'bg-red-100 text-red-800' => $summary['balance'] > 0,
                                'bg-emerald-100 text-emerald-800' => $summary['balance'] < 0,
                                'bg-gray-100 text-gray-500' => $summary['balance'] == 0,
                            ]) dir="ltr">
                                {{ number_format(abs($summary['balance']), 2) }}
                                {{ $summary['balance'] > 0 ? 'مطلوب' : ($summary['balance'] < 0 ? 'له' : 'صفري') }}
                            </span>
                        </td>
                        <td class="px-4 py-3 border-t text-center whitespace-nowrap">
                            <a href="{{ route('admin.finance.show', ['personType' => $type->value, 'person' => $row['person']]) }}"
                               class="text-emerald-700 hover:underline text-sm">السجل المالي</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">لا توجد نتائج</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4">{{ $people->links() }}</div>
@endsection
