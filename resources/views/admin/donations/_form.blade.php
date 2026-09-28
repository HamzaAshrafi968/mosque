@php
    $types = $types ?? \App\Enums\DonationType::cases();
    $currencies = $currencies ?? \App\Enums\DonationCurrency::cases();
    $donation = $donation ?? null;
@endphp

<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">نوع المساهمة <span class="text-red-500">*</span></label>
    <select name="type" required data-donation-type
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        @foreach ($types as $typeOption)
            <option value="{{ $typeOption->value }}" @selected(old('type', $donation?->type?->value) === $typeOption->value)>{{ $typeOption->label() }}</option>
        @endforeach
    </select>
    <p data-donation-example class="text-xs text-emerald-700 font-semibold mt-1.5 leading-relaxed"></p>
    @error('type')
        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
    @enderror
</div>

<div data-donation-custom class="hidden">
    <label class="block text-sm font-medium text-gray-700 mb-1">اكتب نوع المساهمة <span
            class="text-red-500">*</span></label>
    <input type="text" name="custom_type" maxlength="80" value="{{ old('custom_type', $donation?->custom_type) }}"
        placeholder="مثال: توفير مواصلات للطلاب، تجهيز مكتبة، أي مساهمة أخرى…"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
    @error('custom_type')
        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
    @enderror
</div>

<div data-donation-financial
    class="hidden rounded-xl border border-emerald-200 bg-emerald-50/60 p-3.5 grid gap-4 sm:grid-cols-2">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">المبلغ <span class="text-red-500">*</span></label>
        <input type="number" name="amount" min="1" step="0.01"
            value="{{ old('amount', $donation?->amount) }}" data-donation-amount
            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        @error('amount')
            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">العملة <span class="text-red-500">*</span></label>
        <select name="currency" data-donation-currency
            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            <option value="">اختر العملة…</option>
            @foreach ($currencies as $currencyOption)
                <option value="{{ $currencyOption->value }}" @selected(old('currency', $donation?->currency?->value) === $currencyOption->value)>{{ $currencyOption->label() }}
                </option>
            @endforeach
        </select>
        @error('currency')
            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">اسم المتبرع <span
                class="text-red-500">*</span></label>
        <input type="text" name="donor_name" required maxlength="120"
            value="{{ old('donor_name', $donation?->donor_name) }}"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        @error('donor_name')
            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف <span
                class="text-red-500">*</span></label>
        <input type="text" name="donor_phone" required maxlength="30"
            value="{{ old('donor_phone', $donation?->donor_phone) }}" dir="ltr" placeholder="للتواصل مع المتبرع"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none text-right">
        @error('donor_phone')
            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>
</div>

<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ التسليم <span
            class="text-red-500">*</span></label>
    <input type="datetime-local" name="delivery_date" required
        value="{{ old('delivery_date', $donation?->delivery_date?->format('Y-m-d\TH:i')) }}"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
    @error('delivery_date')
        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
    @enderror
</div>

<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">التفاصيل <span class="text-gray-400">(اختياري)</span></label>
    <textarea name="description" rows="3" maxlength="2000"
        placeholder="اكتب تفاصيل ما سيقدمه المتبرع…"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">{{ old('description', $donation?->description) }}</textarea>
    @error('description')
        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
    @enderror
</div>

<label class="inline-flex items-center gap-2 text-sm text-gray-600">
    <input type="checkbox" name="is_anonymous" value="1" @checked(old('is_anonymous', $donation?->is_anonymous))
        class="rounded border-gray-300 text-emerald-700 focus:ring-emerald-500">
    إخفاء اسم المتبرع عن العامة
</label>
