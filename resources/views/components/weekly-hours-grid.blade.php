@props(['hours', 'editable' => false, 'teacher' => null, 'days' => null])

@php
    $days = $days ?? \App\Enums\WorkDay::cases();
    $hours = $hours instanceof \Illuminate\Support\Collection ? $hours : collect($hours);
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3.5">
    @foreach($days as $day)
        @php
            $dayHours = $hours->get($day->value, collect());
            $dayTotal = round($dayHours->sum(fn ($hour) => $hour->durationHours()), 2);
            $isToday = $day->value === now()->dayOfWeek;
        @endphp
        <div @class([
            'rounded-2xl border bg-white shadow-sm p-4',
            'border-gold-400 ring-1 ring-gold-300/60' => $isToday,
            'border-pine-950/[0.06]' => ! $isToday,
        ])>
            <div class="flex items-center justify-between mb-3">
                <span class="font-black text-pine-950">
                    {{ $day->label() }}
                    @if($isToday)<span class="text-[10px] text-gold-600 font-bold">(اليوم)</span>@endif
                </span>
                <span @class([
                    'text-[11px] font-bold rounded-full px-2.5 py-1',
                    'bg-emerald-50 text-emerald-700' => $dayTotal > 0,
                    'bg-gray-100 text-gray-400' => $dayTotal <= 0,
                ])>{{ $dayTotal > 0 ? $dayTotal.' س' : '—' }}</span>
            </div>

            @forelse($dayHours as $hour)
                <div class="rounded-xl bg-pine-50/60 border border-pine-100 p-2.5 mb-2">
                    <div class="flex items-center justify-between gap-2">
                        <span class="inline-flex items-center gap-1.5 font-bold text-pine-800 text-sm">
                            <x-icon name="clock" class="w-3.5 h-3.5" />
                            {{ substr($hour->start_time, 0, 5) }} — {{ substr($hour->end_time, 0, 5) }}
                        </span>
                        @if($editable)
                            <form method="POST" action="{{ route('admin.work-hours.destroy', $hour) }}" onsubmit="return confirm('حذف فترة العمل؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-bold">حذف</button>
                            </form>
                        @endif
                    </div>
                    @if($hour->notes)
                        <p class="text-xs text-gray-500 mt-1">{{ $hour->notes }}</p>
                    @endif
                    @if($editable)
                        <details class="mt-2">
                            <summary class="cursor-pointer text-xs text-emerald-700 font-bold select-none">تعديل</summary>
                            <x-work-hour-form :action="route('admin.work-hours.update', $hour)" method="PATCH" :work-hour="$hour" class="mt-2" />
                        </details>
                    @endif
                </div>
            @empty
                <p class="text-xs text-gray-400">لا فترات</p>
            @endforelse
        </div>
    @endforeach
</div>
