@props([
    'fields' => collect(),   // CustomField definitions
    'values' => [],
    'prefix' => 'custom_fields',
])

@foreach($fields as $field)
    @php
        $fieldValue = $values[$field->field_key] ?? null;
        $isOptionsType = $field->field_type->hasOptions();
        $required = $field->required ? 'required' : '';
    @endphp
    @if($field->is_active)
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                {{ $field->name }}
                @if($field->required)<span class="text-red-500">*</span>@endif
                <span class="text-xs text-gray-400 font-normal">({{ $field->field_type->label() }})</span>
            </label>

            @if($field->field_type === \App\Enums\CustomFieldType::Textarea)
                <textarea name="{{ $prefix }}[{{ $field->field_key }}]" rows="3" {{ $required }}
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">{{ $fieldValue }}</textarea>
            @elseif($field->field_type === \App\Enums\CustomFieldType::Number)
                <input type="number" step="any" name="{{ $prefix }}[{{ $field->field_key }}]" value="{{ $fieldValue }}" {{ $required }}
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            @elseif($field->field_type === \App\Enums\CustomFieldType::Date)
                <input type="date" name="{{ $prefix }}[{{ $field->field_key }}]" value="{{ $fieldValue }}" {{ $required }}
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            @elseif($field->field_type === \App\Enums\CustomFieldType::Boolean)
                <select name="{{ $prefix }}[{{ $field->field_key }}]" {{ $required }} class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="0" @selected($fieldValue === false || $fieldValue === null || $fieldValue === '')>لا</option>
                    <option value="1" @selected($fieldValue === true || $fieldValue === '1')>نعم</option>
                </select>
            @elseif($isOptionsType)
                @if($field->field_type === \App\Enums\CustomFieldType::Multiselect)
                    <select name="{{ $prefix }}[{{ $field->field_key }}][]" multiple {{ $required }}
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        @foreach($field->options ?? [] as $option)
                            <option value="{{ $option }}" @selected(is_array($fieldValue) && in_array($option, $fieldValue, true))>{{ $option }}</option>
                        @endforeach
                    </select>
                @else
                    <select name="{{ $prefix }}[{{ $field->field_key }}]" {{ $required }}
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <option value="">اختر...</option>
                        @foreach($field->options ?? [] as $option)
                            <option value="{{ $option }}" @selected((string) $fieldValue === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                @endif
            @else
                <input type="text" name="{{ $prefix }}[{{ $field->field_key }}]" value="{{ is_string($fieldValue) ? $fieldValue : '' }}" {{ $required }}
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            @endif
        </div>
    @endif
@endforeach
