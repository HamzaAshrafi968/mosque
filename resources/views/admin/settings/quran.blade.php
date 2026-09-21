@extends('layouts.app')

@section('title', 'إعدادات برنامج القرآن')

@section('content')
<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-2">إعدادات برنامج القرآن</h1>
    <p class="text-sm text-gray-500 mb-6">القواعد التي يعتمد عليها النظام في تحديد النجاح والرسوب — تُطبَّق على الاختبارات الجديدة فقط، ولا تغيّر نتائج الاختبارات السابقة.</p>

    @include('admin.settings._tabs')

    <form method="POST" action="{{ route('admin.settings.quran.update') }}" class="bg-white rounded-2xl shadow p-6 space-y-6">
        @csrf
        @method('PATCH')

        <div>
            <label for="minimum_passing_percentage" class="block text-sm font-bold text-gray-700 mb-2">حد النجاح الموحّد لجميع اختبارات القرآن (%)</label>
            <input type="number" name="minimum_passing_percentage" id="minimum_passing_percentage"
                value="{{ old('minimum_passing_percentage', $minimumPassingPercentage) }}"
                min="{{ \App\Services\QuranSettingsService::MIN_PASSING_PERCENTAGE }}"
                max="{{ \App\Services\QuranSettingsService::MAX_PASSING_PERCENTAGE }}"
                step="0.5"
                class="w-full md:w-56 rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500"
                required>
            @error('minimum_passing_percentage')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
            <p class="text-xs text-gray-400 mt-2 leading-relaxed">
                علامة واحدة تُطبَّق على جميع اختبارات القرآن: الاختبار التراكمي لدفعات الحفظ والاختبار المباشر،
                واختبارات برامج الاستماع (التأهيلي والإجازة والقراءات)، واختبار الشهر للحفاظ.
                <span class="font-bold">النسبة/الدرجة ≥ الحد = نجاح</span>.
            </p>
        </div>

        <div class="rounded-xl bg-gray-50 border border-gray-200 p-4 text-xs text-gray-500 leading-relaxed">
            تُحفظ القيمة المستخدمة مع كل اختبار (لقطة) — فلو غيّرت الحد لاحقاً من {{ $minimumPassingPercentage }}% إلى قيمة أخرى، تبقى نتائج الاختبارات القديمة محسوبة وفق الحد الذي كان سارياً وقتها. واختبار الشهر للحفاظ يُثبَّت على نتيجته وقت التقييم.
        </div>

        <div class="rounded-xl border border-gray-200 p-4">
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="hidden" name="auto_confirm_completion" value="0">
                <input type="checkbox" name="auto_confirm_completion" value="1"
                    class="mt-1 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                    @checked(old('auto_confirm_completion', $autoConfirmCompletion))>
                <span>
                    <span class="block text-sm font-bold text-gray-700">الاعتماد التلقائي للحافظ عند إتمام حفظ القرآن (30 جزءاً)</span>
                    <span class="block text-xs text-gray-500 mt-1 leading-relaxed">
                        عند نجاح الطالب في آخر اختبار تراكمي (الدفعة 15 — الجزآن 29–30) يُعتمد حافظاً فوراً: يظهر في «الحفاظ المؤكدين»، ويُنشأ ملف الحافظ، ويلتحق بالبرنامج التأهيلي تلقائياً.
                        تعطيل المفتاح يُرجع المسار اليدوي: يبقى الطلب في «بانتظار التأكيد» حتى تعتمده الإدارة.
                    </span>
                </span>
            </label>
            @error('auto_confirm_completion')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-5 py-2.5 rounded-lg">حفظ الإعدادات</button>
        </div>
    </form>
</div>
@endsection
