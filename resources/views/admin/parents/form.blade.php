@extends('layouts.app')

@section('title', $guardian ? 'تعديل ولي أمر' : 'إضافة ولي أمر')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <form method="POST" action="{{ $guardian ? route('admin.parents.update', $guardian) : route('admin.parents.store') }}" enctype="multipart/form-data" class="lg:col-span-3 space-y-6">
        @csrf
        @if($guardian)
            @method('PATCH')
        @endif

        <div class="bg-white rounded-2xl shadow p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">البيانات الأساسية</h2>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                <div class="lg:col-span-1">
                    <x-photo-input label="صورة ولي الأمر" :current-src="$guardian?->avatarUrl()" :current-name="$guardian?->name" />
                </div>
                <div class="lg:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4">
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
            <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                <h2 class="text-lg font-bold text-gray-800">الأبناء المرتبطون</h2>
                <span class="text-xs text-gray-400">تظهر هنا أبناء ولي الأمر فقط — استخدم البحث لإضافة ابن.</span>
            </div>

            <div data-search-picker
                 data-search-url="{{ route('admin.students.search') }}"
                 data-empty-label="لا يوجد طالب مطابق"
                 class="space-y-3">
                <div class="relative max-w-md">
                    <label class="block text-sm font-medium text-gray-700 mb-1">إضافة ابن</label>
                    <input type="text" data-picker-input autocomplete="off" placeholder="ابحث باسم الطالب..."
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    <div data-picker-results
                         class="hidden absolute z-30 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto"></div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500 text-right">
                                <th class="px-3 py-2 font-medium">الطالب</th>
                                <th class="px-3 py-2 font-medium">الصف</th>
                                <th class="px-3 py-2 font-medium">صلة القرابة</th>
                                <th class="px-3 py-2 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody data-picker-selected>
                            @foreach($linkedStudents as $student)
                                @php
                                    $relationship = old("relationships.{$student->id}", $student->pivot?->relationship ?? 'guardian');
                                @endphp
                                <tr data-id="{{ $student->id }}" class="border-t border-gray-100">
                                    <td class="px-3 py-2">
                                        <input type="hidden" name="student_ids[]" value="{{ $student->id }}">
                                        <span class="text-gray-800 font-medium">{{ $student->name }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-gray-500">{{ $student->classroom?->name ?? '—' }}</td>
                                    <td class="px-3 py-2">
                                        <select name="relationships[{{ $student->id }}]" class="border border-gray-300 rounded-lg px-2 py-1 text-xs">
                                            <option value="father" @selected($relationship === 'father')>أب</option>
                                            <option value="mother" @selected($relationship === 'mother')>أم</option>
                                            <option value="guardian" @selected($relationship === 'guardian')>ولي أمر</option>
                                            <option value="other" @selected($relationship === 'other')>أخرى</option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-2 text-left">
                                        <button type="button" data-picker-remove class="text-xs font-bold text-red-600 hover:text-red-800">إزالة</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <p data-picker-empty @class(['text-sm text-gray-400 text-center py-6', 'hidden' => $linkedStudents->isNotEmpty()])>
                        لا يوجد أبناء مرتبطون بعد — ابحث باسم الطالب في الأعلى لإضافة ابن.
                    </p>
                </div>

                <template data-picker-template>
                    <tr data-id="__ID__" class="border-t border-gray-100">
                        <td class="px-3 py-2">
                            <input type="hidden" name="student_ids[]" value="__ID__">
                            <span class="text-gray-800 font-medium">__NAME__</span>
                        </td>
                        <td class="px-3 py-2 text-gray-500">__META__</td>
                        <td class="px-3 py-2">
                            <select name="relationships[__ID__]" class="border border-gray-300 rounded-lg px-2 py-1 text-xs">
                                <option value="father">أب</option>
                                <option value="mother">أم</option>
                                <option value="guardian" selected>ولي أمر</option>
                                <option value="other">أخرى</option>
                            </select>
                        </td>
                        <td class="px-3 py-2 text-left">
                            <button type="button" data-picker-remove class="text-xs font-bold text-red-600 hover:text-red-800">إزالة</button>
                        </td>
                    </tr>
                </template>
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
