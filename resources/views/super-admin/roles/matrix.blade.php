@extends('layouts.app')

@section('title', "صلاحيات دور {$role->name}")

@section('content')
<div class="mb-6">
    <a href="{{ route('super-admin.mosques.roles.index', $mosque) }}" class="text-sm text-emerald-700 hover:text-emerald-800">← أدوار {{ $mosque->name }}</a>
    <h2 class="text-2xl font-extrabold text-gray-800 mt-1">مصفوفة صلاحيات: {{ $role->name }}</h2>
    <p class="text-sm text-gray-500 mt-1">حدد لكل عملية النطاق المسموح به، واستخدم البحث والإجراءات الجماعية والنسخ من دور آخر. العمليات غير المحددة تكون مرفوضة.</p>
</div>

<form method="POST" action="{{ route('super-admin.mosques.roles.update', [$mosque, $role]) }}" class="space-y-4">
    @csrf
    @method('PATCH')

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">اسم الدور</label>
            <input type="text" name="name" required value="{{ old('name', $role->name) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الوصف</label>
            <input type="text" name="description" value="{{ old('description', $role->description) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <x-permission-matrix
            mode="role"
            :granted="$granted"
            :exclude="['mosques']"
            :copy-sources="$copySources"
        />
    </div>

    <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-6 py-2.5 rounded-xl">حفظ الصلاحيات</button>
</form>
@endsection
