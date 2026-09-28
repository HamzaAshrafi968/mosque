@php
    $mosqueOptions = \App\Models\Tenant::query()->publiclyVisible()->whereNotNull('code')->orderBy('name')->get();
    $preselectedMosqueId = request()->routeIs('site.mosques.show')
        ? request()->route('mosque')?->id
        : (auth()->check()
            ? auth()->user()->tenant_id ?? config('app.current_tenant_id')
            : null);
    $donationTypes = \App\Enums\DonationType::cases();
    $donationCurrencies = \App\Enums\DonationCurrency::cases();
@endphp

<div id="donation-modal" data-donation-modal @if ($errors->any()) data-open-on-load @endif
    class="fixed inset-0 z-[70] hidden" role="dialog" aria-modal="true" aria-labelledby="donation-modal-title">
    <div data-donation-backdrop class="absolute inset-0 bg-pine-950/60 backdrop-blur-sm"></div>

    <div class="absolute inset-x-0 bottom-0 sm:inset-0 sm:grid sm:place-items-center sm:p-4" data-donation-panel>
        <div
            class="relative w-full max-w-lg bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl max-h-[92vh] overflow-y-auto">
            <div
                class="sticky top-0 bg-white/95 backdrop-blur border-b border-pine-100 px-6 py-4 flex items-center gap-3 rounded-t-3xl">
                <span
                    class="w-10 h-10 rounded-xl bg-gradient-to-br from-gold-300 to-gold-600 text-pine-950 grid place-items-center shrink-0">
                    <x-icon name="gift" class="w-5 h-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <h2 id="donation-modal-title" class="font-black text-pine-950">شاركنا الخير</h2>
                    <p class="text-xs text-gray-500 font-semibold">تبرع مادي أو عيني أو مساهمة معنوية — تظهر بعد موافقة
                        إدارة الجامع</p>
                </div>
                <button type="button" data-donation-close aria-label="إغلاق"
                    class="w-9 h-9 rounded-xl bg-pine-50 hover:bg-pine-100 text-pine-800 grid place-items-center transition shrink-0">
                    <x-icon name="x" class="w-4 h-4" />
                </button>
            </div>

            <form method="POST" action="{{ route('site.donations.store') }}" class="px-6 py-5 space-y-4">
                @csrf

                <div>
                    <label for="donation-mosque" class="block text-sm font-bold text-pine-900 mb-1">الجامع المستفيد
                        <span class="text-red-500">*</span></label>
                    <select id="donation-mosque" name="mosque_id" required data-donation-mosque
                        class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <option value="">اختر الجامع…</option>
                        @foreach ($mosqueOptions as $mosqueOption)
                            <option value="{{ $mosqueOption->id }}" @selected(old('mosque_id') === $mosqueOption->id || $preselectedMosqueId === $mosqueOption->id)>
                                {{ $mosqueOption->name }}</option>
                        @endforeach
                    </select>
                    @error('mosque_id')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="donation-type" class="block text-sm font-bold text-pine-900 mb-1">نوع المساهمة <span
                            class="text-red-500">*</span></label>
                    <select id="donation-type" name="type" required data-donation-type
                        class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        @foreach ($donationTypes as $donationType)
                            <option value="{{ $donationType->value }}" @selected(old('type') === $donationType->value)>
                                {{ $donationType->label() }}</option>
                        @endforeach
                    </select>
                    <p data-donation-example class="text-xs text-emerald-700 font-semibold mt-1.5 leading-relaxed"></p>
                    @error('type')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div data-donation-custom class="hidden">
                    <label for="donation-custom-type" class="block text-sm font-bold text-pine-900 mb-1">اكتب نوع
                        المساهمة <span class="text-red-500">*</span></label>
                    <input type="text" id="donation-custom-type" name="custom_type" maxlength="80"
                        value="{{ old('custom_type') }}"
                        placeholder="مثال: توفير مواصلات للطلاب، تجهيز مكتبة، أي مساهمة أخرى…"
                        class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    @error('custom_type')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div data-donation-financial
                    class="hidden rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="donation-amount" class="block text-sm font-bold text-pine-900 mb-1">المبلغ <span
                                class="text-red-500">*</span></label>
                        <input type="number" id="donation-amount" name="amount" min="1" step="0.01"
                            value="{{ old('amount') }}" data-donation-amount placeholder="مثال: 25 أو 50 أو 100"
                            class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        @error('amount')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="donation-currency" class="block text-sm font-bold text-pine-900 mb-1">العملة <span
                                class="text-red-500">*</span></label>
                        <select id="donation-currency" name="currency" data-donation-currency
                            class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                            <option value="">اختر العملة…</option>
                            @foreach ($donationCurrencies as $donationCurrency)
                                <option value="{{ $donationCurrency->value }}" @selected(old('currency') === $donationCurrency->value)>
                                    {{ $donationCurrency->label() }}</option>
                            @endforeach
                        </select>
                        @error('currency')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="donation-name" class="block text-sm font-bold text-pine-900 mb-1">الاسم والكنية
                            <span class="text-red-500">*</span></label>
                        <input type="text" id="donation-name" name="donor_name" required maxlength="120"
                            value="{{ old('donor_name') }}"
                            class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        @error('donor_name')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="donation-phone" class="block text-sm font-bold text-pine-900 mb-1">رقم الهاتف
                            <span class="text-red-500">*</span></label>
                        <input type="text" id="donation-phone" name="donor_phone" required maxlength="30"
                            value="{{ old('donor_phone') }}" dir="ltr" placeholder="للتواصل معك"
                            class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none text-right">
                        @error('donor_phone')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="donation-delivery-date" class="block text-sm font-bold text-pine-900 mb-1">تاريخ
                        التسليم
                        <span class="text-red-500">*</span></label>
                    <input type="datetime-local" id="donation-delivery-date" name="delivery_date" required
                        value="{{ old('delivery_date') }}"
                        class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    @error('delivery_date')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="donation-description" class="block text-sm font-bold text-pine-900 mb-1">التفاصيل
                        <span class="text-gray-400">(اختياري)</span></label>
                    <textarea id="donation-description" name="description" maxlength="2000" rows="3"
                        placeholder="اكتب تفاصيل ما تريد تقديمه…"
                        class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-600 cursor-pointer">
                    <input type="checkbox" name="is_anonymous" value="1" @checked(old('is_anonymous'))
                        class="rounded border-gray-300 text-emerald-700 focus:ring-emerald-500">
                    إخفاء اسمي عن العامة
                </label>

                <button type="submit"
                    class="w-full bg-gradient-to-l from-pine-800 via-emerald-700 to-emerald-600 hover:from-pine-900 hover:to-emerald-700 text-white font-black px-4 py-3 rounded-xl transition text-sm">
                    إرسال المساهمة
                </button>
            </form>
        </div>
    </div>
</div>
