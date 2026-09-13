@props([
    'week',
    'evaluation' => null,
    'month',
    'student',
    'action',
    'method' => 'POST',
    'results',
    'weekStart',
    'weekEnd',
    'teachers' => null,
    'evaluatedBy' => null,
    'canDelete' => false,
    'deleteAction' => null,
])

@php
    $result = $evaluation?->result;
@endphp

<div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-4">
    <div class="flex items-start justify-between gap-2">
        <div>
            <div class="font-black text-pine-950">الأسبوع {{ $week }}</div>
            <div class="text-[11px] text-gray-400 font-bold mt-0.5">
                {{ $evaluation?->week_start?->format('Y-m-d') ?? $weekStart }} — {{ $evaluation?->week_end?->format('Y-m-d') ?? $weekEnd }}
            </div>
        </div>
        @if($evaluation)
            <span @class([
                'px-2 py-0.5 rounded-full text-[11px] font-bold shrink-0',
                'bg-green-100 text-green-800' => $result?->value === 'passed',
                'bg-yellow-100 text-yellow-800' => $result?->value === 'needs_review',
                'bg-red-100 text-red-800' => $result?->value === 'failed',
            ])>{{ $result?->label() }}</span>
        @else
            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-gray-100 text-gray-500 shrink-0">لم يُقيَّم</span>
        @endif
    </div>

    @if($evaluation)
        <div class="mt-3 text-sm text-gray-600 space-y-1">
            <div>المقدار: <span class="font-bold">{{ $evaluation->amount }}</span></div>
            @if($evaluation->recited_portion)<div>المقروء: {{ $evaluation->recited_portion }}</div>@endif
            @if($evaluation->evaluatedBy)<div class="text-xs text-gray-400">المشرف: {{ $evaluation->evaluatedBy->name }}</div>@endif
            @if($evaluation->notes)<div class="text-xs text-gray-400">{{ $evaluation->notes }}</div>@endif
        </div>
    @endif

    <details class="mt-3" @if(! $evaluation) open @endif>
        <summary class="cursor-pointer text-xs text-emerald-700 font-bold select-none">{{ $evaluation ? 'تعديل التقييم' : 'تسجيل التقييم' }}</summary>

        <form method="POST" action="{{ $action }}" class="mt-3 space-y-2">
            @csrf
            @if($method !== 'POST')
                @method($method)
            @endif

            @if(! $evaluation)
                <input type="hidden" name="student_id" value="{{ $student->id }}">
                <input type="hidden" name="month" value="{{ $month }}">
            @endif
            <input type="hidden" name="week" value="{{ $week }}">

            <div class="grid grid-cols-2 gap-2">
                <input type="number" step="0.01" min="0" name="amount" required value="{{ old('amount', $evaluation?->amount) }}"
                       placeholder="المقدار" class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm">
                <select name="result" required class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm">
                    @foreach($results as $option)
                        <option value="{{ $option->value }}" @selected(old('result', $result?->value) === $option->value)>{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>

            <input type="text" name="recited_portion" maxlength="255" value="{{ old('recited_portion', $evaluation?->recited_portion) }}"
                   placeholder="المقروء" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">

            <div class="grid grid-cols-2 gap-2">
                <input type="date" name="week_start" value="{{ old('week_start', $evaluation?->week_start?->format('Y-m-d') ?? $weekStart) }}"
                       class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm" aria-label="بداية الأسبوع">
                <input type="date" name="week_end" value="{{ old('week_end', $evaluation?->week_end?->format('Y-m-d') ?? $weekEnd) }}"
                       class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm" aria-label="نهاية الأسبوع">
            </div>

            @if($teachers)
                <select name="evaluated_by" class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm">
                    <option value="">— المشرف —</option>
                    @foreach($teachers as $teacherOption)
                        <option value="{{ $teacherOption->id }}" @selected(old('evaluated_by', $evaluation?->evaluated_by ?? $evaluatedBy) === $teacherOption->id)>{{ $teacherOption->name }}</option>
                    @endforeach
                </select>
            @endif

            <textarea name="notes" rows="2" maxlength="2000" placeholder="ملاحظات"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('notes', $evaluation?->notes) }}</textarea>

            <button type="submit" class="w-full bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">
                {{ $evaluation ? 'حفظ التعديل' : 'تسجيل الأسبوع' }}
            </button>
        </form>
    </details>

    @if($canDelete && $evaluation && $deleteAction)
        <form method="POST" action="{{ $deleteAction }}" class="mt-2" onsubmit="return confirm('حذف تقييم هذا الأسبوع؟')">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-xs text-red-500 hover:text-red-700 font-bold">حذف التقييم</button>
        </form>
    @endif
</div>
