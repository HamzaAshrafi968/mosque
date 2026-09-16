@extends('layouts.app')

@section('title', 'إعدادات برنامج القرآن')

@section('content')
<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-2">إعدادات برنامج القرآن</h1>
    <p class="text-sm text-gray-500 mb-6">القواعد التي يعتمد عليها النظام في تحديد النجاح والرسوب — تُطبَّق على الاختبارات الجديدة فقط، ولا تغيّر نتائج الاختبارات السابقة.</p>

    @if (session('success'))
        <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm font-bold">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.quran.update') }}" class="bg-white rounded-2xl shadow p-6 space-y-6">
        @csrf
        @method('PATCH')

        <div>
            <label for="minimum_passing_percentage" class="block text-sm font-bold text-gray-700 mb-2">الحد الأدنى للنجاح في اختبار دفعات الحفظ (%)</label>
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
            <p class="text-xs text-gray-400 mt-2">
                درجة اختبار الدفعة تُحسب آلياً من نسبة العناصر الناجحة (مثال: 7 من 8 = 87.5%)، والـ Backend يقارنها بهذا الحد:
                <span class="font-bold">النسبة ≥ الحد = نجاح</span> وفتح الدفعة التالية.
            </p>
        </div>

        <div class="rounded-xl bg-gray-50 border border-gray-200 p-4 text-xs text-gray-500 leading-relaxed">
            تُحفظ القيمة المستخدمة مع كل اختبار (لقطة) — فلو غيّرت الحد لاحقاً من {{ $minimumPassingPercentage }}% إلى قيمة أخرى، تبقى نتائج الاختبارات القديمة محسوبة وفق الحد الذي كان سارياً وقتها.
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-5 py-2.5 rounded-lg">حفظ الإعدادات</button>
        </div>
    </form>
</div>
@endsection
