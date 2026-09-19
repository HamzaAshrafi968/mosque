@props([
    'teacher',
    'monthInput',
    'remaining' => 0,
    'gross' => 0,
    'currency' => '',
    'closed' => false,
    'canPay' => true,
    'inline' => false,
    'size' => 'md',
])

@php
    $remaining = round((float) $remaining, 2);
    $gross = round((float) $gross, 2);
    $settled = $gross > 0 && $remaining <= 0.004;
    $noDues = $gross <= 0;
    $sizeClasses = $size === 'sm'
        ? 'px-3 py-1.5 text-xs'
        : 'px-4 py-2 text-sm';
    $amountValue = number_format($remaining, 2, '.', '');
@endphp

<div data-quick-pay class="relative inline-block text-start">
    @if($closed)
        <button type="button" disabled
                class="{{ $sizeClasses }} inline-flex items-center gap-1.5 rounded-lg bg-gray-100 text-gray-400 font-bold cursor-not-allowed"
                title="الشهر مغلق — أعد فتحه من صفحة الكشف">
            <x-icon name="clock" class="w-3.5 h-3.5" />
            الشهر مغلق
        </button>
    @elseif($settled)
        <span class="{{ $sizeClasses }} inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 text-emerald-700 font-bold">
            <x-icon name="check" class="w-3.5 h-3.5" />
            مدفوع
        </span>
    @elseif($noDues)
        <span class="{{ $sizeClasses }} inline-flex items-center gap-1.5 rounded-lg bg-gray-100 text-gray-400 font-bold">لا مستحقات</span>
    @elseif(! $canPay)
        <a href="{{ route('admin.payroll.sheet', ['teacher' => $teacher, 'month' => $monthInput]) }}"
           class="{{ $sizeClasses }} inline-flex items-center gap-1.5 rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 font-bold">
            عرض
        </a>
    @else
        <button type="button" data-quick-pay-toggle aria-expanded="{{ $inline ? 'true' : 'false' }}"
                class="{{ $sizeClasses }} inline-flex items-center gap-1.5 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white font-bold shadow-sm">
            <x-icon name="wallet" class="w-3.5 h-3.5" />
            دفع
        </button>

        <div data-quick-pay-panel @if(! $inline) hidden @endif
             @class([
                 'w-72 max-w-[calc(100vw-2.5rem)] rounded-2xl border border-gray-200 bg-white p-4 shadow-xl space-y-3',
                 'mt-2' => $inline,
                 'absolute end-0 z-30 mt-2' => ! $inline,
             ])>
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-black text-gray-700">دفع لـ {{ $teacher->name }}</span>
                <button type="button" data-quick-pay-close class="text-gray-400 hover:text-gray-600" aria-label="إغلاق">
                    <x-icon name="x" class="w-4 h-4" />
                </button>
            </div>

            <form method="POST" action="{{ route('admin.payroll.pay', $teacher) }}" class="space-y-3">
                @csrf
                <input type="hidden" name="month" value="{{ $monthInput }}">

                <div>
                    <label class="block text-[11px] font-bold text-gray-500 mb-1">المبلغ ({{ $currency }})</label>
                    <input type="number" name="amount" required step="0.01" min="0.01" max="{{ $amountValue }}"
                           value="{{ $amountValue }}" dir="ltr" data-quick-pay-amount
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-bold focus:border-emerald-500 focus:ring-emerald-500">
                    <p class="text-[10px] text-gray-400 mt-1">المتبقي كاملاً: <span dir="ltr">{{ number_format($remaining, 2) }}</span> {{ $currency }}</p>
                </div>

                <div>
                    <span class="block text-[11px] font-bold text-gray-500 mb-1">طريقة الدفع</span>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach(['نقد', 'تحويل بنكي'] as $method)
                            <label class="cursor-pointer">
                                <input type="radio" name="payment_method" value="{{ $method }}" class="peer sr-only" @checked($loop->first)>
                                <span class="inline-flex px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-bold text-gray-600 peer-checked:bg-emerald-700 peer-checked:text-white peer-checked:border-emerald-700 transition-colors">{{ $method }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <button class="w-full bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">
                    تسجيل الدفعة
                </button>
            </form>
        </div>
    @endif
</div>
