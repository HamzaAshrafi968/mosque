@extends('layouts.app')

@section('title', 'ملفي الشخصي')

@section('content')
<div class="max-w-2xl bg-white rounded-2xl shadow p-6">
    <h2 class="text-xl font-bold text-gray-800 mb-4">الملف الشخصي</h2>
    <dl class="divide-y divide-gray-100 text-sm">
        <div class="py-3 grid grid-cols-3 gap-2">
            <dt class="text-gray-500 font-medium">الاسم</dt>
            <dd class="col-span-2 font-bold text-gray-800">{{ $student->name }}</dd>
        </div>
        <div class="py-3 grid grid-cols-3 gap-2">
            <dt class="text-gray-500 font-medium">تاريخ الميلاد</dt>
            <dd class="col-span-2 text-gray-800">{{ $student->birth_date?->format('Y-m-d') ?? '—' }}</dd>
        </div>
        <div class="py-3 grid grid-cols-3 gap-2">
            <dt class="text-gray-500 font-medium">الصف</dt>
            <dd class="col-span-2 text-gray-800">{{ $student->classroom?->name ?? '—' }}</dd>
        </div>
        <div class="py-3 grid grid-cols-3 gap-2">
            <dt class="text-gray-500 font-medium">الشعبة</dt>
            <dd class="col-span-2 text-gray-800">{{ $student->section?->name ?? '—' }}</dd>
        </div>
        <div class="py-3 grid grid-cols-3 gap-2">
            <dt class="text-gray-500 font-medium">بريد الحساب</dt>
            <dd class="col-span-2 text-gray-800" dir="ltr">{{ $user->email }}</dd>
        </div>
    </dl>
    <p class="mt-4 text-xs text-gray-400">بعض البيانات الإدارية لا تظهر للطلاب — للاستفسار تواصل مع إدارة الجامع.</p>
</div>
@endsection
