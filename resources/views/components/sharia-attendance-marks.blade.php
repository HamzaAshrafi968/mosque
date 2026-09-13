@props([
    'students',
    'existing' => collect(),
    'notes' => collect(),
    'action',
    'lessonId' => null,
    'date' => null,
    'submitLabel' => 'حفظ الحضور',
])

@php $statuses = \App\Enums\ShariaAttendanceStatus::cases(); @endphp

<form method="POST" action="{{ $action }}">
    @csrf
    @if($lessonId)
        <input type="hidden" name="lesson_id" value="{{ $lessonId }}">
    @endif
    @if($date)
        <input type="hidden" name="date" value="{{ $date }}">
    @endif

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-gray-600">
                    <th class="px-4 py-3 text-right">الطالب</th>
                    <th class="px-4 py-3 text-center">الحالة</th>
                    <th class="px-4 py-3 text-right">ملاحظة</th>
                </tr>
            </thead>
            <tbody>
            @forelse($students as $student)
                @php
                    $current = $existing->get($student->id) ?? 'present';
                    $note = $notes->get($student->id);
                @endphp
                <tr class="border-t">
                    <td class="px-4 py-3 font-bold text-gray-800 whitespace-nowrap">{{ $student->name }}</td>
                    <td class="px-4 py-3">
                        <div class="flex justify-center gap-3 flex-wrap">
                            @foreach($statuses as $status)
                                @php
                                    $colors = match ($status) {
                                        \App\Enums\ShariaAttendanceStatus::Present => 'text-green-700 accent-green-600',
                                        \App\Enums\ShariaAttendanceStatus::Absent => 'text-red-700 accent-red-600',
                                        \App\Enums\ShariaAttendanceStatus::Late => 'text-yellow-700 accent-yellow-500',
                                        \App\Enums\ShariaAttendanceStatus::Excused => 'text-sky-700 accent-sky-600',
                                    };
                                @endphp
                                <label class="inline-flex items-center gap-1 {{ $colors }}">
                                    <input type="radio" name="marks[{{ $student->id }}][status]" value="{{ $status->value }}" @checked($current === $status->value)>
                                    <span class="text-sm whitespace-nowrap">{{ $status->label() }}</span>
                                </label>
                            @endforeach
                        </div>
                    </td>
                    <td class="px-4 py-3 min-w-48">
                        <input type="text" name="marks[{{ $student->id }}][notes]" value="{{ old('marks.'.$student->id.'.notes', $note) }}"
                               maxlength="500" placeholder="ملاحظة (إلزامية للمعذور)"
                               class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-sm focus:ring-1 focus:ring-emerald-500 focus:outline-none">
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="px-4 py-8 text-center text-gray-400">لا يوجد طلاب نشطون في الدورة</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($students->isNotEmpty())
        <div class="p-4 border-t border-gray-100">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">{{ $submitLabel }}</button>
            <span class="text-xs text-gray-400 ms-3">قاعدة النسبة: معذور مستبعد من المقام والبسط</span>
        </div>
    @endif
</form>
