@props([
    'weeks',
    'slotsByDate',
    'editable' => false,
    'teacher' => null,
    'payrollClosed' => false,
])

@php
    $slotsByDate = $slotsByDate instanceof \Illuminate\Support\Collection ? $slotsByDate : collect($slotsByDate);
@endphp

<div class="space-y-4">
    @foreach($weeks as $week)
        @php
            $weekSlots = collect();
            $weekCursor = $week['start']->copy();

            while ($weekCursor->lte($week['end'])) {
                $weekSlots = $weekSlots->merge($slotsByDate->get($weekCursor->toDateString(), collect()));
                $weekCursor = $weekCursor->addDay();
            }

            $weekMinutes = (int) $weekSlots->sum(fn ($slot) => (int) $slot->duration_minutes);
        @endphp

        <details class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden" @if($weekMinutes > 0) open @endif>
            <summary class="cursor-pointer px-5 py-3 flex flex-wrap items-center justify-between gap-3 bg-gray-50/60 select-none">
                <span class="font-black text-gray-800">{{ $week['label'] }}</span>
                <span class="flex items-center gap-2">
                    @if($week['starts_before_month'] || $week['ends_after_month'])
                        <span class="text-[11px] font-bold rounded-full px-2 py-0.5 bg-amber-50 text-amber-700">يمتد لشهر آخر</span>
                    @endif
                    <span @class([
                        'text-sm font-black',
                        'text-emerald-700' => $weekMinutes > 0,
                        'text-gray-300' => $weekMinutes <= 0,
                    ])>{{ \App\Models\WorkSlot::formatMinutes($weekMinutes) }}</span>
                </span>
            </summary>

            <div class="divide-y divide-gray-100">
                @php $dayCursor = $week['start']->copy(); @endphp

                @while($dayCursor->lte($week['end']))
                    @php $daySlots = $slotsByDate->get($dayCursor->toDateString(), collect()); @endphp

                    <div class="px-5 py-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-gray-700">{{ \App\Enums\WorkDay::from($dayCursor->dayOfWeek)->label() }}</span>
                                <span class="text-xs text-gray-400" dir="ltr">{{ $dayCursor->format('Y-m-d') }}</span>
                            </div>
                            <span @class([
                                'text-xs font-bold',
                                'text-pine-800' => $daySlots->isNotEmpty(),
                                'text-gray-300' => $daySlots->isEmpty(),
                            ])>{{ $daySlots->isNotEmpty() ? \App\Models\WorkSlot::formatMinutes((int) $daySlots->sum(fn ($slot) => (int) $slot->duration_minutes)) : '—' }}</span>
                        </div>

                        @if($daySlots->isNotEmpty())
                            <div class="mt-2 space-y-2">
                                @foreach($daySlots as $slot)
                                    <div class="rounded-xl bg-pine-50/60 border border-pine-100 p-2.5">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <span class="inline-flex items-center gap-1.5 font-bold text-pine-800 text-sm" dir="ltr">
                                                <x-icon name="clock" class="w-3.5 h-3.5" />
                                                {{ substr($slot->start_time, 0, 5) }} — {{ substr($slot->end_time, 0, 5) }}
                                            </span>
                                            <span class="flex items-center gap-2">
                                                <span class="text-xs font-bold text-gray-500">{{ \App\Models\WorkSlot::formatMinutes((int) $slot->duration_minutes) }}</span>
                                                @if($editable && ! $payrollClosed)
                                                    <form method="POST" action="{{ route('admin.timesheet.slots.destroy', $slot) }}"
                                                          onsubmit="return confirm('حذف فترة العمل؟')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-bold">حذف</button>
                                                    </form>
                                                @endif
                                            </span>
                                        </div>

                                        @if($slot->notes)
                                            <p class="text-xs text-gray-500 mt-1">{{ $slot->notes }}</p>
                                        @endif

                                        @if($editable && ! $payrollClosed)
                                            <details class="mt-2">
                                                <summary class="cursor-pointer text-xs text-emerald-700 font-bold select-none">تعديل</summary>
                                                <x-work-slot-form
                                                    :action="route('admin.timesheet.slots.update', $slot)"
                                                    method="PATCH"
                                                    :teacher="$teacher"
                                                    :work-slot="$slot"
                                                    :show-conflict="false"
                                                    class="mt-2" />
                                            </details>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @php $dayCursor = $dayCursor->addDay(); @endphp
                @endwhile
            </div>
        </details>
    @endforeach
</div>
