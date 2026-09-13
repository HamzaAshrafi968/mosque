@props(['action', 'method' => 'POST', 'workHour' => null])

<form method="POST" action="{{ $action }}" {{ $attributes->merge(['class' => 'space-y-2']) }}>
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
        <select name="day_of_week" required class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm">
            @foreach(\App\Enums\WorkDay::cases() as $day)
                <option value="{{ $day->value }}" @selected((int) old('day_of_week', $workHour?->day_of_week ?? now()->dayOfWeek) === $day->value)>{{ $day->label() }}</option>
            @endforeach
        </select>
        <input type="time" name="start_time" required value="{{ old('start_time', $workHour ? substr($workHour->start_time, 0, 5) : '') }}"
               class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm" aria-label="من">
        <input type="time" name="end_time" required value="{{ old('end_time', $workHour ? substr($workHour->end_time, 0, 5) : '') }}"
               class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm" aria-label="إلى">
    </div>

    <input type="text" name="notes" maxlength="1000" value="{{ old('notes', $workHour?->notes) }}" placeholder="ملاحظات (اختياري)"
           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">

    <button type="submit" class="w-full bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">
        {{ $workHour ? 'حفظ التعديل' : 'إضافة الفترة' }}
    </button>
</form>
