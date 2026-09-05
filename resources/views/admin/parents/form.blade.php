@extends('layouts.app')

@section('title', $guardian ? 'تعديل ولي أمر' : 'إضافة ولي أمر')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <form method="POST" action="{{ $guardian ? route('admin.parents.update', $guardian) : route('admin.parents.store') }}" class="lg:col-span-3 space-y-6">
        @csrf
        @if($guardian)
            @method('PATCH')
        @endif

        <div class="bg-white rounded-2xl shadow p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">البيانات الأساسية</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الاسم *</label>
                    <input type="text" name="name" required value="{{ old('name', $guardian?->name) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">رقم الجوال</label>
                    <input type="text" name="phone" value="{{ old('phone', $guardian?->phone) }}" dir="ltr"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الحالة</label>
                    <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="active" @selected(old('status', $guardian?->status ?? 'active') === 'active')>نشط</option>
                        <option value="inactive" @selected(old('status', $guardian?->status) === 'inactive')>غير نشط</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-1">حساب بوابة ولي الأمر</h2>
            <p class="text-xs text-gray-400 mb-4">بإنشاء الحساب يستطيع ولي الأمر تسجيل الدخول ومتابعة أبنائه.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
                    <input type="email" name="email" value="{{ old('email', $guardian?->user?->email ?? $guardian?->email) }}" dir="ltr"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        كلمة المرور {{ $guardian ? '(اتركها فارغة لعدم التغيير)' : '*' }}
                    </label>
                    <input type="password" name="password" {{ $guardian ? '' : 'required' }}
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">ربط الأبناء</h2>
            @php
                $linked = old('student_ids', $guardian ? $guardian->students->pluck('id')->all() : []);
                $relationships = old('relationships', []);
            @endphp
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-500 text-right">
                            <th class="px-3 py-2 font-medium">ربط</th>
                            <th class="px-3 py-2 font-medium">الطالب</th>
                            <th class="px-3 py-2 font-medium">الصف</th>
                            <th class="px-3 py-2 font-medium">صلة القرابة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($students->groupBy('classroom.name') as $className => $group)
                            <tr class="bg-emerald-50/50">
                                <td colspan="4" class="px-3 py-1.5 text-xs font-bold text-emerald-800">{{ $className }}</td>
                            </tr>
                            @foreach($group as $student)
                                @php $checked = in_array($student->id, $linked); @endphp
                                <tr class="border-t border-gray-100">
                                    <td class="px-3 py-2">
                                        <input type="checkbox" name="student_ids[]" value="{{ $student->id }}" {{ $checked ? 'checked' : '' }}
                                               class="student-check rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                    </td>
                                    <td class="px-3 py-2 text-gray-800">{{ $student->name }}</td>
                                    <td class="px-3 py-2 text-gray-500">{{ $className }}</td>
                                    <td class="px-3 py-2">
                                        <select name="relationships[{{ $student->id }}]" class="relationship-select border border-gray-300 rounded-lg px-2 py-1 text-xs {{ $checked ? '' : 'opacity-40' }}" {{ $checked ? '' : 'disabled' }}>
                                            <option value="father" @selected(($relationships[$student->id] ?? '') === 'father')>أب</option>
                                            <option value="mother" @selected(($relationships[$student->id] ?? '') === 'mother')>أم</option>
                                            <option value="guardian" @selected(($relationships[$student->id] ?? '') === 'guardian' || ! isset($relationships[$student->id]))>ولي أمر</option>
                                            <option value="other" @selected(($relationships[$student->id] ?? '') === 'other')>أخرى</option>
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-6 py-2.5 rounded-lg">
                {{ $guardian ? 'حفظ التعديلات' : 'إضافة ولي الأمر' }}
            </button>
            <a href="{{ route('admin.parents.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold px-6 py-2.5 rounded-lg">إلغاء</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.student-check').forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                var row = checkbox.closest('tr');
                var select = row.querySelector('.relationship-select');
                select.disabled = !checkbox.checked;
                select.classList.toggle('opacity-40', !checkbox.checked);
            });
        });
    });
</script>
@endpush
