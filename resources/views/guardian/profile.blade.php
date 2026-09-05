@extends('layouts.app')

@section('title', 'بيانات ولي الأمر')

@section('content')
<div class="max-w-2xl bg-white rounded-2xl shadow p-6">
    <h2 class="text-xl font-bold text-gray-800 mb-4">الملف الشخصي</h2>
    <dl class="divide-y divide-gray-100 text-sm">
        <div class="py-3 grid grid-cols-3 gap-2">
            <dt class="text-gray-500 font-medium">الاسم</dt>
            <dd class="col-span-2 text-gray-800 font-bold">{{ $guardian->name }}</dd>
        </div>
        <div class="py-3 grid grid-cols-3 gap-2">
            <dt class="text-gray-500 font-medium">رقم الجوال</dt>
            <dd class="col-span-2 text-gray-800" dir="ltr">{{ $guardian->phone ?? '—' }}</dd>
        </div>
        <div class="py-3 grid grid-cols-3 gap-2">
            <dt class="text-gray-500 font-medium">البريد الإلكتروني</dt>
            <dd class="col-span-2 text-gray-800" dir="ltr">{{ $user->email ?? '—' }}</dd>
        </div>
        <div class="py-3 grid grid-cols-3 gap-2">
            <dt class="text-gray-500 font-medium">الحالة</dt>
            <dd class="col-span-2 text-gray-800">{{ $guardian->status === 'active' ? 'نشط' : 'غير نشط' }}</dd>
        </div>
        <div class="py-3 grid grid-cols-3 gap-2">
            <dt class="text-gray-500 font-medium">تاريخ التسجيل</dt>
            <dd class="col-span-2 text-gray-800">{{ $guardian->created_at->format('Y-m-d') }}</dd>
        </div>
    </dl>
    <p class="mt-4 text-xs text-gray-400">لطلب تعديل بيانات الحساب يرجى التواصل مع إدارة الجامع.</p>
</div>
@endsection
