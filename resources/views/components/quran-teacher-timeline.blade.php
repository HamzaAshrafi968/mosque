@props([
    'items',
    'showActions' => true,
])

@php
    $dateLabel = function ($date): string {
        if (! $date) {
            return '—';
        }

        if ($date->isToday()) {
            return 'اليوم';
        }

        if ($date->isYesterday()) {
            return 'أمس';
        }

        return $date->format('Y-m-d');
    };

    $masteryLabel = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
@endphp

<div {{ $attributes->merge(['class' => 'space-y-2']) }}>
    @forelse ($items as $item)
        @php
            $badgeClass = match ($item->type->value) {
                'recitation_new' => 'bg-emerald-100 text-emerald-800',
                'recitation_revision' => 'bg-sky-100 text-sky-800',
                default => 'bg-amber-100 text-amber-800',
            };
        @endphp

        <div class="rounded-xl border border-gray-200 p-3 hover:border-emerald-300 transition">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $badgeClass }}">{{ $item->type->label() }}</span>
                    @if ($item->batchLabel)
                        <span class="text-[11px] text-gray-400">{{ $item->batchLabel }}</span>
                    @endif
                </div>
                <span class="text-[11px] text-gray-400">{{ $dateLabel($item->occurredAt) }}</span>
            </div>

            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-700">
                @if ($item->pagesLabel)
                    <span class="font-bold">{{ $item->pagesLabel }}</span>
                @endif
                @if ($item->amountLabel)
                    <span class="text-gray-500">{{ $item->amountLabel }}</span>
                @endif
                @if ($item->subtitle)
                    <span class="text-gray-400">{{ $item->subtitle }}</span>
                @endif
                @if ($item->teacherName)
                    <span class="text-[11px] text-gray-400">الأستاذ: {{ $item->teacherName }}</span>
                @endif
            </div>

            @if ($item->isListening())
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    @if ($item->masteryPercentage !== null)
                        <span class="text-xs font-bold text-gray-500">الإتقان:
                            <span class="text-emerald-700">{{ $masteryLabel($item->masteryPercentage) }}%</span>
                        </span>
                    @endif
                    @if ($item->hasErrors())
                        <span class="text-[11px] text-red-600">أخطاء: {{ $item->errorSummary() }}</span>
                    @elseif ($item->masteryPercentage !== null)
                        <span class="text-[11px] text-emerald-600">بدون أخطاء مسجّلة</span>
                    @endif
                    @if ($showActions && $item->showUrl)
                        <a href="{{ $item->showUrl }}" class="text-xs font-bold text-emerald-700 hover:underline">عرض الجلسة</a>
                    @endif
                </div>
            @else
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    @if ($item->result)
                        <span @class([
                            'px-2 py-0.5 rounded-full text-[11px] font-bold',
                            'bg-green-100 text-green-800' => $item->result->value === 'excellent',
                            'bg-emerald-50 text-emerald-700' => $item->result->value === 'very_good',
                            'bg-sky-100 text-sky-800' => $item->result->value === 'good',
                            'bg-yellow-100 text-yellow-800' => $item->result->value === 'needs_review',
                        ])>{{ $item->result->label() }}</span>
                    @endif
                    @if ($item->wordErrorCount)
                        <span class="text-[11px] text-red-600">{{ $item->wordErrorCount }} خطأ محدد</span>
                    @endif
                    @if ($showActions && $item->editUrl)
                        <a href="{{ $item->editUrl }}" class="text-xs font-bold text-emerald-700 hover:underline">تعديل</a>
                    @endif
                </div>
            @endif

            @if ($item->notes)
                <div class="mt-2 rounded-lg bg-gray-50 px-2 py-1 text-xs text-gray-500">{{ \Illuminate\Support\Str::limit($item->notes, 160) }}</div>
            @endif
        </div>
    @empty
        <div class="py-6 text-center text-sm text-gray-400">لا توجد جلسات تسميع أو استماع مسجّلة بعد.</div>
    @endforelse
</div>
