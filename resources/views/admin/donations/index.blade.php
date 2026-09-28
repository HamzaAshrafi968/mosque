@extends('layouts.app')

@section('title', 'التبرعات والمساهمات')

@section('content')
@php
    $authorization = app(\App\Services\AuthorizationService::class);
    $can = fn (string $permission) => $authorization->can(auth()->user(), $permission);
    $pendingCount = $statusCounts['pending'] ?? 0;
    $tabs = [
        '' => ['label' => 'الكل', 'count' => $totalCount],
        'pending' => ['label' => 'بانتظار المراجعة', 'count' => $pendingCount],
        'accepted' => ['label' => 'مقبول', 'count' => $statusCounts['accepted'] ?? 0],
        'rejected' => ['label' => 'مرفوض', 'count' => $statusCounts['rejected'] ?? 0],
    ];
    $tone = fn (string $value) => match ($value) {
        'financial' => 'bg-emerald-100 text-emerald-700',
        'in_kind' => 'bg-sky-100 text-sky-700',
        'moral' => 'bg-violet-100 text-violet-700',
        default => 'bg-gold-100 text-gold-700',
    };
    $statusTone = fn (string $value) => match ($value) {
        'pending' => 'bg-amber-100 text-amber-700',
        'accepted' => 'bg-emerald-100 text-emerald-700',
        default => 'bg-red-100 text-red-700',
    };
@endphp

<div class="max-w-6xl mx-auto">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-gray-800">التبرعات والمساهمات</h1>
            <p class="text-sm text-gray-500 font-semibold mt-1">
                ما يصل من الموقع العام يبقى «بانتظار المراجعة» حتى تقبله فيظهر للجميع في صفحة الجامع.
            </p>
        </div>
        @if ($can('donations.create'))
            <a href="{{ route('admin.donations.create') }}"
                class="inline-flex items-center gap-2 bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2.5 rounded-xl transition text-sm">
                <x-icon name="plus" class="w-4 h-4" />
                تسجيل يدوي
            </a>
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-2 mb-5">
        @foreach ($tabs as $value => $tab)
            <a href="{{ route('admin.donations.index', array_filter(['status' => $value, 'type' => $type?->value])) }}"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold border transition {{ ($status?->value ?? '') === $value
                    ? 'bg-pine-900 text-white border-pine-900'
                    : 'bg-white text-gray-600 border-gray-200 hover:border-emerald-400' }}">
                {{ $tab['label'] }}
                <span class="text-xs px-1.5 py-0.5 rounded-full {{ ($status?->value ?? '') === $value ? 'bg-white/20' : 'bg-gray-100' }}">{{ $tab['count'] }}</span>
            </a>
        @endforeach

        <form method="GET" action="{{ route('admin.donations.index') }}" class="ms-auto">
            @if ($status)
                <input type="hidden" name="status" value="{{ $status->value }}">
            @endif
            <select name="type" onchange="this.form.submit()"
                class="border border-gray-200 rounded-xl px-3 py-2 text-sm font-bold text-gray-600 bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                <option value="">كل الأنواع</option>
                @foreach (\App\Enums\DonationType::cases() as $typeOption)
                    <option value="{{ $typeOption->value }}" @selected($type?->value === $typeOption->value)>{{ $typeOption->label() }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="space-y-4">
        @forelse ($donations as $donation)
            <div class="bg-white rounded-2xl shadow overflow-hidden p-4 sm:p-5">
                <div class="flex flex-wrap justify-between items-start gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap mb-1.5">
                            <span class="text-xs px-2 py-1 rounded-full font-black {{ $tone($donation->type->value) }}">{{ $donation->typeLabel() }}</span>
                            <span class="text-xs px-2 py-1 rounded-full font-black {{ $statusTone($donation->status->value) }}">{{ $donation->status->label() }}</span>
                            @if ($donation->source === 'public')
                                <span class="text-xs px-2 py-1 rounded-full border border-gray-200 text-gray-500 font-bold">من الموقع العام</span>
                            @else
                                <span class="text-xs px-2 py-1 rounded-full border border-gray-200 text-gray-500 font-bold">تسجيل يدوي</span>
                            @endif
                            @if ($donation->amountLabel())
                                <span class="text-sm font-black text-pine-950" dir="ltr">{{ $donation->amountLabel() }}</span>
                            @endif
                        </div>
                        <h3 class="font-bold text-gray-800">{{ $donation->displayTitle() }}</h3>
                        @if (filled($donation->description))
                            <p class="text-sm text-gray-600 mt-1.5 leading-relaxed">{{ $donation->description }}</p>
                        @endif
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        @if ($donation->status === \App\Enums\DonationStatus::Pending && $can('donations.approve'))
                            <form method="POST" action="{{ route('admin.donations.accept', $donation) }}">
                                @csrf
                                <button type="submit"
                                    class="inline-flex items-center gap-1.5 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-3 py-2 rounded-lg transition">
                                    <x-icon name="check" class="w-3.5 h-3.5" />
                                    قبول
                                </button>
                            </form>
                            <button type="button" data-reject-toggle data-donation-id="{{ $donation->id }}"
                                class="inline-flex items-center gap-1.5 bg-white border border-red-200 hover:bg-red-50 text-red-700 text-xs font-bold px-3 py-2 rounded-lg transition">
                                <x-icon name="x" class="w-3.5 h-3.5" />
                                رفض
                            </button>
                        @endif
                        @if ($donation->status !== \App\Enums\DonationStatus::Pending && $can('donations.approve'))
                            <form method="POST" action="{{ route('admin.donations.accept', $donation) }}">
                                @csrf
                                <button type="submit" class="text-xs font-bold text-emerald-700 hover:underline">إعادة قبول</button>
                            </form>
                        @endif
                        @if ($can('donations.update'))
                            <a href="{{ route('admin.donations.edit', $donation) }}" class="text-xs font-bold text-gray-500 hover:text-pine-800 hover:underline">تعديل</a>
                        @endif
                        @if ($can('donations.delete'))
                            <form method="POST" action="{{ route('admin.donations.destroy', $donation) }}" onsubmit="return confirm('هل أنت متأكد من حذف هذا التبرع؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-bold text-red-600 hover:underline">حذف</button>
                            </form>
                        @endif
                    </div>
                </div>

                <div data-reject-form data-donation-id="{{ $donation->id }}" class="hidden mt-3 rounded-xl border border-red-200 bg-red-50/60 p-3.5">
                    <form method="POST" action="{{ route('admin.donations.reject', $donation) }}" class="flex flex-wrap items-center gap-2">
                        @csrf
                        <input type="text" name="reject_reason" maxlength="500" placeholder="سبب الرفض (اختياري)…"
                            class="flex-1 min-w-[200px] border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:outline-none">
                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-xs font-bold px-4 py-2 rounded-lg transition">تأكيد الرفض</button>
                        <button type="button" data-reject-cancel class="text-xs font-bold text-gray-500 hover:underline">إلغاء</button>
                    </form>
                </div>

                @if (filled($donation->reject_reason))
                    <p class="mt-2 text-xs font-semibold text-red-600">سبب الرفض: {{ $donation->reject_reason }}</p>
                @endif

                <div class="mt-3 text-xs text-gray-400 font-semibold flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span>{{ $donation->displayDonorName() }}</span>
                    @if (filled($donation->donor_phone))
                        <span dir="ltr">{{ $donation->donor_phone }}</span>
                    @endif
                    @if ($donation->delivery_date)
                        <span>• التسليم: {{ $donation->delivery_date->translatedFormat('j F Y H:i') }}</span>
                    @endif
                    <span>•</span>
                    <span>{{ $donation->created_at->translatedFormat('j F Y H:i') }}</span>
                    @if ($donation->reviewer)
                        <span>• راجعه: {{ $donation->reviewer->name }}</span>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl shadow p-10 text-center">
                <span class="inline-grid place-items-center w-14 h-14 rounded-2xl bg-gold-100 text-gold-600 mx-auto">
                    <x-icon name="gift" class="w-7 h-7" />
                </span>
                <p class="text-gray-500 font-bold mt-4">لا توجد تبرعات في هذا التصنيف</p>
            </div>
        @endforelse
    </div>

    <div class="mt-5">
        {{ $donations->links() }}
    </div>
</div>

<script>
    document.addEventListener('click', function (event) {
        const toggle = event.target.closest('[data-reject-toggle]');
        if (toggle) {
            document.querySelectorAll('[data-reject-form]').forEach((form) => form.classList.add('hidden'));
            const form = document.querySelector('[data-reject-form][data-donation-id="' + toggle.dataset.donationId + '"]');
            if (form) form.classList.remove('hidden');
            return;
        }
        if (event.target.closest('[data-reject-cancel]')) {
            document.querySelectorAll('[data-reject-form]').forEach((form) => form.classList.add('hidden'));
        }
    });
</script>
@endsection
