@extends('layouts.app')

@section('title', 'جدولي الدراسي')

@php
    $days = [
        0 => 'الأحد',
        1 => 'الاثنين',
        2 => 'الثلاثاء',
        3 => 'الأربعاء',
        4 => 'الخميس',
        5 => 'الجمعة',
        6 => 'السبت',
    ];
    $nextDateFor = function (int $day): string {
        $today = now();

        return $today->dayOfWeek === $day ? $today->toDateString() : $today->copy()->next($day)->toDateString();
    };
@endphp

@section('content')
<div class="mb-4 flex flex-wrap items-center gap-3">
    <button onclick="window.print()" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">طباعة الجدول</button>
</div>

@if($conflicts->isNotEmpty())
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 mb-6">
        <h2 class="font-bold mb-2">تعارضات في جدولك (نفس اليوم والوقت)</h2>
        <ul class="list-disc pr-5 space-y-1 text-sm">
            @foreach($conflicts as $conflict)
                <li>
                    {{ $days[$conflict['first']->day_of_week] }}
                    ({{ substr($conflict['first']->starts_at, 0, 5) }}–{{ substr($conflict['first']->ends_at, 0, 5) }}):
                    {{ $conflict['first']->subject?->name ?? $conflict['first']->program?->name ?? 'حصة' }}
                    ({{ $conflict['first']->section?->name ?? $conflict['first']->classroom?->name }})
                    يتعارض مع
                    {{ $conflict['second']->subject?->name ?? $conflict['second']->program?->name ?? 'حصة' }}
                    ({{ $conflict['second']->section?->name ?? $conflict['second']->classroom?->name }})
                </li>
            @endforeach
        </ul>
    </div>
@endif

@if($exceptions->isNotEmpty())
    <div class="bg-white rounded-xl shadow overflow-hidden mb-6">
        <h2 class="text-lg font-bold text-gray-800 p-4 border-b">التغييرات القادمة (إلغاء/تأجيل)</h2>
        <div class="divide-y">
            @foreach($exceptions as $scheduleId => $rows)
                @foreach($rows as $exception)
                    <div class="p-4 flex flex-wrap items-center justify-between gap-2 text-sm">
                        <div>
                            <span class="font-bold {{ $exception->status->value === 'cancelled' ? 'text-red-600' : 'text-amber-600' }}">{{ $exception->status->label() }}</span>
                            — {{ $exception->schedule?->subject?->name ?? $exception->schedule?->program?->name ?? 'حصة' }}
                            ({{ $exception->schedule?->classroom?->name }}@if($exception->schedule?->section) — {{ $exception->schedule->section->name }}@endif)
                            <span class="text-gray-500">
                                يوم {{ $exception->date?->toDateString() }}
                                @if($exception->status->value === 'postponed')
                                    ← {{ $exception->postponed_date?->toDateString() }}
                                    ({{ substr((string) $exception->postponed_starts_at, 0, 5) }}–{{ substr((string) $exception->postponed_ends_at, 0, 5) }})
                                @endif
                            </span>
                            @if($exception->reason)
                                <span class="text-gray-400">— {{ $exception->reason }}</span>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('teacher.schedule.restore', $exception) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-emerald-700 hover:underline text-sm">إرجاع</button>
                        </form>
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>
@endif

@foreach($days as $num => $dayName)
    @if($schedules->has($num))
        <div class="bg-white rounded-xl shadow overflow-hidden mb-6">
            <h2 class="text-lg font-bold text-gray-800 p-4 border-b">{{ $dayName }}</h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600 text-sm">
                            <th class="px-4 py-3 text-right whitespace-nowrap">الوقت</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap">البرنامج</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap">الفترة</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap">المادة</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap">الصف</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap">الشعبة</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap">تغيير يوم واحد</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($schedules[$num] as $schedule)
                            <tr>
                                <td class="px-4 py-3 border-t whitespace-nowrap">{{ substr($schedule->starts_at, 0, 5) }} - {{ substr($schedule->ends_at, 0, 5) }}</td>
                                <td class="px-4 py-3 border-t whitespace-nowrap">
                                    @if($schedule->program)
                                        <span class="inline-flex items-center gap-1.5">
                                            <span class="w-2.5 h-2.5 rounded-full" style="background: {{ $schedule->program->color ?: '#475569' }}"></span>
                                            {{ $schedule->program->name }}
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 border-t whitespace-nowrap">{{ $schedule->programPeriod?->name ?? '—' }}</td>
                                <td class="px-4 py-3 border-t whitespace-nowrap">{{ $schedule->subject?->name ?? '—' }}</td>
                                <td class="px-4 py-3 border-t whitespace-nowrap">{{ $schedule->classroom?->name }}</td>
                                <td class="px-4 py-3 border-t whitespace-nowrap">{{ $schedule->section?->name ?? 'كل الشعب' }}</td>
                                <td class="px-4 py-3 border-t whitespace-nowrap">
                                    @php $nextDate = $nextDateFor((int) $schedule->day_of_week); @endphp
                                    <details>
                                        <summary class="cursor-pointer text-xs text-gray-500">إلغاء/تأجيل</summary>
                                        <form method="POST" action="{{ route('teacher.schedule.cancel', $schedule) }}" class="mt-2 space-y-1 w-56">
                                            @csrf
                                            <input type="date" name="date" required value="{{ $nextDate }}" class="w-full border border-gray-300 rounded px-2 py-1 text-xs">
                                            <input type="text" name="reason" placeholder="سبب الإلغاء (اختياري)" class="w-full border border-gray-300 rounded px-2 py-1 text-xs">
                                            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white rounded py-1 text-xs">إلغاء هذا اليوم</button>
                                        </form>
                                        <form method="POST" action="{{ route('teacher.schedule.postpone', $schedule) }}" class="mt-2 space-y-1 w-56 border-t border-dashed pt-2">
                                            @csrf
                                            <input type="hidden" name="date" value="{{ $nextDate }}">
                                            <input type="date" name="postponed_date" required value="{{ $nextDate }}" class="w-full border border-gray-300 rounded px-2 py-1 text-xs">
                                            <div class="flex gap-1">
                                                <input type="time" name="postponed_starts_at" required value="{{ substr($schedule->starts_at, 0, 5) }}" class="w-1/2 border border-gray-300 rounded px-1 py-1 text-xs">
                                                <input type="time" name="postponed_ends_at" required value="{{ substr($schedule->ends_at, 0, 5) }}" class="w-1/2 border border-gray-300 rounded px-1 py-1 text-xs">
                                            </div>
                                            <button type="submit" class="w-full bg-amber-600 hover:bg-amber-700 text-white rounded py-1 text-xs">تأجيل</button>
                                        </form>
                                    </details>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endforeach

@if($schedules->isEmpty())
    <div class="bg-white rounded-xl shadow p-6 text-center text-gray-500">لا يوجد جدول دراسي</div>
@endif
@endsection
