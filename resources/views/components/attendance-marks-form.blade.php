@props([
    'students',
    'existing' => collect(),
    'notes' => collect(),
    'records' => collect(),
    'date' => null,
    'action',
    'method' => 'POST',
    'submitLabel' => 'حفظ الحضور',
])

@php
    $statuses = \App\Enums\AttendanceStatus::cases();
@endphp

<form method="POST" action="{{ $action }}">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif
    @if($date)
        <input type="hidden" name="date" value="{{ $date }}">
    @endif
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50 text-gray-600 text-sm">
                    <th class="px-4 py-3 text-right whitespace-nowrap">الاسم</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">الجنس</th>
                    <th class="px-4 py-3 text-center whitespace-nowrap">حالة الحضور</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap w-1/4">ملاحظة</th>
                </tr>
            </thead>
            <tbody>
                @foreach($students as $student)
                    @php
                        $record = $records->get($student->id);
                        $current = $existing->get($student->id) ?? $record?->status?->value ?? 'present';
                        $note = $notes->get($student->id) ?? $record?->note ?? null;
                    @endphp
                    <tr>
                        <td class="px-4 py-3 border-t font-medium whitespace-nowrap">{{ $student->name }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $student->gender === 'male' ? 'ذكر' : 'أنثى' }}</td>
                        <td class="px-4 py-3 border-t">
                            <div class="flex justify-center gap-3 flex-wrap">
                                @foreach($statuses as $status)
                                    @php
                                        $colors = match ($status) {
                                            \App\Enums\AttendanceStatus::Present => 'text-green-700 accent-green-600',
                                            \App\Enums\AttendanceStatus::Absent => 'text-red-700 accent-red-600',
                                            \App\Enums\AttendanceStatus::Late => 'text-yellow-700 accent-yellow-500',
                                            \App\Enums\AttendanceStatus::Excused => 'text-sky-700 accent-sky-600',
                                        };
                                    @endphp
                                    <label class="inline-flex items-center gap-1 {{ $colors }}">
                                        <input type="radio" name="statuses[{{ $student->id }}]" value="{{ $status->value }}"
                                               class="accent-current" @checked($current === $status->value)>
                                        <span class="text-sm whitespace-nowrap">{{ $status->label() }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-3 border-t">
                            <input type="text" name="notes[{{ $student->id }}]" value="{{ old('notes.'.$student->id, $note) }}"
                                   maxlength="500" placeholder="—"
                                   class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-sm focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="p-4 border-t">
        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">
            {{ $submitLabel }}
        </button>
    </div>
</form>
