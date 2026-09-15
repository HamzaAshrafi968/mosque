@extends('layouts.app')

@section('title', 'مراجعة 5')

@section('content')
<h1 class="text-2xl font-bold text-gray-800 mb-2">مراجعة 5 (الخمسات)</h1>
<p class="text-sm text-gray-500 mb-6">مراجعاتك المخصصة مع الأستاذ — كل خمسة ٥ صفحات من جزء حفظته.</p>

<div class="space-y-4">
    @forelse($reviews as $review)
        @php $progress = $review->progress(); @endphp
        <div class="bg-white rounded-2xl shadow p-5">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <div>
                    <h3 class="font-bold text-gray-800">الأستاذ: {{ $review->teacher?->name ?? '—' }}</h3>
                    <div class="text-xs text-gray-400 mt-1">
                        الدوام: {{ $review->studySession?->name ?? '—' }}
                        • التخصيص: {{ $review->assigned_at?->format('Y-m-d') }}
                        @if($review->due_date)
                            • الاستحقاق: {{ $review->due_date->format('Y-m-d') }}
                        @endif
                    </div>
                </div>
                <span @class([
                    'px-3 py-1 rounded-full text-xs font-bold',
                    'bg-amber-100 text-amber-800' => $review->status->value === 'pending',
                    'bg-emerald-100 text-emerald-800' => $review->status->value === 'completed',
                    'bg-gray-200 text-gray-600' => $review->status->value === 'cancelled',
                ])>{{ $review->status->label() }}</span>
            </div>

            <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                <span>التقدم: {{ $progress['completed'] }} من {{ $progress['total'] }} خمسة</span>
                <span>{{ $review->pagesCount() }} صفحة</span>
            </div>
            <div class="h-2 rounded-full bg-gray-100 overflow-hidden mb-4">
                <div class="h-full bg-emerald-600" style="width: {{ $progress['percentage'] }}%"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                @foreach($review->items as $item)
                    <div @class([
                        'flex items-center gap-3 rounded-xl border px-3 py-2',
                        'border-emerald-200 bg-emerald-50/50' => $item->isCompleted(),
                        'border-gray-200' => ! $item->isCompleted(),
                    ])>
                        <span @class([
                            'w-6 h-6 grid place-items-center rounded-full text-xs font-bold shrink-0',
                            'bg-emerald-600 text-white' => $item->isCompleted(),
                            'bg-amber-100 text-amber-800' => ! $item->isCompleted(),
                        ])>{{ $item->isCompleted() ? '✓' : '…' }}</span>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-bold text-gray-800">الجزء {{ $item->juz }} — الخمسة {{ $item->khamsa }}</div>
                            <div class="text-[11px] text-gray-400">صفحات {{ $item->from_page }}–{{ $item->to_page }}</div>
                        </div>
                        <div class="text-xs text-left">
                            @if($item->isCompleted())
                                <span class="text-emerald-700 font-bold">تمت</span>
                                @if($item->result)
                                    <div class="text-gray-400">{{ $item->result->label() }}</div>
                                @endif
                            @else
                                <span class="text-amber-700 font-bold">قيد المراجعة</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if($review->notes)
                <div class="mt-3 text-xs text-gray-500 bg-gray-50 rounded-lg px-3 py-2">ملاحظات: {{ $review->notes }}</div>
            @endif
        </div>
    @empty
        <div class="bg-white rounded-2xl shadow p-8 text-center text-gray-400">لا توجد مراجعات مخصصة لك حالياً</div>
    @endforelse
</div>
@endsection
