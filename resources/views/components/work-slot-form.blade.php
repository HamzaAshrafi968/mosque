@props([
    'action',
    'method' => 'POST',
    'teacher' => null,
    'workSlot' => null,
    'teachers' => null,
    'date' => null,
    'saveAndAdd' => false,
    'showConflict' => true,
])

@php
    $slotDate = $workSlot?->date?->format('Y-m-d') ?? $date ?? now()->toDateString();
    $daySlotsUrl = route('admin.timesheet.day-slots');
    $uid = $workSlot?->id ?? 'new';
@endphp

<form method="POST" action="{{ $action }}"
      data-work-slot-form
      data-day-slots-url="{{ $daySlotsUrl }}"
      @if($workSlot) data-slot-id="{{ $workSlot->id }}" @endif
      {{ $attributes->merge(['class' => 'space-y-3']) }}>
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2">
        @if($teacher)
            <input type="hidden" name="teacher_id" value="{{ $teacher->id }}">
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-bold text-gray-700 truncate">
                {{ $teacher->name }}
            </div>
        @else
            <div>
                <label class="block text-[11px] font-bold text-gray-500 mb-1" for="slot-teacher-{{ $uid }}">المعلم</label>
                <select id="slot-teacher-{{ $uid }}" name="teacher_id" required
                        class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm bg-white">
                    <option value="">اختر المعلم…</option>
                    @foreach(($teachers ?? collect()) as $option)
                        <option value="{{ $option->id }}" @selected((string) old('teacher_id', $workSlot?->teacher_id) === (string) $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div>
            <label class="block text-[11px] font-bold text-gray-500 mb-1" for="slot-date-{{ $uid }}">التاريخ</label>
            <input id="slot-date-{{ $uid }}" type="date" name="date" required
                   value="{{ old('date', $slotDate) }}"
                   class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm bg-white" dir="ltr">
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-[11px] font-bold text-gray-500 mb-1" for="slot-start-{{ $uid }}">من</label>
                <input id="slot-start-{{ $uid }}" type="time" name="start_time" required
                       value="{{ old('start_time', $workSlot ? substr($workSlot->start_time, 0, 5) : '') }}"
                       class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm bg-white" dir="ltr">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-500 mb-1" for="slot-end-{{ $uid }}">إلى</label>
                <input id="slot-end-{{ $uid }}" type="time" name="end_time" required
                       value="{{ old('end_time', $workSlot ? substr($workSlot->end_time, 0, 5) : '') }}"
                       class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm bg-white" dir="ltr">
            </div>
        </div>

        <div>
            <label class="block text-[11px] font-bold text-gray-500 mb-1" for="slot-notes-{{ $uid }}">ملاحظات (اختياري)</label>
            <input id="slot-notes-{{ $uid }}" type="text" name="notes" maxlength="1000" value="{{ old('notes', $workSlot?->notes) }}"
                   placeholder="مثال: حصة تعويضية"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <span class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-600 bg-gray-100 rounded-lg px-2.5 py-1.5" data-slot-duration>—</span>
        @if($showConflict)
            <span class="inline-flex items-center gap-1.5 text-xs font-bold text-red-600 bg-red-50 rounded-lg px-2.5 py-1.5 hidden" data-slot-conflict role="status" aria-live="polite"></span>
        @endif

        <div class="flex items-center gap-2 ms-auto">
            @if($saveAndAdd && ! $workSlot)
                <button type="submit" name="save_and_add" value="1"
                        class="bg-white border border-gray-300 hover:border-emerald-400 text-gray-700 text-sm font-bold px-4 py-2 rounded-lg">
                    حفظ وإضافة أخرى
                </button>
            @endif
            <button type="submit"
                    class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-5 py-2 rounded-lg">
                {{ $workSlot ? 'حفظ التعديل' : 'حفظ' }}
            </button>
        </div>
    </div>
</form>
