@extends('layouts.app')

@section('title', 'المالية')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <h1 class="text-2xl font-bold text-gray-800">الدفتر المالي للشيخ</h1>
    <div class="flex gap-2 flex-wrap">
        <a href="{{ route('teacher.finance.receive') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">➕ تسجيل قبض</a>
        <a href="{{ route('teacher.finance.transfer') }}" class="bg-indigo-700 hover:bg-indigo-800 text-white text-sm font-bold px-4 py-2 rounded-lg">⇄ تحويل بين أشخاص</a>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-2xl shadow p-5 text-center">
        <div class="text-sm text-gray-500 mb-1">إجمالي المقبوضات</div>
        <div class="text-3xl font-bold text-emerald-600">{{ number_format($received, 2) }}</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-5 text-center">
        <div class="text-sm text-gray-500 mb-1">المسلّم والمدفوع (منها إيرادات الجامع)</div>
        <div class="text-3xl font-bold text-red-500">{{ number_format($handed, 2) }}</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-5 text-center {{ $remaining >= 0 ? 'bg-emerald-700' : 'bg-amber-600' }}">
        <div class="text-sm text-emerald-100 mb-1">المتبقي بيدك</div>
        <div class="text-3xl font-bold text-white">{{ number_format($remaining, 2) }}</div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-2xl shadow p-5">
        <h2 class="font-bold text-gray-800 mb-3">تسليم إيرادات للجامع</h2>
        <p class="text-xs text-gray-400 mb-3">سجّل المبلغ الذي سلمته للجامع فيخفض المتبقي بيدك تلقائياً.</p>
        <form method="POST" action="{{ route('teacher.finance.adjust') }}" class="space-y-3">
            @csrf
            <input type="hidden" name="direction" value="money_out">
            <input type="number" step="0.01" min="0.01" name="amount" required placeholder="المبلغ"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            <input type="text" name="description" required placeholder="الوصف (مثال: تسليم إيرادات الجامع)"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white text-sm font-bold px-4 py-2 rounded-lg">تسجيل التسليم</button>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow p-5 lg:col-span-2">
        <h2 class="font-bold text-gray-800 mb-3">تسوية تصحيحية</h2>
        <p class="text-xs text-gray-400 mb-3">تُستخدم لتسجيل خطأ في الدفتر (إضافة أو خصم) دون حذف السجل الأصلي.</p>
        <form method="POST" action="{{ route('teacher.finance.adjust') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
            @csrf
            <div>
                <label class="block text-xs text-gray-500 mb-1">الاتجاه</label>
                <select name="direction" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    <option value="money_in">زيادة (نقص في الدفتر)</option>
                    <option value="money_out">خصم (زيادة في الدفتر)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">المبلغ</label>
                <input type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">الوصف</label>
                <input type="text" name="description" required placeholder="سبب التسوية"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>
            <div class="sm:col-span-3">
                <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold px-4 py-2 rounded-lg">تسجيل التسوية</button>
            </div>
        </form>
    </div>
</div>

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <h2 class="text-lg font-bold text-gray-800 p-4 border-b">آخر العمليات</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-gray-500 text-right">
                    <th class="px-4 py-3 font-medium">التاريخ</th>
                    <th class="px-4 py-3 font-medium">النوع</th>
                    <th class="px-4 py-3 font-medium">الطرف الآخر</th>
                    <th class="px-4 py-3 font-medium">الوصف</th>
                    <th class="px-4 py-3 font-medium">المبلغ</th>
                    <th class="px-4 py-3 font-medium">سجّلها</th>
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @php
                    $typeLabel = ['payment' => 'قبض', 'refund' => 'دفع/تسليم', 'transfer' => 'تحويل', 'adjustment' => 'تسوية', 'charge' => 'مطالبة'];
                @endphp
                @forelse($transactions as $tx)
                    <tr class="border-t border-gray-100">
                        <td class="px-4 py-3 text-gray-500">{{ $tx->created_at->format('Y-m-d') }}</td>
                        <td class="px-4 py-3">
                            @if($tx->reverses_id)
                                <span class="text-xs px-2 py-1 rounded-lg bg-red-100 text-red-700 font-bold">عكس عملية</span>
                            @else
                                <span class="text-xs px-2 py-1 rounded-lg {{ $tx->direction->value === 'money_in' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }} font-bold">
                                    {{ $typeLabel[$tx->transaction_type->value] ?? $tx->transaction_type->value }} — {{ $tx->direction->value === 'money_in' ? 'وارد' : 'صادر' }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $tx->relatedPerson?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 max-w-xs truncate" title="{{ $tx->description }}">{{ $tx->description ?? '—' }}</td>
                        <td class="px-4 py-3 font-bold {{ $tx->direction->value === 'money_in' ? 'text-emerald-600' : 'text-red-500' }}">
                            {{ $tx->direction->value === 'money_in' ? '+' : '−' }}{{ number_format((float) $tx->amount, 2) }}
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $tx->creator?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if(! $tx->reverses_id)
                                <form method="POST" action="{{ route('teacher.finance.reverse', $tx) }}" onsubmit="return confirm('سيتم عكس هذه العملية مع حفظ السجل الأصلي، هل أنت متأكد؟')">
                                    @csrf
                                    <button type="submit" class="text-xs text-red-500 hover:underline">عكس</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-400">لا توجد عمليات مالية بعد</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4">{{ $transactions->links() }}</div>
</div>
@endsection
