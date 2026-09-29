@extends('layouts.app')

@section('title', 'إعدادات نقاط المكافآت')

@section('content')
@php
    $authorization = app(\App\Services\AuthorizationService::class);
    $can = fn (string $permission) => $authorization->can(auth()->user(), $permission);
    $canUpdate = $can('quran_settings.update');
@endphp
<div class="max-w-6xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-2">إعدادات نقاط المكافآت</h1>
    <p class="text-sm text-gray-500 mb-6">
        لكل دوام نقاطه الخاصة. فعّل القاعدة التي تريدها وحدّد قيمتها، واتركها مُطفأة لتعطيلها.
        تعديل القواعد لا يغيّر النقاط الممنوحة سابقاً.
    </p>

    @include('admin.settings._tabs')

    @if ($sessions->isEmpty())
        <div class="bg-white rounded-2xl shadow p-10 text-center text-gray-500">
            لا يوجد دوام بعد — أضف الدوام من صفحة الدوام أولاً.
        </div>
    @else
        <form method="POST" action="{{ route('admin.settings.rewards.update') }}" class="space-y-6">
            @csrf
            @method('PATCH')

            <div class="bg-white rounded-2xl shadow p-6 flex flex-wrap items-center gap-5">
                <span class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-700 grid place-items-center text-2xl">⚙️</span>
                <div class="flex-1 min-w-56">
                    <div class="font-bold text-gray-800">المنح التلقائي للنقاط</div>
                    <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                        المفتاح العام للنظام كله. عند إيقافه تتوقف كل القواعد التلقائية فوراً دون حذفها،
                        وتبقى النقاط الممنوحة سابقاً كما هي.
                    </p>
                </div>
                <label class="toggle-switch" title="تشغيل/إيقاف المنح التلقائي">
                    <input type="hidden" name="automatic_enabled" value="0">
                    <input type="checkbox" name="automatic_enabled" value="1" @checked(old('automatic_enabled', $automaticEnabled))>
                    <span class="toggle-track"></span>
                </label>
                <span class="text-xs font-bold {{ old('automatic_enabled', $automaticEnabled) ? 'text-emerald-700' : 'text-gray-400' }}">
                    {{ old('automatic_enabled', $automaticEnabled) ? 'مفعّل' : 'موقوف' }}
                </span>
            </div>

            @foreach ($sessions as $session)
                @php
                    $rules = $rulesBySession[$session->id];
                    $total = $totals[$session->id] ?? null;
                @endphp
                <div class="bg-white rounded-2xl shadow p-6" data-session-panel="{{ $session->id }}">
                    <div class="flex flex-wrap items-center gap-3 mb-5">
                        <span class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center text-xl">🏆</span>
                        <div class="flex-1 min-w-40">
                            <h2 class="font-bold text-gray-800">دوام {{ $session->display_name }}</h2>
                            <p class="text-xs text-gray-400">
                                @if ($total)
                                    مُنح تلقائياً حتى الآن: <span class="font-bold text-amber-700">{{ $total['points'] }}</span> نقطة
                                    ({{ $total['awards'] }} عملية)
                                @else
                                    لم تُمنح نقاط تلقائية في هذا الدوام بعد
                                @endif
                            </p>
                        </div>
                        @if ($canUpdate)
                            <button type="button" data-copy-session="{{ $session->id }}"
                                class="text-[11px] font-bold rounded-lg border border-gray-200 bg-gray-50 hover:bg-emerald-50 hover:border-emerald-200 hover:text-emerald-800 text-gray-500 px-3 py-1.5 transition">
                                نسخ قواعد هذا الدوام للكل
                            </button>
                        @endif
                    </div>

                    <div class="space-y-3">
                        @foreach ($definitions as $type => $definition)
                            @php
                                $rule = $rules[$type] ?? ['pages_count' => null, 'points' => null];
                                $enabled = old("rules.{$session->id}.{$type}.enabled", $rule['points'] !== null && $rule['points'] > 0);
                            @endphp
                            <div class="rounded-xl border border-gray-200 bg-gray-50/60 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div class="flex items-start gap-3 min-w-0 flex-1">
                                        <label class="toggle-switch mt-0.5" title="تفعيل/تعطيل القاعدة">
                                            <input type="hidden" name="rules[{{ $session->id }}][{{ $type }}][enabled]" value="0">
                                            <input type="checkbox" name="rules[{{ $session->id }}][{{ $type }}][enabled]" value="1" @checked($enabled)>
                                            <span class="toggle-track"></span>
                                        </label>
                                        <div class="min-w-0">
                                            <div class="font-bold text-sm text-gray-700">{{ $definition['title'] }}</div>
                                            <p class="text-[11px] text-gray-400 mt-1 leading-relaxed">{{ $definition['description'] }}</p>
                                            @error("rules.{$session->id}.{$type}.points")
                                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                            @enderror
                                            @error("rules.{$session->id}.{$type}.enabled")
                                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2 text-sm shrink-0">
                                        @if ($definition['has_pages'])
                                            <span class="text-gray-500">كل</span>
                                            <input type="number" name="rules[{{ $session->id }}][{{ $type }}][pages_count]"
                                                value="{{ old("rules.{$session->id}.{$type}.pages_count", $rule['pages_count']) }}"
                                                min="1" max="604" placeholder="{{ $definition['pages_placeholder'] }}"
                                                class="w-20 rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                                            <span class="text-gray-500">صفحة =</span>
                                        @else
                                            <span class="text-gray-500">عند التحقق =</span>
                                        @endif
                                        <input type="number" name="rules[{{ $session->id }}][{{ $type }}][points]"
                                            value="{{ old("rules.{$session->id}.{$type}.points", $rule['points']) }}"
                                            min="0" placeholder="{{ $definition['placeholder'] }}"
                                            class="w-20 rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                                        <span class="text-gray-500">نقطة</span>
                                    </div>
                                </div>
                                @error("rules.{$session->id}.{$type}.pages_count")
                                    <p class="text-xs text-red-600 mt-2">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="rounded-xl bg-gray-50 border border-gray-200 p-4 text-xs text-gray-500 leading-relaxed">
                المنح التلقائي محمي من التكرار لكل مصدر (جلسة/خمسة/اختبار/خطة/دورة)، ولا يمكن حذفه من الأستاذ.
                تغيير القواعد يسري على الأحداث الجديدة فقط.
            </div>

            @if ($canUpdate)
                <div class="flex justify-end">
                    <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-6 py-2.5 rounded-lg">
                        حفظ إعدادات النقاط
                    </button>
                </div>
            @else
                <p class="text-xs text-gray-400">لا تملك صلاحية تعديل إعدادات النقاط.</p>
            @endif
        </form>
    @endif
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-copy-session]').forEach(function (button) {
            button.addEventListener('click', function () {
                const sourceId = button.dataset.copySession;
                const source = document.querySelector('[data-session-panel="' + sourceId + '"]');

                if (! source) {
                    return;
                }

                document.querySelectorAll('[data-session-panel]').forEach(function (target) {
                    if (target === source) {
                        return;
                    }

                    const targetId = target.dataset.sessionPanel;

                    source.querySelectorAll('input[name]').forEach(function (input) {
                        const suffix = input.name.replace('rules[' + sourceId + ']', '');
                        const twin = target.querySelector('input[name="rules[' + targetId + ']' + suffix + '"]');

                        if (! twin) {
                            return;
                        }

                        if (input.type === 'checkbox') {
                            twin.checked = input.checked;
                        } else {
                            twin.value = input.value;
                        }
                    });
                });

                const original = button.textContent;
                button.textContent = 'تم النسخ لكل الدوام ✓';
                button.classList.add('border-emerald-300', 'bg-emerald-50', 'text-emerald-800');

                setTimeout(function () {
                    button.textContent = original;
                    button.classList.remove('border-emerald-300', 'bg-emerald-50', 'text-emerald-800');
                }, 1800);
            });
        });
    });
</script>
@endpush
