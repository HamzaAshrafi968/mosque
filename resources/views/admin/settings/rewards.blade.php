@extends('layouts.app')

@section('title', 'إعدادات نقاط المكافآت')

@section('content')
<div class="max-w-5xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-2">إعدادات نقاط المكافآت</h1>
    <p class="text-sm text-gray-500 mb-6">
        لكل دوام نقاطه الخاصة. تُمنح النقاط تلقائياً عند: حفظ صفحات جديدة، إتمام خمسة مراجعة، واجتياز اختبار دفعة الحفظ.
        اترك خانة النقاط فارغة أو صفراً لتعطيل القاعدة لهذا الدوام.
    </p>

    @if (session('success'))
        <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm font-bold">
            {{ session('success') }}
        </div>
    @endif

    @if ($sessions->isEmpty())
        <div class="bg-white rounded-2xl shadow p-10 text-center text-gray-500">
            لا توجد دوامات بعد — أضف الدوامات من صفحة الدوامات أولاً.
        </div>
    @else
        <form method="POST" action="{{ route('admin.settings.rewards.update') }}" class="space-y-6">
            @csrf
            @method('PATCH')

            @foreach ($sessions as $session)
                @php($rules = $rulesBySession[$session->id])
                <div class="bg-white rounded-2xl shadow p-6">
                    <div class="flex items-center gap-3 mb-5">
                        <span class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center text-xl">🏆</span>
                        <div>
                            <h2 class="font-bold text-gray-800">دوام {{ $session->name }}</h2>
                            <p class="text-xs text-gray-400">نقاط المكافآت الخاصة بهذا الدوام</p>
                        </div>
                    </div>

                    <div class="grid md:grid-cols-3 gap-5">
                        <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-4">
                            <div class="font-bold text-sm text-gray-700 mb-3">📖 حفظ صفحات جديدة</div>
                            <div class="flex flex-wrap items-center gap-2 text-sm">
                                <span class="text-gray-500">كل</span>
                                <input type="number" name="rules[{{ $session->id }}][tasmee_pages][pages_count]"
                                    value="{{ old("rules.{$session->id}.tasmee_pages.pages_count", $rules['tasmee_pages']['pages_count']) }}"
                                    min="1" max="604" placeholder="5"
                                    class="w-20 rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                                <span class="text-gray-500">صفحة =</span>
                                <input type="number" name="rules[{{ $session->id }}][tasmee_pages][points]"
                                    value="{{ old("rules.{$session->id}.tasmee_pages.points", $rules['tasmee_pages']['points']) }}"
                                    min="0" placeholder="10"
                                    class="w-20 rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                                <span class="text-gray-500">نقطة</span>
                            </div>
                            @error("rules.{$session->id}.tasmee_pages.pages_count")
                                <p class="text-xs text-red-600 mt-2">{{ $message }}</p>
                            @enderror
                            @error("rules.{$session->id}.tasmee_pages.points")
                                <p class="text-xs text-red-600 mt-2">{{ $message }}</p>
                            @enderror
                            <p class="text-[11px] text-gray-400 mt-3 leading-relaxed">
                                تراكمي مع ترحيل الباقي: من حفظ ٣ صفحات ثم ٢ لاحقاً تكتمل الخمسة وتُمنح نقاطها.
                            </p>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-4">
                            <div class="font-bold text-sm text-gray-700 mb-3">✅ إتمام خمسة مراجعة</div>
                            <div class="flex flex-wrap items-center gap-2 text-sm">
                                <span class="text-gray-500">لكل خمسة =</span>
                                <input type="number" name="rules[{{ $session->id }}][khamsa_review][points]"
                                    value="{{ old("rules.{$session->id}.khamsa_review.points", $rules['khamsa_review']['points']) }}"
                                    min="0" placeholder="2"
                                    class="w-20 rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                                <span class="text-gray-500">نقطة</span>
                            </div>
                            @error("rules.{$session->id}.khamsa_review.points")
                                <p class="text-xs text-red-600 mt-2">{{ $message }}</p>
                            @enderror
                            <p class="text-[11px] text-gray-400 mt-3 leading-relaxed">
                                تُمنح عند إنهاء الخمسة من شاشة «مراجعة 5» أو تلقائياً عند اجتياز اختبار الدفعة.
                            </p>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-4">
                            <div class="font-bold text-sm text-gray-700 mb-3">🎓 اجتياز اختبار الدفعة</div>
                            <div class="flex flex-wrap items-center gap-2 text-sm">
                                <span class="text-gray-500">عند النجاح =</span>
                                <input type="number" name="rules[{{ $session->id }}][test_pass][points]"
                                    value="{{ old("rules.{$session->id}.test_pass.points", $rules['test_pass']['points']) }}"
                                    min="0" placeholder="20"
                                    class="w-20 rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                                <span class="text-gray-500">نقطة</span>
                            </div>
                            @error("rules.{$session->id}.test_pass.points")
                                <p class="text-xs text-red-600 mt-2">{{ $message }}</p>
                            @enderror
                            <p class="text-[11px] text-gray-400 mt-3 leading-relaxed">
                                تُمنح مرة واحدة لكل اختبار ناجح فقط، ولا شيء عند الرسوب.
                            </p>
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="rounded-xl bg-gray-50 border border-gray-200 p-4 text-xs text-gray-500 leading-relaxed">
                تعديل القواعد لا يغيّر النقاط الممنوحة سابقاً — الصفحات المكافأة محفوظة لقطة لكل عملية منح.
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-5 py-2.5 rounded-lg">حفظ القواعد</button>
            </div>
        </form>
    @endif
</div>
@endsection
