@extends('layouts.app')

@section('title', $program ? 'تعديل برنامج' : 'برنامج جديد')

@section('content')
@php
    $isEdit = (bool) $program;
@endphp

<div class="max-w-6xl mx-auto space-y-6" dir="rtl">
    <div>
        <h2 class="text-2xl font-extrabold text-gray-800">{{ $isEdit ? 'تعديل البرنامج' : 'برنامج جديد' }}</h2>
        <p class="text-sm text-gray-500 mt-1">حدّد بيانات البرنامج، ثم فتراته (الفترة الأولى/الثانية...) وخصائصه الخاصة</p>
    </div>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm space-y-1">
            @foreach($errors->all() as $error)
                <div>• {{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ $isEdit ? route('admin.programs.update', $program) : route('admin.programs.store') }}" class="space-y-6">
        @csrf
        @if($isEdit)
            @method('PATCH')
        @endif

        {{-- بيانات البرنامج --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <h3 class="font-extrabold text-gray-800 mb-4">بيانات البرنامج</h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-1">اسم البرنامج <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required maxlength="255" value="{{ old('name', $program?->name) }}"
                           placeholder="مثال: برنامج التحفيظ الصيفي"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">النوع <span class="text-red-500">*</span></label>
                    <select name="type" id="program-type" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        @foreach($programTypes as $programType)
                            <option value="{{ $programType->value }}" data-color="{{ $programType->color() }}"
                                @selected(old('type', $program?->type?->value ?? 'custom') === $programType->value)>
                                {{ $programType->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">المفتاح (code)</label>
                    <input type="text" name="code" maxlength="100" value="{{ old('code', $program?->code) }}"
                           placeholder="يُولّد تلقائياً إذا تُرك فارغاً"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 font-mono text-sm">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">اللون</label>
                    <div class="flex items-center gap-2">
                        <input type="color" name="color" value="{{ old('color', $program?->color ?? '#047857') }}"
                               class="h-10 w-16 border border-gray-300 rounded-lg p-1">
                        <button type="button" id="use-type-color" class="text-xs text-emerald-700 hover:underline">لون النوع الافتراضي</button>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">الترتيب</label>
                    <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $program?->sort_order ?? 0) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>

                <div class="md:col-span-3">
                    <label class="block text-sm font-bold text-gray-700 mb-1">الوصف</label>
                    <textarea name="description" rows="2" maxlength="5000" class="w-full border border-gray-300 rounded-lg px-3 py-2">{{ old('description', $program?->description) }}</textarea>
                </div>

                <div class="md:col-span-3">
                    <label class="inline-flex items-center gap-2 text-sm font-bold text-gray-700">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $program?->is_active ?? true)) class="rounded">
                        البرنامج مفعّل
                    </label>
                </div>
            </div>
        </div>

        {{-- الفترات --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between mb-2">
                <div>
                    <h3 class="font-extrabold text-gray-800">الفترات</h3>
                    <p class="text-xs text-gray-500 mt-0.5">لكل برنامج فتراته الخاصة — يمكن الاكتفاء بالفترة الأولى أو إضافة أكثر. تظهر أوقات الفترة تلقائياً عند إضافة الحصة</p>
                </div>
                <button type="button" onclick="addPeriodRow()" class="bg-gray-800 hover:bg-gray-900 text-white text-xs font-bold px-3 py-2 rounded-lg">+ إضافة فترة</button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600 text-xs">
                            <th class="px-2 py-2 text-right">اسم الفترة</th>
                            <th class="px-2 py-2 text-right">من</th>
                            <th class="px-2 py-2 text-right">إلى</th>
                            <th class="px-2 py-2 text-right">الترتيب</th>
                            <th class="px-2 py-2 text-center">مفعّلة</th>
                            <th class="px-2 py-2"></th>
                        </tr>
                    </thead>
                    <tbody id="periods-body">
                        @foreach(array_values($periodRows) as $index => $row)
                            <tr class="border-t period-row">
                                <td class="px-2 py-2">
                                    <input type="hidden" name="periods[{{ $index }}][id]" value="{{ $row['id'] ?? '' }}">
                                    <input type="text" name="periods[{{ $index }}][name]" value="{{ $row['name'] ?? '' }}" maxlength="255"
                                           placeholder="الفترة الأولى" class="w-full border border-gray-300 rounded-lg px-2 py-1.5">
                                </td>
                                <td class="px-2 py-2">
                                    <input type="time" name="periods[{{ $index }}][starts_at]" value="{{ !empty($row['starts_at']) ? substr($row['starts_at'], 0, 5) : '' }}"
                                           class="border border-gray-300 rounded-lg px-2 py-1.5">
                                </td>
                                <td class="px-2 py-2">
                                    <input type="time" name="periods[{{ $index }}][ends_at]" value="{{ !empty($row['ends_at']) ? substr($row['ends_at'], 0, 5) : '' }}"
                                           class="border border-gray-300 rounded-lg px-2 py-1.5">
                                </td>
                                <td class="px-2 py-2">
                                    <input type="number" name="periods[{{ $index }}][sort_order]" min="0" value="{{ $row['sort_order'] ?? $index }}"
                                           class="w-20 border border-gray-300 rounded-lg px-2 py-1.5">
                                </td>
                                <td class="px-2 py-2 text-center">
                                    <input type="hidden" name="periods[{{ $index }}][is_active]" value="0">
                                    <input type="checkbox" name="periods[{{ $index }}][is_active]" value="1" @checked(!empty($row['is_active'])) class="rounded">
                                </td>
                                <td class="px-2 py-2 text-center">
                                    <button type="button" onclick="this.closest('tr').remove()" class="text-red-600 hover:underline text-xs">حذف</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- الخصائص --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between mb-2">
                <div>
                    <h3 class="font-extrabold text-gray-800">الخصائص المخصصة</h3>
                    <p class="text-xs text-gray-500 mt-0.5">أضف حقولاً خاصة بهذا البرنامج (مثال: عدد الأجزاء، المستوى، مدة البرنامج)</p>
                </div>
                <button type="button" onclick="addAttributeRow()" class="bg-gray-800 hover:bg-gray-900 text-white text-xs font-bold px-3 py-2 rounded-lg">+ إضافة خصيصة</button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600 text-xs">
                            <th class="px-2 py-2 text-right">الاسم</th>
                            <th class="px-2 py-2 text-right">المفتاح</th>
                            <th class="px-2 py-2 text-right">النوع</th>
                            <th class="px-2 py-2 text-right">القيمة</th>
                            <th class="px-2 py-2 text-right">الخيارات</th>
                            <th class="px-2 py-2 text-center">مطلوبة</th>
                            <th class="px-2 py-2 text-right">الترتيب</th>
                            <th class="px-2 py-2 text-center">مفعّلة</th>
                            <th class="px-2 py-2"></th>
                        </tr>
                    </thead>
                    <tbody id="attributes-body">
                        @foreach(array_values($attributeRows) as $index => $row)
                            @php
                                $rowValue = $row['value'] ?? '';
                                $rowValueAttr = is_array($rowValue)
                                    ? json_encode(array_values($rowValue), JSON_UNESCAPED_UNICODE)
                                    : (string) $rowValue;
                                $rowSource = $row['options_source'] ?? 'manual';
                                $rowConfig = $row['options_config'] ?? [];
                            @endphp
                            <tr class="border-t attribute-row" data-value="{{ $rowValueAttr }}">
                                <td class="px-2 py-2">
                                    <input type="hidden" name="attributes[{{ $index }}][id]" value="{{ $row['id'] ?? '' }}">
                                    <input type="text" name="attributes[{{ $index }}][name]" value="{{ $row['name'] ?? '' }}" maxlength="255"
                                           class="w-full border border-gray-300 rounded-lg px-2 py-1.5">
                                </td>
                                <td class="px-2 py-2">
                                    <input type="text" name="attributes[{{ $index }}][field_key]" value="{{ $row['field_key'] ?? '' }}" maxlength="100"
                                           placeholder="auto" class="w-24 border border-gray-300 rounded-lg px-2 py-1.5 font-mono text-xs">
                                </td>
                                <td class="px-2 py-2">
                                    <select name="attributes[{{ $index }}][field_type]" onchange="refreshAttributeRow(this.closest('tr'))"
                                            class="attr-type border border-gray-300 rounded-lg px-2 py-1.5 text-xs">
                                        @foreach($fieldTypes as $fieldType)
                                            <option value="{{ $fieldType->value }}" @selected(($row['field_type'] ?? 'text') === $fieldType->value)>{{ $fieldType->label() }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-2 py-2 attr-value-cell min-w-40"></td>
                                <td class="px-2 py-2 attr-options-cell min-w-56">
                                    <select name="attributes[{{ $index }}][options_source]" class="attr-options-source w-full border border-gray-300 rounded-lg px-2 py-1 text-xs mb-1">
                                        @foreach($optionSources as $source)
                                            <option value="{{ $source->value }}" @selected($rowSource === $source->value)>{{ $source->label() }}</option>
                                        @endforeach
                                    </select>

                                    <div class="attr-manual-options">
                                        <textarea name="attributes[{{ $index }}][options]" rows="1" placeholder="خيار في كل سطر"
                                                  class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs">{{ $row['options'] ?? '' }}</textarea>
                                    </div>

                                    <div class="attr-student-options hidden space-y-1">
                                        <div class="grid grid-cols-2 gap-1">
                                            <select name="attributes[{{ $index }}][options_config][student_status]" class="w-full border border-gray-300 rounded-lg px-2 py-1 text-xs">
                                                <option value="active" @selected(($rowConfig['student_status'] ?? 'active') === 'active')>الطلاب النشطون</option>
                                                <option value="all" @selected(($rowConfig['student_status'] ?? 'active') === 'all')>كل الحالات</option>
                                            </select>
                                            <select name="attributes[{{ $index }}][options_config][gender]" class="w-full border border-gray-300 rounded-lg px-2 py-1 text-xs">
                                                <option value="all" @selected(($rowConfig['gender'] ?? 'all') === 'all')>كل الجنسين</option>
                                                <option value="male" @selected(($rowConfig['gender'] ?? 'all') === 'male')>ذكور</option>
                                                <option value="female" @selected(($rowConfig['gender'] ?? 'all') === 'female')>إناث</option>
                                            </select>
                                        </div>
                                        <div class="grid grid-cols-2 gap-1">
                                            <input type="number" min="0" max="120" name="attributes[{{ $index }}][options_config][age_from]"
                                                   value="{{ $rowConfig['age_from'] ?? '' }}" placeholder="العمر من"
                                                   class="w-full border border-gray-300 rounded-lg px-2 py-1 text-xs">
                                            <input type="number" min="0" max="120" name="attributes[{{ $index }}][options_config][age_to]"
                                                   value="{{ $rowConfig['age_to'] ?? '' }}" placeholder="العمر إلى"
                                                   class="w-full border border-gray-300 rounded-lg px-2 py-1 text-xs">
                                        </div>
                                        <div class="grid grid-cols-2 gap-1">
                                            <input type="number" step="0.5" min="0" max="30" name="attributes[{{ $index }}][options_config][juz_from]"
                                                   value="{{ $rowConfig['juz_from'] ?? '' }}" placeholder="الأجزاء من"
                                                   class="w-full border border-gray-300 rounded-lg px-2 py-1 text-xs">
                                            <input type="number" step="0.5" min="0" max="30" name="attributes[{{ $index }}][options_config][juz_to]"
                                                   value="{{ $rowConfig['juz_to'] ?? '' }}" placeholder="الأجزاء إلى"
                                                   class="w-full border border-gray-300 rounded-lg px-2 py-1 text-xs">
                                        </div>
                                        <select name="attributes[{{ $index }}][options_config][classroom_id]" class="w-full border border-gray-300 rounded-lg px-2 py-1 text-xs">
                                            <option value="">كل الصفوف</option>
                                            @foreach($classrooms as $classroom)
                                                <option value="{{ $classroom->id }}" @selected(($rowConfig['classroom_id'] ?? '') === $classroom->id)>{{ $classroom->name }}</option>
                                            @endforeach
                                        </select>
                                        <select name="attributes[{{ $index }}][options_config][label_mode]" class="w-full border border-gray-300 rounded-lg px-2 py-1 text-xs">
                                            <option value="name" @selected(($rowConfig['label_mode'] ?? 'name') === 'name')>الاسم فقط</option>
                                            <option value="name_age" @selected(($rowConfig['label_mode'] ?? 'name') === 'name_age')>الاسم + العمر</option>
                                            <option value="name_juz" @selected(($rowConfig['label_mode'] ?? 'name') === 'name_juz')>الاسم + الأجزاء</option>
                                            <option value="name_age_juz" @selected(($rowConfig['label_mode'] ?? 'name') === 'name_age_juz')>الاسم + العمر + الأجزاء</option>
                                        </select>
                                    </div>
                                </td>
                                <td class="px-2 py-2 text-center">
                                    <input type="hidden" name="attributes[{{ $index }}][required]" value="0">
                                    <input type="checkbox" name="attributes[{{ $index }}][required]" value="1" @checked(!empty($row['required'])) class="rounded">
                                </td>
                                <td class="px-2 py-2">
                                    <input type="number" name="attributes[{{ $index }}][sort_order]" min="0" value="{{ $row['sort_order'] ?? $index }}"
                                           class="w-20 border border-gray-300 rounded-lg px-2 py-1.5">
                                </td>
                                <td class="px-2 py-2 text-center">
                                    <input type="hidden" name="attributes[{{ $index }}][is_active]" value="0">
                                    <input type="checkbox" name="attributes[{{ $index }}][is_active]" value="1" @checked($row['is_active'] ?? true) class="rounded">
                                </td>
                                <td class="px-2 py-2 text-center">
                                    <button type="button" onclick="this.closest('tr').remove()" class="text-red-600 hover:underline text-xs">حذف</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-8 py-2.5 rounded-xl">
                {{ $isEdit ? 'حفظ التعديلات' : 'إنشاء البرنامج' }}
            </button>
            <a href="{{ route('admin.programs.index') }}" class="text-gray-500 text-sm hover:underline">إلغاء</a>
        </div>
    </form>
</div>

<template id="period-template">
    <tr class="border-t period-row">
        <td class="px-2 py-2">
            <input type="hidden" name="periods[__INDEX__][id]" value="">
            <input type="text" name="periods[__INDEX__][name]" maxlength="255" placeholder="الفترة الأولى" class="w-full border border-gray-300 rounded-lg px-2 py-1.5">
        </td>
        <td class="px-2 py-2"><input type="time" name="periods[__INDEX__][starts_at]" class="border border-gray-300 rounded-lg px-2 py-1.5"></td>
        <td class="px-2 py-2"><input type="time" name="periods[__INDEX__][ends_at]" class="border border-gray-300 rounded-lg px-2 py-1.5"></td>
        <td class="px-2 py-2"><input type="number" name="periods[__INDEX__][sort_order]" min="0" value="0" class="w-20 border border-gray-300 rounded-lg px-2 py-1.5"></td>
        <td class="px-2 py-2 text-center">
            <input type="hidden" name="periods[__INDEX__][is_active]" value="0">
            <input type="checkbox" name="periods[__INDEX__][is_active]" value="1" checked class="rounded">
        </td>
        <td class="px-2 py-2 text-center"><button type="button" onclick="this.closest('tr').remove()" class="text-red-600 hover:underline text-xs">حذف</button></td>
    </tr>
</template>

<template id="attribute-template">
    <tr class="border-t attribute-row" data-value="">
        <td class="px-2 py-2">
            <input type="hidden" name="attributes[__INDEX__][id]" value="">
            <input type="text" name="attributes[__INDEX__][name]" maxlength="255" class="w-full border border-gray-300 rounded-lg px-2 py-1.5">
        </td>
        <td class="px-2 py-2"><input type="text" name="attributes[__INDEX__][field_key]" maxlength="100" placeholder="auto" class="w-24 border border-gray-300 rounded-lg px-2 py-1.5 font-mono text-xs"></td>
        <td class="px-2 py-2">
            <select name="attributes[__INDEX__][field_type]" onchange="refreshAttributeRow(this.closest('tr'))" class="attr-type border border-gray-300 rounded-lg px-2 py-1.5 text-xs">
                @foreach($fieldTypes as $fieldType)
                    <option value="{{ $fieldType->value }}">{{ $fieldType->label() }}</option>
                @endforeach
            </select>
        </td>
        <td class="px-2 py-2 attr-value-cell min-w-40"></td>
        <td class="px-2 py-2 attr-options-cell min-w-56">
            <select name="attributes[__INDEX__][options_source]" class="attr-options-source w-full border border-gray-300 rounded-lg px-2 py-1 text-xs mb-1">
                @foreach($optionSources as $source)
                    <option value="{{ $source->value }}">{{ $source->label() }}</option>
                @endforeach
            </select>

            <div class="attr-manual-options">
                <textarea name="attributes[__INDEX__][options]" rows="1" placeholder="خيار في كل سطر" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs"></textarea>
            </div>

            <div class="attr-student-options hidden space-y-1">
                <div class="grid grid-cols-2 gap-1">
                    <select name="attributes[__INDEX__][options_config][student_status]" class="w-full border border-gray-300 rounded-lg px-2 py-1 text-xs">
                        <option value="active">الطلاب النشطون</option>
                        <option value="all">كل الحالات</option>
                    </select>
                    <select name="attributes[__INDEX__][options_config][gender]" class="w-full border border-gray-300 rounded-lg px-2 py-1 text-xs">
                        <option value="all">كل الجنسين</option>
                        <option value="male">ذكور</option>
                        <option value="female">إناث</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-1">
                    <input type="number" min="0" max="120" name="attributes[__INDEX__][options_config][age_from]" placeholder="العمر من" class="w-full border border-gray-300 rounded-lg px-2 py-1 text-xs">
                    <input type="number" min="0" max="120" name="attributes[__INDEX__][options_config][age_to]" placeholder="العمر إلى" class="w-full border border-gray-300 rounded-lg px-2 py-1 text-xs">
                </div>
                <div class="grid grid-cols-2 gap-1">
                    <input type="number" step="0.5" min="0" max="30" name="attributes[__INDEX__][options_config][juz_from]" placeholder="الأجزاء من" class="w-full border border-gray-300 rounded-lg px-2 py-1 text-xs">
                    <input type="number" step="0.5" min="0" max="30" name="attributes[__INDEX__][options_config][juz_to]" placeholder="الأجزاء إلى" class="w-full border border-gray-300 rounded-lg px-2 py-1 text-xs">
                </div>
                <select name="attributes[__INDEX__][options_config][classroom_id]" class="w-full border border-gray-300 rounded-lg px-2 py-1 text-xs">
                    <option value="">كل الصفوف</option>
                    @foreach($classrooms as $classroom)
                        <option value="{{ $classroom->id }}">{{ $classroom->name }}</option>
                    @endforeach
                </select>
                <select name="attributes[__INDEX__][options_config][label_mode]" class="w-full border border-gray-300 rounded-lg px-2 py-1 text-xs">
                    <option value="name">الاسم فقط</option>
                    <option value="name_age">الاسم + العمر</option>
                    <option value="name_juz">الاسم + الأجزاء</option>
                    <option value="name_age_juz">الاسم + العمر + الأجزاء</option>
                </select>
            </div>
        </td>
        <td class="px-2 py-2 text-center">
            <input type="hidden" name="attributes[__INDEX__][required]" value="0">
            <input type="checkbox" name="attributes[__INDEX__][required]" value="1" class="rounded">
        </td>
        <td class="px-2 py-2"><input type="number" name="attributes[__INDEX__][sort_order]" min="0" value="0" class="w-20 border border-gray-300 rounded-lg px-2 py-1.5"></td>
        <td class="px-2 py-2 text-center">
            <input type="hidden" name="attributes[__INDEX__][is_active]" value="0">
            <input type="checkbox" name="attributes[__INDEX__][is_active]" value="1" checked class="rounded">
        </td>
        <td class="px-2 py-2 text-center"><button type="button" onclick="this.closest('tr').remove()" class="text-red-600 hover:underline text-xs">حذف</button></td>
    </tr>
</template>

<script>
    const studentPool = @json($studentOptions);

    function nextIndex(selector) {
        let max = -1;
        document.querySelectorAll(selector).forEach(function (row) {
            row.querySelectorAll('input, select, textarea').forEach(function (field) {
                const match = (field.name || '').match(/\[(\d+)\]/);
                if (match) { max = Math.max(max, parseInt(match[1], 10)); }
            });
        });
        return max + 1;
    }

    function addPeriodRow() {
        const html = document.getElementById('period-template').innerHTML.replaceAll('__INDEX__', nextIndex('#periods-body .period-row'));
        document.getElementById('periods-body').insertAdjacentHTML('beforeend', html);
    }

    function escapeAttribute(value) {
        return String(value ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    function parseSelected(value) {
        if (value === null || value === undefined || value === '') { return []; }
        const text = String(value).trim();
        if (text.startsWith('[')) {
            try { return JSON.parse(text).map(String); } catch (error) { /* fall through */ }
        }
        return text.split(/[,،]+/).map(function (item) { return item.trim(); }).filter(Boolean);
    }

    function formatJuz(juz) {
        return String(parseFloat(juz));
    }

    function studentConfig(row) {
        function value(name) {
            const field = row.querySelector('[name*="[options_config][' + name + ']"]');
            return field ? field.value.trim() : '';
        }
        return {
            student_status: value('student_status') || 'active',
            gender: value('gender') || 'all',
            age_from: value('age_from'),
            age_to: value('age_to'),
            juz_from: value('juz_from'),
            juz_to: value('juz_to'),
            classroom_id: value('classroom_id'),
            label_mode: value('label_mode') || 'name',
        };
    }

    function filterStudents(row) {
        const config = studentConfig(row);
        return studentPool.filter(function (student) {
            if (config.student_status === 'active' && student.status !== 'active') { return false; }
            if (config.gender !== 'all' && student.gender !== config.gender) { return false; }
            if (config.classroom_id && student.classroom_id !== config.classroom_id) { return false; }
            if (config.age_from !== '' && (student.age === null || student.age < parseInt(config.age_from, 10))) { return false; }
            if (config.age_to !== '' && (student.age === null || student.age > parseInt(config.age_to, 10))) { return false; }
            if (config.juz_from !== '' && (student.juz === null || student.juz < parseFloat(config.juz_from))) { return false; }
            if (config.juz_to !== '' && (student.juz === null || student.juz > parseFloat(config.juz_to))) { return false; }
            return true;
        });
    }

    function studentLabel(student, mode) {
        const parts = [];
        if (mode.indexOf('age') !== -1 && student.age !== null) { parts.push(student.age + ' سنة'); }
        if (mode.indexOf('juz') !== -1 && student.juz !== null) { parts.push(formatJuz(student.juz) + ' جزء'); }
        return parts.length ? student.name + ' — ' + parts.join(' — ') : student.name;
    }

    function collectAttributeOptions(row) {
        const source = row.querySelector('.attr-options-source')?.value || 'manual';
        if (source === 'students') {
            const config = studentConfig(row);
            return filterStudents(row).map(function (student) { return studentLabel(student, config.label_mode); });
        }
        const textarea = row.querySelector('.attr-manual-options textarea');
        return (textarea?.value || '').split(/[\r\n,]+/).map(function (item) { return item.trim(); }).filter(Boolean);
    }

    function currentSelection(row) {
        const select = row.querySelector('.attr-value-cell select');
        if (select) {
            return Array.from(select.selectedOptions || []).map(function (option) { return option.value; }).filter(function (value) { return value !== ''; });
        }
        return parseSelected(row.dataset.value || '');
    }

    function attributeValueHtml(index, type, selected, options) {
        const name = 'attributes[' + index + '][value]';
        const classes = 'w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm';
        selected = (selected || []).map(String);

        const merged = options.slice();
        selected.forEach(function (value) { if (merged.indexOf(value) === -1) { merged.push(value); } });

        if (type === 'select' || type === 'multiselect') {
            const multiple = type === 'multiselect';
            let html = '<select name="' + name + (multiple ? '[]' : '') + '" class="' + classes + '"' + (multiple ? ' multiple size="4"' : '') + '>';
            if (!multiple) {
                html += '<option value="">—</option>';
            }
            if (merged.length === 0) {
                html += '<option value="" disabled>لا توجد خيارات — أضفها من خانة الخيارات</option>';
            }
            merged.forEach(function (option) {
                html += '<option value="' + escapeAttribute(option) + '"' + (selected.indexOf(option) !== -1 ? ' selected' : '') + '>' + escapeAttribute(option) + '</option>';
            });
            html += '</select>';
            return html;
        }

        if (type === 'boolean') {
            const current = String(selected[0] ?? '');
            const checked = current === '1' ? '1' : (current === '0' ? '0' : '');
            return '<select name="' + name + '" class="' + classes + '">'
                + '<option value="" ' + (checked === '' ? 'selected' : '') + '>—</option>'
                + '<option value="1" ' + (checked === '1' ? 'selected' : '') + '>نعم</option>'
                + '<option value="0" ' + (checked === '0' ? 'selected' : '') + '>لا</option>'
                + '</select>';
        }

        const value = selected.length ? selected.join(', ') : '';
        if (type === 'number') {
            return '<input type="number" step="any" name="' + name + '" value="' + escapeAttribute(value) + '" class="' + classes + '">';
        }

        if (type === 'date') {
            return '<input type="date" name="' + name + '" value="' + escapeAttribute(value) + '" class="' + classes + '">';
        }

        if (type === 'textarea') {
            return '<textarea name="' + name + '" rows="1" class="' + classes + '">' + escapeAttribute(value) + '</textarea>';
        }

        return '<input type="text" name="' + name + '" value="' + escapeAttribute(value) + '" class="' + classes + '">';
    }

    function refreshAttributeRow(row) {
        const typeField = row.querySelector('.attr-type');
        const index = (typeField.name.match(/\[(\d+)\]/) || [])[1];
        const type = typeField.value;
        const selected = currentSelection(row);
        const options = collectAttributeOptions(row);

        row.querySelector('.attr-value-cell').innerHTML = attributeValueHtml(index, type, selected, options);
        row.querySelector('.attr-options-cell').style.display = (type === 'select' || type === 'multiselect') ? '' : 'none';

        const source = row.querySelector('.attr-options-source')?.value || 'manual';
        const manual = row.querySelector('.attr-manual-options');
        const students = row.querySelector('.attr-student-options');
        if (manual) { manual.style.display = source === 'manual' ? '' : 'none'; }
        if (students) { students.style.display = source === 'students' ? '' : 'none'; }
    }

    function addAttributeRow() {
        const html = document.getElementById('attribute-template').innerHTML.replaceAll('__INDEX__', nextIndex('#attributes-body .attribute-row'));
        document.getElementById('attributes-body').insertAdjacentHTML('beforeend', html);
        refreshAttributeRow(document.querySelector('#attributes-body .attribute-row:last-child'));
    }

    function onAttributeFieldChanged(event) {
        const row = event.target.closest('.attribute-row');
        if (!row) { return; }
        if (event.target.classList.contains('attr-type')) {
            refreshAttributeRow(row);
            return;
        }
        if (event.target.matches('.attr-options-source, .attr-manual-options textarea, .attr-student-options input, .attr-student-options select')) {
            refreshAttributeRow(row);
        }
    }

    document.querySelectorAll('#attributes-body .attribute-row').forEach(refreshAttributeRow);

    document.getElementById('attributes-body')?.addEventListener('input', onAttributeFieldChanged);
    document.getElementById('attributes-body')?.addEventListener('change', onAttributeFieldChanged);

    document.getElementById('use-type-color')?.addEventListener('click', function () {
        const select = document.getElementById('program-type');
        const color = select.options[select.selectedIndex]?.dataset.color;
        if (color) { document.querySelector('input[name="color"]').value = color; }
    });
</script>
@endsection
