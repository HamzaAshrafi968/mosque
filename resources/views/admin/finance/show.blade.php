@extends('layouts.app')

@section('title', 'السجل المالي')

@section('content')
@php
    $balance = $summary['balance'];
    $personRoute = $personType === \App\Enums\FinancePersonType::Student
        ? route('admin.students.show', $person)
        : route('admin.teachers.show', $person);
@endphp

<div class="mb-4">
    <a href="{{ route('admin.finance.index', ['type' => $personType->value]) }}" class="text-sm text-emerald-700 hover:underline">← العمليات المالية</a>
</div>

<div class="bg-white rounded-xl shadow overflow-hidden mb-6">
    <div class="px-4 py-3 bg-emerald-700 text-white flex items-center justify-between">
        <div class="font-bold">{{ $personType->label() }}: {{ $person->name }}</div>
        <a href="{{ $personRoute }}" class="text-xs bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-lg">الملف الشخصي</a>
    </div>
    <div class="p-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
        <div class="bg-gray-50 rounded-lg p-3">
            <div class="text-xl font-bold text-gray-800" dir="ltr">{{ number_format($summary['charges'], 2) }}</div>
            <div class="text-xs text-gray-600">إجمالي المستحقات</div>
        </div>
        <div class="bg-gray-50 rounded-lg p-3">
            <div class="text-xl font-bold text-emerald-700" dir="ltr">{{ number_format($summary['payments'], 2) }}</div>
            <div class="text-xs text-gray-600">إجمالي المدفوعات</div>
        </div>
        <div class="bg-gray-50 rounded-lg p-3">
            <div class="text-xl font-bold text-sky-700" dir="ltr">{{ number_format($summary['received'], 2) }}</div>
            <div class="text-xs text-gray-600">المبالغ المستلمة من الآخرين</div>
        </div>
        <div @class(['rounded-lg p-3', $balance > 0 ? 'bg-red-50' : ($balance < 0 ? 'bg-emerald-50' : 'bg-gray-50')])>
            <div @class(['text-xl font-bold', $balance > 0 ? 'text-red-700' : ($balance < 0 ? 'text-emerald-700' : 'text-gray-500')]) dir="ltr">
                {{ number_format(abs($balance), 2) }}
            </div>
            <div class="text-xs text-gray-600">{{ $balance > 0 ? 'الرصيد المطلوب عليه' : ($balance < 0 ? 'رصيد له (دائن)' : 'رصيد صفري') }}</div>
        </div>
    </div>
    <div class="px-4 pb-4 text-center text-xs text-gray-400">
        إجمالي التحويلات الصادرة: <span dir="ltr">{{ number_format($summary['sent'], 2) }}</span> • الاسترداد: <span dir="ltr">{{ number_format($summary['refunds'], 2) }}</span> • التسويات: <span dir="ltr">{{ number_format($summary['adjustments'], 2) }}</span>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="px-4 py-3 bg-emerald-700 text-white font-bold text-sm">تسجيل عملية</div>
        <form method="POST" action="{{ route('admin.finance.transactions.store') }}" class="p-4 space-y-3">
            @csrf
            <input type="hidden" name="person_type" value="{{ $personType->value }}">
            <input type="hidden" name="person_id" value="{{ $person->id }}">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">نوع العملية</label>
                <select name="transaction_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="charge">مستحقات / رسوم</option>
                    <option value="payment">دفعة</option>
                    <option value="refund">استرداد</option>
                    <option value="adjustment">تسوية (تصحيح)</option>
                </select>
            </div>
            <div id="direction-group" class="hidden">
                <label class="block text-xs font-medium text-gray-700 mb-1">اتجاه التسوية</label>
                <select name="direction" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="money_out">زيادة المطلوب على الشخص</option>
                    <option value="money_in">تخفيض المطلوب (خصم)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">المبلغ <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" min="0.01" name="amount" required value="{{ old('amount') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" dir="ltr">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">البيان</label>
                <input type="text" name="description" maxlength="1000" value="{{ old('description') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">مرجع (اختياري)</label>
                <input type="text" name="reference" maxlength="255" value="{{ old('reference') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" dir="ltr">
            </div>
            <button type="submit" class="w-full bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">تسجيل</button>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="px-4 py-3 bg-emerald-700 text-white font-bold text-sm">تحويل إلى شخص آخر</div>
        <form method="POST" action="{{ route('admin.finance.transfers.store') }}" class="p-4 space-y-3">
            @csrf
            <input type="hidden" name="from_type" value="{{ $personType->value }}">
            <input type="hidden" name="from_id" value="{{ $person->id }}">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">المستلم</label>
                <select name="to_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">اختر الشخص المستلم...</option>
                    @foreach($otherPeople as $other)
                        <option value="{{ $other->id }}">{{ $other->name }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="to_type" value="{{ $personType->value }}">
                <p class="text-xs text-gray-400 mt-1">يُسجل التحويل على الطرفين بنفس المرجع (صادر للمحوِّل / وارد للمستلم).</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">المبلغ <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" min="0.01" name="amount" required value="{{ old('amount') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" dir="ltr">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">البيان</label>
                <input type="text" name="description" maxlength="1000" value="{{ old('description') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <button type="submit" class="w-full bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold px-4 py-2 rounded-lg">تسجيل التحويل</button>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden p-4 text-sm text-gray-600 space-y-2">
        <h3 class="font-bold text-gray-800">القواعد المحاسبية</h3>
        <ul class="list-disc pr-5 space-y-1 text-xs text-gray-500">
            <li>دفتر العمليات هو مصدر الحقيقة — لا يُخزَّن رصيد يدوي.</li>
            <li>التحويل يُسجل على الطرفين (صادر من المحوِّل ووارد للمستلم) بنفس المرجع.</li>
            <li>العمليات لا تُحذف؛ التصحيح يتم بعملية عكس (إلغاء) تُبقي السجل الأصلي.</li>
            <li>كل عملية تُسجل في سجل العمليات (Audit) مع منفذها وتاريخها.</li>
        </ul>
    </div>
</div>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <div class="px-4 py-3 bg-gray-50 border-b font-bold text-gray-800">سجل العمليات</div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-gray-600">
                    <th class="px-4 py-3 text-right whitespace-nowrap">التاريخ</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">النوع</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">الطرف الآخر</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">البيان</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">المرجع</th>
                    <th class="px-4 py-3 text-center whitespace-nowrap">المبلغ</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">سجله</th>
                    <th class="px-4 py-3 text-center whitespace-nowrap">إجراء</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $tx)
                    @php $related = $tx->transaction_type === \App\Enums\FinancialTransactionType::Transfer ? $tx->relatedPerson : null; @endphp
                    <tr>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $tx->created_at->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">
                            {{ $tx->transaction_type->label() }}
                            @if($tx->reverses_id !== null)
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-700">عكس</span>
                            @endif
                            <span class="text-xs text-gray-400">{{ $tx->direction === \App\Enums\FinancialDirection::MoneyIn ? '(وارد)' : '(صادر)' }}</span>
                        </td>
                        <td class="px-4 py-3 border-t whitespace-nowrap text-gray-600">
                            @if($related)
                                {{ $tx->direction === \App\Enums\FinancialDirection::MoneyOut ? 'إلى: ' : 'من: ' }}{{ $related->name }}
                            @else
                                — (الجامع)
                            @endif
                        </td>
                        <td class="px-4 py-3 border-t text-gray-600">{{ $tx->description ?? '—' }}</td>
                        <td class="px-4 py-3 border-t font-mono text-xs text-gray-400" dir="ltr">{{ $tx->reference ? mb_substr($tx->reference, 0, 8).'…' : '—' }}</td>
                        <td @class(['px-4 py-3 border-t text-center font-bold whitespace-nowrap', $tx->direction === \App\Enums\FinancialDirection::MoneyIn ? 'text-emerald-700' : 'text-red-700']) dir="ltr">
                            {{ $tx->direction === \App\Enums\FinancialDirection::MoneyIn ? '+' : '-' }}{{ number_format((float) $tx->amount, 2) }}
                        </td>
                        <td class="px-4 py-3 border-t text-xs text-gray-500 whitespace-nowrap">{{ $tx->creator?->name ?? '—' }}</td>
                        <td class="px-4 py-3 border-t text-center whitespace-nowrap">
                            @if($tx->reverses_id === null)
                                <form method="POST" action="{{ route('admin.finance.reverse', $tx) }}" onsubmit="return confirm('سيتم عكس العملية (تبقى في السجل كمرجع). متأكد؟')" class="inline">
                                    @csrf
                                    <button type="submit" class="text-amber-600 hover:underline text-xs">عكس</button>
                                </form>
                            @else
                                <span class="text-gray-300 text-xs">مُلغاة</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">لا توجد عمليات مسجلة</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4">{{ $transactions->links() }}</div>
@endsection

@section('scripts')
<script>
    const typeSelect = document.querySelector('select[name="transaction_type"]');
    const directionGroup = document.getElementById('direction-group');
    if (typeSelect && directionGroup) {
        const sync = () => {
            directionGroup.classList.toggle('hidden', typeSelect.value !== 'adjustment');
        };
        typeSelect.addEventListener('change', sync);
        sync();
    }
</script>
@endsection
