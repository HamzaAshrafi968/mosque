@extends('layouts.app')

@section('title', 'جدولي الدراسي')

@php
    $authorization = app(\App\Services\AuthorizationService::class);
    $can = fn (string $permission) => $authorization->can(auth()->user(), $permission);
    $days = [
        0 => 'الأحد',
        1 => 'الاثنين',
        2 => 'الثلاثاء',
        3 => 'الأربعاء',
        4 => 'الخميس',
        5 => 'الجمعة',
        6 => 'السبت',
    ];
    $today = (int) now()->dayOfWeek;
    $totalLessons = $schedules->flatten(1)->count();
    $nextDateFor = function (int $day) use ($today): string {
        return $today === $day ? now()->toDateString() : now()->copy()->next($day)->toDateString();
    };
@endphp

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">جدولي الدراسي</h1>
        <p class="text-gray-500 text-sm mt-1">{{ $totalLessons }} حصة أسبوعية موزّعة على أيام الأسبوع</p>
    </div>
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

@if($schedules->isEmpty())
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-10 text-center">
        <div class="text-4xl mb-3">📅</div>
        <p class="text-gray-500">لا يوجد جدول دراسي بعد — تواصل مع إدارة الجامع لإضافة حصصك.</p>
    </div>
@else
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm table-fixed min-w-[1100px]">
                <thead>
                    <tr>
                        @foreach($days as $dayNum => $dayName)
                            <th class="border-r border-gray-100 last:border-r-0 p-0 align-top">
                                <div @class([
                                    'px-3 py-3 text-center border-b-2',
                                    'bg-emerald-50 border-emerald-600' => $dayNum === $today,
                                    'bg-gray-50 border-transparent' => $dayNum !== $today,
                                ])>
                                    <div class="font-black {{ $dayNum === $today ? 'text-emerald-800' : 'text-gray-700' }}">
                                        {{ $dayName }}
                                        @if($dayNum === $today)
                                            <span class="text-[10px] font-bold bg-emerald-600 text-white rounded-full px-1.5 py-0.5 align-middle">اليوم</span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-gray-400 mt-0.5">
                                        {{ $schedules->get($dayNum, collect())->count() }} حصة
                                    </div>
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr class="align-top">
                        @foreach($days as $dayNum => $dayName)
                            <td class="border-r border-gray-100 last:border-r-0 p-2 min-h-[120px]">
                                @forelse($schedules->get($dayNum, collect())->sortBy('starts_at') as $schedule)
                                    @php
                                        $slotExceptions = $exceptions->get($schedule->id, collect());
                                        $color = $schedule->program?->color ?: '#10b981';
                                    @endphp
                                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-2.5 mb-2"
                                         style="border-inline-start: 4px solid {{ $color }}">
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="font-black text-gray-800 whitespace-nowrap">
                                                {{ substr($schedule->starts_at, 0, 5) }}–{{ substr($schedule->ends_at, 0, 5) }}
                                            </span>
                                            @if($schedule->program)
                                                <span class="text-[10px] px-1.5 py-0.5 rounded-full whitespace-nowrap"
                                                      style="background: {{ $color }}22; color: {{ $color }}">
                                                    {{ $schedule->program->name }}
                                                </span>
                                            @endif
                                        </div>

                                        <div class="mt-1 font-bold text-gray-700">
                                            {{ $schedule->subject?->name ?? 'حصة' }}
                                        </div>
                                        <div class="text-xs text-gray-500 mt-0.5">
                                            {{ $schedule->classroom?->name }}
                                            @if($schedule->section)
                                                <span class="text-gray-300">—</span> {{ $schedule->section->name }}
                                            @endif
                                        </div>

                                        <div class="flex flex-wrap gap-1 mt-1.5">
                                            @if($schedule->studySession)
                                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-pine-50 text-pine-800">
                                                    دوام {{ $schedule->studySession->display_name }}
                                                </span>
                                            @endif
                                            @if($schedule->programPeriod)
                                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-500">
                                                    {{ $schedule->programPeriod->name }}
                                                </span>
                                            @endif
                                            @if($schedule->validityLabel())
                                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-600"
                                                      title="مدة صلاحية الحصة">
                                                    {{ $schedule->validityLabel() }}
                                                </span>
                                            @endif
                                        </div>

                                        @foreach($slotExceptions as $exception)
                                            <div class="mt-1.5 rounded-lg px-2 py-1 text-[11px] {{ $exception->status->value === 'cancelled' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700' }}">
                                                <div class="flex items-center justify-between gap-1">
                                                    <span>
                                                        {{ $exception->status->label() }} {{ $exception->date->format('m/d') }}
                                                        @if($exception->status->value === 'postponed')
                                                            ← {{ $exception->postponed_date?->format('m/d') }} {{ substr((string) $exception->postponed_starts_at, 0, 5) }}
                                                        @endif
                                                    </span>
                                                    @if ($can('schedule.update'))
                                                        <form method="POST" action="{{ route('teacher.schedule.restore', $exception) }}">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="hover:underline font-bold">إرجاع</button>
                                                        </form>
                                                    @endif
                                                </div>
                                                @if($exception->reason)
                                                    <div class="text-[10px] opacity-75">{{ $exception->reason }}</div>
                                                @endif
                                            </div>
                                        @endforeach

                                        @if ($can('schedule.update'))
                                        <details class="mt-1.5">
                                            <summary class="cursor-pointer text-[11px] text-gray-400 hover:text-gray-600 select-none">إلغاء / تأجيل</summary>
                                            @php $nextDate = $nextDateFor((int) $schedule->day_of_week); @endphp
                                            <form method="POST" action="{{ route('teacher.schedule.cancel', $schedule) }}" class="mt-1.5 space-y-1">
                                                @csrf
                                                <input type="date" name="date" required value="{{ $nextDate }}" class="w-full border border-gray-300 rounded px-1.5 py-0.5 text-xs">
                                                <input type="text" name="reason" placeholder="سبب الإلغاء (اختياري)" class="w-full border border-gray-300 rounded px-1.5 py-0.5 text-xs">
                                                <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white rounded py-1 text-xs font-bold">إلغاء هذا اليوم</button>
                                            </form>
                                            <form method="POST" action="{{ route('teacher.schedule.postpone', $schedule) }}" class="mt-1.5 space-y-1 border-t border-dashed pt-1.5">
                                                @csrf
                                                <input type="hidden" name="date" value="{{ $nextDate }}">
                                                <input type="date" name="postponed_date" required value="{{ $nextDate }}" class="w-full border border-gray-300 rounded px-1.5 py-0.5 text-xs">
                                                <div class="flex gap-1">
                                                    <input type="time" name="postponed_starts_at" required value="{{ substr($schedule->starts_at, 0, 5) }}" class="w-1/2 border border-gray-300 rounded px-1 py-0.5 text-xs">
                                                    <input type="time" name="postponed_ends_at" required value="{{ substr($schedule->ends_at, 0, 5) }}" class="w-1/2 border border-gray-300 rounded px-1 py-0.5 text-xs">
                                                </div>
                                                <button type="submit" class="w-full bg-amber-600 hover:bg-amber-700 text-white rounded py-1 text-xs font-bold">تأجيل</button>
                                            </form>
                                        </details>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-gray-300 text-xs text-center py-6">لا حصص</p>
                                @endforelse
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
