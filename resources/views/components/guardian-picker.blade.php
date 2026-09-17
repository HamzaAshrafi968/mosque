@props([
    'selected' => collect(),
    'searchUrl',
    'quickStoreUrl' => null,
])

<div data-search-picker
     data-search-url="{{ $searchUrl }}"
     data-empty-label="لا يوجد ولي أمر مطابق"
     @if($quickStoreUrl) data-quick-store-url="{{ $quickStoreUrl }}" @endif
     class="space-y-2">
    <input type="hidden" name="guardian_ids_present" value="1">

    <div data-picker-selected class="flex flex-wrap gap-2">
        @foreach($selected as $guardian)
            <span data-id="{{ $guardian->id }}"
                  class="inline-flex items-center gap-2 bg-emerald-50 text-emerald-900 border border-emerald-200 rounded-full ps-3 pe-1.5 py-1 text-sm font-medium">
                <input type="hidden" name="guardian_ids[]" value="{{ $guardian->id }}">
                <span>{{ $guardian->name }}</span>
                @if($guardian->phone)
                    <span class="text-[11px] text-emerald-700/70" dir="ltr">{{ $guardian->phone }}</span>
                @endif
                <button type="button" data-picker-remove
                        class="w-5 h-5 grid place-items-center rounded-full text-emerald-700 hover:bg-emerald-200/70 hover:text-red-700 transition"
                        aria-label="إزالة">×</button>
            </span>
        @endforeach
    </div>

    <div class="relative">
        <input type="text" data-picker-input autocomplete="off"
               placeholder="اكتب اسم ولي الأمر أو رقم الجوال للبحث..."
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        <div data-picker-results
             class="hidden absolute z-30 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto"></div>
    </div>

    <p class="text-xs text-gray-400">اكتب للبحث ثم اختر ولي الأمر فوراً — يمكنك اختيار أكثر من ولي أمر.</p>

    @if($quickStoreUrl)
        <div>
            <button type="button" data-picker-quick-toggle
                    class="text-xs font-bold text-emerald-700 hover:text-emerald-900 hover:underline">
                + إضافة ولي أمر جديد
            </button>

            <div data-picker-quick-form
                 class="hidden mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2 bg-emerald-50/60 border border-emerald-100 rounded-lg p-3">
                <input type="text" data-picker-quick-name placeholder="اسم ولي الأمر *"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                <input type="text" data-picker-quick-phone placeholder="رقم الجوال (اختياري)" dir="ltr"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                <div class="sm:col-span-2 flex flex-wrap items-center gap-2">
                    <button type="button" data-picker-quick-submit
                            class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-4 py-2 rounded-lg">
                        إضافة واختيار
                    </button>
                    <button type="button" data-picker-quick-cancel
                            class="text-xs text-gray-500 hover:text-gray-700 font-semibold">إلغاء</button>
                    <span data-picker-quick-error class="hidden text-xs font-semibold text-red-600"></span>
                </div>
            </div>
        </div>
    @endif
</div>
