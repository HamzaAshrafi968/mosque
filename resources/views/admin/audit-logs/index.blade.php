@extends('layouts.app')

@section('title', 'سجل العمليات')

@php
    $toneBadge = [
        'emerald' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'teal' => 'bg-teal-50 text-teal-700 border-teal-200',
        'sky' => 'bg-sky-50 text-sky-700 border-sky-200',
        'violet' => 'bg-violet-50 text-violet-700 border-violet-200',
        'amber' => 'bg-amber-50 text-amber-700 border-amber-200',
        'rose' => 'bg-rose-50 text-rose-700 border-rose-200',
        'gold' => 'bg-gold-50 text-gold-700 border-gold-200',
        'gray' => 'bg-gray-100 text-gray-600 border-gray-200',
    ];
    $hasFilters = collect($filters)->contains(fn ($value) => $value !== '');
@endphp

@section('content')
<div class="space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-black text-gray-800">سجل العمليات (تدقيق)</h1>
            <p class="text-xs text-gray-500 mt-0.5">سجل للقراءة فقط — كل إجراء يُسجَّل مع منفّذه وتاريخه.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-3 py-1.5 text-center">
                <div class="text-[10px] font-bold text-gray-400">اليوم</div>
                <div class="text-base font-black text-gray-800">{{ $stats['today'] }}</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-3 py-1.5 text-center">
                <div class="text-[10px] font-bold text-gray-400">آخر ٧ أيام</div>
                <div class="text-base font-black text-gray-800">{{ $stats['week'] }}</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-3 py-1.5 text-center">
                <div class="text-[10px] font-bold text-gray-400">مستخدمون نشطون</div>
                <div class="text-base font-black text-gray-800">{{ $stats['users'] }}</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-3 py-1.5 text-center">
                <div class="text-[10px] font-bold text-gray-400">آخر عملية</div>
                <div class="text-xs font-black text-gray-800">{{ $stats['latest_label'] }}</div>
                @if($stats['latest_hint'])
                    <div class="text-[10px] text-gray-400">{{ $stats['latest_hint'] }}</div>
                @endif
            </div>
        </div>
    </div>

    <form method="GET" class="bg-white rounded-2xl shadow p-3 flex flex-wrap gap-2 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-[11px] font-bold text-gray-500 mb-1">بحث</label>
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="اسم المستخدم أو الإجراء أو الكيان"
                   class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-[11px] font-bold text-gray-500 mb-1">المجموعة</label>
            <select name="group" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
                <option value="">كل المجموعات</option>
                @foreach($groups as $key => $meta)
                    <option value="{{ $key }}" @selected($filters['group'] === $key)>{{ $meta['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-bold text-gray-500 mb-1">المستخدم</label>
            <select name="user_id" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm max-w-[180px]">
                <option value="">كل المستخدمين</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" @selected($filters['user_id'] === $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-bold text-gray-500 mb-1">الكيان</label>
            <select name="entity_type" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm max-w-[180px]">
                <option value="">كل الكيانات</option>
                @foreach($entityTypes as $type)
                    <option value="{{ $type }}" @selected($filters['entity_type'] === $type)>
                        {{ \App\Support\AuditActionCatalog::entityLabel($type) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-bold text-gray-500 mb-1">من تاريخ</label>
            <input type="date" name="from" value="{{ $filters['from'] }}" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
        </div>
        <div>
            <label class="block text-[11px] font-bold text-gray-500 mb-1">إلى تاريخ</label>
            <input type="date" name="to" value="{{ $filters['to'] }}" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
        </div>
        <button type="submit" class="bg-gray-800 text-white text-sm font-bold px-4 py-1.5 rounded-lg">تصفية</button>
        @if($hasFilters)
            <a href="{{ route('admin.audit-logs.index') }}" class="text-xs font-bold text-gray-500 hover:text-gray-700 px-2 py-1.5">إعادة تعيين</a>
        @endif
    </form>

    @forelse($dayGroups as $day => $entries)
        <section class="bg-white rounded-2xl shadow overflow-hidden">
            <header class="px-4 py-2 bg-gray-50 border-b border-gray-100 flex items-center justify-between gap-2">
                <span class="text-sm font-bold text-gray-700 flex items-center gap-1.5">
                    <x-icon name="calendar" class="w-4 h-4 text-gray-400" />
                    {{ $entries->first()->dayLabel() }}
                </span>
                <span class="text-[11px] font-bold text-gray-400">{{ $entries->count() }} عملية</span>
            </header>
            <div class="divide-y divide-gray-50">
                @foreach($entries as $log)
                    @php $changes = $log->changes(); @endphp
                    <article class="px-4 py-2.5 flex gap-3">
                        <span class="w-8 h-8 rounded-full bg-pine-50 text-pine-700 grid place-items-center text-xs font-black shrink-0">
                            {{ $log->actorInitial() }}
                        </span>
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="text-sm font-bold text-gray-800">{{ $log->actorName() }}</span>
                                <span class="text-[11px] text-gray-400">{{ $log->exactTime() }}</span>
                                <span class="inline-flex items-center text-[10px] font-bold border rounded-full px-2 py-0.5 {{ $toneBadge[$log->groupTone()] ?? $toneBadge['gray'] }}">
                                    {{ $log->groupLabel() }}
                                </span>
                                <span class="text-[10px] text-gray-300 font-mono" dir="ltr">#{{ $log->reference() }}</span>
                            </div>
                            <div class="mt-0.5 flex flex-wrap items-center gap-2">
                                <span class="text-sm font-semibold text-pine-800">{{ $log->actionLabel() }}</span>
                            </div>
                            <div class="text-[11px] text-gray-500 mt-0.5">
                                {{ $log->entityLabel() }}
                                @if($log->log->entity_id)
                                    <span class="font-mono text-gray-300" dir="ltr">#{{ strtoupper(substr($log->log->entity_id, 0, 8)) }}</span>
                                @endif
                            </div>
                            @if($changes !== [])
                                <details class="mt-1.5">
                                    <summary class="cursor-pointer text-[11px] font-bold text-emerald-700 hover:text-emerald-800 select-none">
                                        عرض التغييرات ({{ count($changes) }})
                                    </summary>
                                    <div class="mt-1.5 overflow-x-auto rounded-xl border border-gray-100">
                                        <table class="w-full text-[11px]">
                                            <thead class="bg-gray-50 text-gray-500 text-right">
                                                <tr>
                                                    <th class="px-2.5 py-1.5 font-bold">الحقل</th>
                                                    <th class="px-2.5 py-1.5 font-bold">قبل</th>
                                                    <th class="px-2.5 py-1.5 font-bold">بعد</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($changes as $change)
                                                    <tr class="border-t border-gray-50 align-top">
                                                        <td class="px-2.5 py-1.5 font-semibold text-gray-600 whitespace-nowrap">{{ $change['label'] }}</td>
                                                        <td class="px-2.5 py-1.5 text-rose-700">{{ $change['before'] }}</td>
                                                        <td class="px-2.5 py-1.5 text-emerald-700">{{ $change['after'] }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </details>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @empty
        <div class="bg-white rounded-2xl shadow p-10 text-center text-gray-400 text-sm">لا توجد عمليات مطابقة</div>
    @endforelse

    <div>{{ $logs->links() }}</div>
</div>
@endsection
