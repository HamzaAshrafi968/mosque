@extends('layouts.app')

@section('title', 'الحقول المخصصة')

@section('content')
<div class="mb-4 flex gap-2">
    @foreach(\App\Enums\CustomFieldEntityType::cases() as $type)
        <a href="{{ route('admin.custom-fields.index', ['entity_type' => $type->value]) }}"
           @class([
               'px-4 py-2 rounded-lg text-sm font-bold border',
               'bg-emerald-700 text-white border-emerald-700' => $entityType === $type,
               'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' => $entityType !== $type,
           ])>
            {{ $type->label() }}
        </a>
    @endforeach
</div>

<div class="bg-white rounded-xl shadow overflow-hidden mb-6">
    <div class="px-4 py-3 bg-gray-50 border-b">
        <span class="font-bold text-gray-800">إضافة حقل مخصص لـ{{ $entityType->label() }}</span>
    </div>
    <form method="POST" action="{{ route('admin.custom-fields.store') }}" class="p-4 grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
        @csrf
        <input type="hidden" name="entity_type" value="{{ $entityType->value }}">
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">اسم الحقل <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name') }}" required placeholder="مثال: مهنة ولي الأمر"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">المفتاح (اختياري)</label>
            <input type="text" name="field_key" value="{{ old('field_key') }}" placeholder="father_occupation" dir="ltr"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-left">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">النوع <span class="text-red-500">*</span></label>
            <select name="field_type" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                @foreach($fieldTypes as $type)
                    <option value="{{ $type->value }}" @selected(old('field_type') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الخيارات (لقوائم الاختيار)</label>
            <input type="text" name="options" value="{{ old('options') }}" placeholder="افصل بين الخيارات بفاصلة"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ترتيب</label>
            <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <div class="flex items-end pb-2 gap-4">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="required" value="1" @checked(old('required')) class="accent-emerald-600">
                إلزامي
            </label>
        </div>
        <div class="md:col-span-6">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">إنشاء الحقل</button>
        </div>
    </form>
</div>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-gray-600">
                    <th class="px-4 py-3 text-right whitespace-nowrap">الحقل</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">المفتاح</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">النوع</th>
                    <th class="px-4 py-3 text-center whitespace-nowrap">إلزامي</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">الخيارات</th>
                    <th class="px-4 py-3 text-center whitespace-nowrap">مفعل</th>
                    <th class="px-4 py-3 text-center whitespace-nowrap">إجراء</th>
                </tr>
            </thead>
            <tbody>
                @forelse($fields as $field)
                    <tr id="field-row-{{ $field->id }}">
                        <td class="px-4 py-3 border-t font-bold whitespace-nowrap">{{ $field->name }}</td>
                        <td class="px-4 py-3 border-t font-mono text-gray-500 whitespace-nowrap" dir="ltr">{{ $field->field_key }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $field->field_type->label() }}</td>
                        <td class="px-4 py-3 border-t text-center">{{ $field->required ? 'نعم' : 'لا' }}</td>
                        <td class="px-4 py-3 border-t text-gray-500 text-xs">{{ is_array($field->options) ? implode('، ', $field->options) : '—' }}</td>
                        <td class="px-4 py-3 border-t text-center">
                            <span @class(['px-2 py-0.5 rounded-full text-xs font-bold', 'bg-green-100 text-green-800' => $field->is_active, 'bg-gray-100 text-gray-500' => !$field->is_active])>
                                {{ $field->is_active ? 'مفعل' : 'معطل' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 border-t text-center whitespace-nowrap">
                            <button type="button" data-toggle-edit="{{ $field->id }}" class="text-emerald-700 hover:underline text-xs ml-2">تعديل</button>
                            <form method="POST" action="{{ route('admin.custom-fields.destroy', $field) }}" onsubmit="return confirm('سيتم حذف الحقل وقيمه. متأكد؟')" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline text-xs">حذف</button>
                            </form>
                        </td>
                    </tr>
                    <tr id="field-edit-{{ $field->id }}" class="hidden">
                        <td colspan="7" class="px-4 py-3 border-t bg-gray-50">
                            <form method="POST" action="{{ route('admin.custom-fields.update', $field) }}" class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="entity_type" value="{{ $field->entity_type->value }}">
                                <input type="hidden" name="field_type" value="{{ $field->field_type->value }}">
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">اسم الحقل</label>
                                    <input type="text" name="name" value="{{ $field->name }}" required
                                           class="w-full border border-gray-300 rounded-lg px-3 py-2">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">الخيارات</label>
                                    <input type="text" name="options" value="{{ is_array($field->options) ? implode('، ', $field->options) : '' }}"
                                           class="w-full border border-gray-300 rounded-lg px-3 py-2">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">ترتيب</label>
                                    <input type="number" name="sort_order" value="{{ $field->sort_order }}" min="0"
                                           class="w-full border border-gray-300 rounded-lg px-3 py-2">
                                </div>
                                <div class="flex gap-4 pb-2">
                                    <label class="inline-flex items-center gap-1 text-sm text-gray-700">
                                        <input type="checkbox" name="required" value="1" @checked($field->required) class="accent-emerald-600"> إلزامي
                                    </label>
                                    <label class="inline-flex items-center gap-1 text-sm text-gray-700">
                                        <input type="checkbox" name="is_active" value="1" @checked($field->is_active) class="accent-emerald-600"> مفعل
                                    </label>
                                </div>
                                <div class="flex gap-2 items-end pb-1">
                                    <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-4 py-2 rounded-lg">حفظ</button>
                                    <button type="button" data-cancel-edit="{{ $field->id }}" class="text-gray-500 text-xs underline">إلغاء</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                            لا توجد حقول مخصصة — أضف حقولاً تظهر تلقائياً في نماذج {{ $entityType->label() }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const createOptions = document.querySelector('form[action="{{ route('admin.custom-fields.store') }}"] input[name="options"]');
    const createType = document.querySelector('form[action="{{ route('admin.custom-fields.store') }}"] select[name="field_type"]');
    if (createOptions && createType) {
        const sync = () => {
            createOptions.disabled = !['select', 'multiselect'].includes(createType.value);
            createOptions.placeholder = createOptions.disabled ? 'يُستخدم للحقول النصية فقط' : 'افصل بين الخيارات بفاصلة';
        };
        createType.addEventListener('change', sync);
        sync();
    }

    document.querySelectorAll('[data-toggle-edit]').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.toggleEdit;
            document.getElementById('field-row-' + id).classList.add('hidden');
            document.getElementById('field-edit-' + id).classList.remove('hidden');
        });
    });
    document.querySelectorAll('[data-cancel-edit]').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.cancelEdit;
            document.getElementById('field-edit-' + id).classList.add('hidden');
            document.getElementById('field-row-' + id).classList.remove('hidden');
        });
    });
</script>
@endsection
