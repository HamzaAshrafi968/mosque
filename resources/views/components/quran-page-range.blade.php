@props(['from' => null, 'to' => null])

<div class="md:col-span-2" data-page-range data-preview-url="{{ route('quran.pages.preview', ['page' => '__PAGE__']) }}">
    <label class="block text-sm font-bold text-gray-700 mb-1">نطاق الصفحات (اختياري)</label>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 items-end">
        <div>
            <label class="block text-xs font-bold text-gray-500 mb-1">من صفحة</label>
            <input type="number" name="from_page" min="1" max="604" value="{{ old('from_page', $from) }}" data-page-from class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 mb-1">إلى صفحة</label>
            <input type="number" name="to_page" min="1" max="604" value="{{ old('to_page', $to) }}" data-page-to class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <div class="col-span-2">
            <button type="button" data-pages-preview class="w-full bg-pine-800 hover:bg-pine-900 text-white text-sm font-bold px-4 py-2 rounded-lg">
                معاينة الصفحات
            </button>
        </div>
    </div>
    <p class="text-xs text-gray-400 mt-1">عند تحديد النطاق يُحسب المقدار تلقائياً (عدد الصفحات) ويمكن تعديله يدوياً.</p>
</div>

<div data-pages-modal class="hidden fixed inset-0 z-[60]">
    <div class="absolute inset-0 bg-pine-950/70 backdrop-blur-sm" data-pages-close></div>
    <div class="relative mx-auto my-6 w-[min(96vw,56rem)] max-h-[90vh] flex flex-col bg-white rounded-3xl shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
            <h3 class="font-black text-pine-950">معاينة صفحات المصحف</h3>
            <button type="button" data-pages-close class="p-2 rounded-xl hover:bg-gray-100" aria-label="إغلاق">
                <x-icon name="x" class="w-5 h-5" />
            </button>
        </div>
        <div data-pages-body class="p-4 overflow-y-auto bg-[#f4f6f4]"></div>
    </div>
</div>

@once
@push('scripts')
<script>
(function () {
    document.querySelectorAll('[data-page-range]').forEach(function (range) {
        const form = range.closest('form') || document;
        const from = range.querySelector('[data-page-from]');
        const to = range.querySelector('[data-page-to]');
        const amount = form.querySelector('[data-amount-input]');
        const modal = document.querySelector('[data-pages-modal]');
        const body = modal ? modal.querySelector('[data-pages-body]') : null;
        const template = range.dataset.previewUrl;

        function syncAmount() {
            if (!amount) return;
            const f = parseInt(from.value, 10);
            const t = parseInt(to.value, 10);
            if (f >= 1 && t >= f) amount.value = t - f + 1;
        }
        from.addEventListener('input', syncAmount);
        to.addEventListener('input', syncAmount);

        function closeModal() { if (modal) modal.classList.add('hidden'); }

        range.querySelector('[data-pages-preview]').addEventListener('click', async function () {
            const f = parseInt(from.value, 10);
            const t = parseInt(to.value, 10);
            if (!(f >= 1 && t >= f)) { alert('حدد نطاق صفحات صحيحاً أولاً'); return; }
            modal.classList.remove('hidden');
            body.innerHTML = '<p class="text-center text-gray-500 py-10 font-bold">جارٍ التحميل…</p>';
            try {
                const url = template.replace('__PAGE__', f) + '?to=' + t;
                const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                body.innerHTML = res.ok ? await res.text() : '<p class="text-center text-red-600 py-10 font-bold">تعذّر تحميل المعاينة</p>';
            } catch (e) {
                body.innerHTML = '<p class="text-center text-red-600 py-10 font-bold">تعذّر تحميل المعاينة</p>';
            }
        });

        if (modal) {
            modal.querySelectorAll('[data-pages-close]').forEach(el => el.addEventListener('click', closeModal));
            document.addEventListener('keydown', e => { if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal(); });
        }
    });
})();
</script>
@endpush
@endonce
