@props([
    'types' => [],
    'oldQuestions' => null,
    'oldType' => null,
    'title' => 'الأسئلة',
    'hint' => null,
    'plain' => false,
])

@php
    $initialRows = is_array($oldQuestions) ? array_values($oldQuestions) : [];
    $selectedType = $oldType ?: ($types[0]->value ?? 'mcq');
    $typeOptions = collect($types)
        ->map(fn ($type) => ['value' => $type->value, 'label' => $type->label()])
        ->values()
        ->all();
@endphp

<div data-exam-question-builder
     data-old="{{ json_encode($initialRows, JSON_UNESCAPED_UNICODE) }}"
     data-old-type="{{ $selectedType }}"
     data-types="{{ json_encode($typeOptions, JSON_UNESCAPED_UNICODE) }}"
     class="{{ $plain ? 'border-t border-gray-200 pt-5 mt-2' : 'bg-white rounded-xl shadow p-5 mb-6' }}">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <h2 class="text-lg font-bold text-gray-800">{{ $title }}</h2>
        @if($hint)
            <span class="text-xs text-gray-400">{{ $hint }}</span>
        @endif
    </div>

    <div data-builder-sections class="space-y-2 mb-3"></div>

    <div class="flex flex-wrap gap-2 mb-4">
        <button type="button" data-builder-add-section
                class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-4 py-2 rounded-lg">
            إضافة قسم
        </button>
        <button type="button" data-builder-generate
                class="bg-gray-700 hover:bg-gray-800 text-white font-bold px-4 py-2 rounded-lg">
            توليد الصفوف
        </button>
    </div>

    <div data-builder-rows class="space-y-4"></div>

    <p class="mt-3 text-sm text-gray-600">
        مجموع علامات الأسئلة: <span data-builder-marks-sum class="font-bold">0</span>
        <span data-builder-marks-target class="text-xs text-gray-400"></span>
    </p>
</div>
