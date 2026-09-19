@props([
    'blocks',
    'slotsByDate',
    'editable' => false,
    'teacher' => null,
    'payrollClosed' => false,
    'maxHours' => null,
])

@php
    $slotsByDate = $slotsByDate instanceof \Illuminate\Support\Collection ? $slotsByDate : collect($slotsByDate);
    $canEdit = $editable && ! $payrollClosed;
    $maxHours = $maxHours ?? app(\App\Services\WorkHoursSettingsService::class)->maxSlotHours();
@endphp

<div class="space-y-3">
    @foreach($blocks as $block)
        @php
            $blockSlots = collect();
            $cursor = $block['start']->copy();

            while ($cursor->lte($block['end'])) {
                $blockSlots = $blockSlots->merge($slotsByDate->get($cursor->toDateString(), collect()));
                $cursor = $cursor->addDay();
            }

            $blockMinutes = (int) $blockSlots->sum(fn ($slot) => (int) $slot->duration_minutes);
        @endphp

        <details class="group bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden" @if($blockMinutes > 0) open @endif>
            <summary class="cursor-pointer px-4 sm:px-5 py-3 flex flex-wrap items-center justify-between gap-3 bg-gray-50/60 hover:bg-gray-50 select-none">
                <span class="flex items-center gap-2 font-black text-gray-800">
                    <x-icon name="chevron" class="w-4 h-4 text-gray-400 transition-transform group-open:rotate-180" />
                    {{ $block['label'] }}
                </span>
                <span @class([
                    'text-sm font-black rounded-lg px-2 py-0.5',
                    'bg-emerald-50 text-emerald-700' => $blockMinutes > 0,
                    'text-gray-300' => $blockMinutes <= 0,
                ])>{{ $blockMinutes > 0 ? \App\Models\WorkSlot::formatMinutes($blockMinutes) : 'لا ساعات' }}</span>
            </summary>

            <div class="divide-y divide-gray-100">
                @php $dayCursor = $block['start']->copy(); @endphp

                @while($dayCursor->lte($block['end']))
                    @php
                        $daySlots = $slotsByDate->get($dayCursor->toDateString(), collect());
                        $dayMinutes = (int) $daySlots->sum(fn ($slot) => (int) $slot->duration_minutes);
                        $isToday = $dayCursor->isToday();
                    @endphp

                    <div @class(['px-4 sm:px-5 py-3', 'bg-gray-50/40' => $daySlots->isEmpty(), 'bg-gold-50/40' => $isToday && $daySlots->isNotEmpty()])>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-gray-700 text-sm">{{ \App\Enums\WorkDay::from($dayCursor->dayOfWeek)->label() }}</span>
                                <span class="text-[11px] text-gray-400" dir="ltr">{{ $dayCursor->format('Y-m-d') }}</span>
                                @if($isToday)
                                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-gold-100 text-gold-700">اليوم</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                <span @class([
                                    'text-xs font-bold',
                                    'text-pine-800' => $daySlots->isNotEmpty(),
                                    'text-gray-300' => $daySlots->isEmpty(),
                                ])>{{ $daySlots->isNotEmpty() ? \App\Models\WorkSlot::formatMinutes($dayMinutes) : '—' }}</span>

                                @if($canEdit)
                                    <details class="inline-block">
                                        <summary class="cursor-pointer list-none inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 select-none">
                                            <x-icon name="plus" class="w-3 h-3" />
                                            ساعات
                                        </summary>
                                        <div class="mt-2 w-full max-w-xl rounded-xl border border-gray-100 bg-gray-50/70 p-3">
                                            <x-quick-slot :teacher="$teacher" :date="$dayCursor->toDateString()" :max-hours="$maxHours" compact />
                                        </div>
                                    </details>
                                @endif
                            </div>
                        </div>

                        @if($daySlots->isNotEmpty())
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach($daySlots as $slot)
                                    <div class="inline-flex items-center gap-2 rounded-xl bg-pine-50/70 border border-pine-100 px-2.5 py-1.5">
                                        <span class="inline-flex items-center gap-1 font-bold text-pine-800 text-sm" dir="ltr">
                                            <x-icon name="clock" class="w-3.5 h-3.5" />
                                            {{ substr($slot->start_time, 0, 5) }} — {{ substr($slot->end_time, 0, 5) }}
                                        </span>
                                        <span class="text-[11px] font-bold text-gray-500">{{ \App\Models\WorkSlot::formatMinutes((int) $slot->duration_minutes) }}</span>

                                        @if($canEdit)
                                            <form method="POST" action="{{ route('admin.timesheet.slots.destroy', $slot) }}"
                                                  onsubmit="return confirm('حذف فترة العمل؟')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-gray-400 hover:text-red-600 transition-colors" title="حذف" aria-label="حذف فترة العمل">
                                                    <x-icon name="trash" class="w-3.5 h-3.5" />
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            @if($canEdit)
                                <details class="mt-2">
                                    <summary class="cursor-pointer text-[11px] text-emerald-700 font-bold select-none">تعديل فترة</summary>
                                    <div class="mt-2 space-y-2">
                                        @foreach($daySlots as $slot)
                                            <x-work-slot-form
                                                :action="route('admin.timesheet.slots.update', $slot)"
                                                method="PATCH"
                                                :teacher="$teacher"
                                                :work-slot="$slot"
                                                :show-conflict="false" />
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        @endif
                    </div>

                    @php $dayCursor = $dayCursor->addDay(); @endphp
                @endwhile
            </div>
        </details>
    @endforeach
</div>
