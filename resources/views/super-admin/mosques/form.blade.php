@extends('layouts.app')

@section('title', $mosque ? 'تعديل جامع' : 'جامع جديد')

@section('content')
@php
    $isEdit = $mosque !== null;
@endphp
<div class="max-w-2xl mx-auto">
    <h2 class="text-2xl font-extrabold text-gray-800 mb-6">{{ $isEdit ? 'تعديل بيانات الجامع' : 'إنشاء جامع جديد' }}</h2>

    <form method="POST" action="{{ $isEdit ? route('super-admin.mosques.update', $mosque) : route('super-admin.mosques.store') }}" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-4">
        @csrf
        @if($isEdit) @method('PATCH') @endif

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">اسم الجامع *</label>
            <input type="text" name="name" required value="{{ old('name', $mosque?->name) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الرمز</label>
                <input type="text" name="code" value="{{ old('code', $mosque?->code) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الهاتف</label>
                <input type="text" name="phone" value="{{ old('phone', $mosque?->phone) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
                <input type="email" name="email" value="{{ old('email', $mosque?->email) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الحالة</label>
                <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    @foreach(['active' => 'نشط', 'inactive' => 'موقوف', 'archived' => 'مؤرشف'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $mosque?->status ?? 'active') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">العنوان</label>
            <input type="text" name="address" value="{{ old('address', $mosque?->address) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>

        @if(!$isEdit)
            <div class="border-t border-gray-100 pt-4">
                <h3 class="font-bold text-gray-700 mb-3">مدير الجامع (اختياري — يمكن إضافته لاحقاً)</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الاسم</label>
                        <input type="text" name="manager_name" value="{{ old('manager_name') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">البريد</label>
                        <input type="email" name="manager_email" value="{{ old('manager_email') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور (8 أحرف+)</label>
                        <input type="password" name="manager_password" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الهاتف</label>
                        <input type="text" name="manager_phone" value="{{ old('manager_phone') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                </div>
            </div>
        @endif

        <div class="flex gap-3 pt-2">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-6 py-2.5 rounded-xl">{{ $isEdit ? 'حفظ التعديلات' : 'إنشاء الجامع' }}</button>
            <a href="{{ route('super-admin.mosques.index') }}" class="bg-gray-100 text-gray-700 font-bold px-6 py-2.5 rounded-xl">إلغاء</a>
        </div>
    </form>
</div>
@endsection
