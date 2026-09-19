@props([
    'action' => null,
    'teacher' => null,
    'teachers' => null,
    'date' => null,
    'maxHours' => null,
    'compact' => false,
])

@php
    $action = $action ?? route('admin.timesheet.slots.store');
    $slotDate = $date ?? now()->toDateString();
    $maxHours = $maxHours ?? app(\App\Services\WorkHoursSettingsService::class)->maxSlotHours();
    $uid = 'qs-'.\Illuminate\Support\Str::random(6);
    $chips = collect([1, 2, 3, 4, 5, 6, 8])->filter(fn ($hours) => $hours <= $maxHours)->values();
@endphp

<form method="POST" action="{{ $action }}"
      data-quick-slot-form
      data-day-slots-url="{{ route('admin.timesheet.day-slots') }}"
      {{ $attributes->merge(['class' => 'space-y-3']) }}>
    @csrf

    <div class="grid grid-cols-1 {{ $teacher ? 'sm:grid-cols-2' : 'sm:grid-cols-3' }} gap-2">
        @if($teacher)
            <input type="hidden" name="teacher_id" value="{{ $teacher->id }}">
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-bold text-gray-700 truncate">
                {{ $teacher->name }}
            </div>
        @else
            <div>
                <label class="block text-[11px] font-bold text-gray-500 mb-1" for="{{ $uid }}-teacher">المعلم</label>
                <select id="{{ $uid }}-teacher" name="teacher_id" required
                        class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm bg-white">
                    <option value="">اختر المعلم…</option>
                    @foreach(($teachers ?? collect()) as $option)
                        <option value="{{ $option->id }}" @selected((string) old('teacher_id') === (string) $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div>
            <label class="block text-[11px] font-bold text-gray-500 mb-1" for="{{ $uid }}-date">التاريخ</label>
            <input id="{{ $uid }}-date" type="date" name="date" required value="{{ old('date', $slotDate) }}"
                   class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm bg-white" dir="ltr">
        </div>

        <div>
            <label class="block text-[11px] font-bold text-gray-500 mb-1" for="{{ $uid }}-hours">عدد الساعات</label>
            <div class="flex flex-wrap items-center gap-1.5">
                @foreach($chips as $hours)
                    <button type="button" data-quick-slot-chip="{{ $hours }}"
                            class="px-2.5 py-2 rounded-lg border border-gray-200 bg-white text-xs font-black text-gray-600 hover:border-emerald-400 hover:text-emerald-700 transition-colors">{{ $hours }}س</button>
                @endforeach
                <input id="{{ $uid }}-hours" type="number" name="hours" required step="0.25" min="0.25" max="{{ $maxHours }}"
                       value="{{ old('hours') }}" placeholder="أو اكتب" dir="ltr" data-quick-slot-hours
                       class="w-24 border border-gray-300 rounded-lg px-2.5 py-2 text-sm bg-white">
            </div>
        </div>
    </div>

    <details class="rounded-lg border border-gray-100 bg-gray-50/60 px-3 py-2">
        <summary class="cursor-pointer text-xs font-bold text-gray-500 select-none">خيارات إضافية (من الساعة / ملاحظات)</summary>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2">
            <div>
                <label class="block text-[11px] font-bold text-gray-500 mb-1" for="{{ $uid }}-start">من الساعة (اختياري — الافتراضي 08:00)</label>
                <input id="{{ $uid }}-start" type="time" name="start_time" value="{{ old('start_time') }}"
                       class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm bg-white" dir="ltr" data-quick-slot-start>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-500 mb-1" for="{{ $uid }}-notes">ملاحظات (اختياري)</label>
                <input id="{{ $uid }}-notes" type="text" name="notes" maxlength="1000" value="{{ old('notes') }}"
                       class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm bg-white">
            </div>
        </div>
    </details>

    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-600 bg-gray-100 rounded-lg px-2.5 py-1.5" data-quick-slot-preview>—</span>
            <span class="inline-flex items-center gap-1.5 text-xs font-bold text-red-600 bg-red-50 rounded-lg px-2.5 py-1.5 hidden" data-quick-slot-conflict role="status" aria-live="polite"></span>
        </div>
        <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-5 py-2 rounded-lg">
            {{ $compact ? 'حفظ' : 'حفظ الساعات' }}
        </button>
    </div>
</form>
