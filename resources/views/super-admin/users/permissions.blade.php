@extends('layouts.app')

@section('title', "صلاحيات {$user->name}")

@section('content')
<div class="mb-6">
    <a href="{{ route('super-admin.mosques.users.index', $mosque) }}" class="text-sm text-emerald-700 hover:text-emerald-800">← مستخدمو {{ $mosque->name }}</a>
    <h2 class="text-2xl font-extrabold text-gray-800 mt-1">مصفوفة صلاحيات: {{ $user->name }}</h2>
    <p class="text-sm text-gray-500 mt-1">حدد لكل عملية النطاق المسموح به (الجامع/خاص بالمستخدم). «وراثة الدور» تُبقي صلاحية الدور، و«منع صريح» يلغيها لهذا المستخدم فقط. العمليات غير المحددة تكون مرفوضة.</p>
    <p class="text-xs text-gray-400 mt-1">
        الأدوار: {{ $user->roles->pluck('name')->join('، ') ?: '—' }}
    </p>
</div>

<form method="POST" action="{{ route('super-admin.mosques.users.permissions.update', [$mosque, $user]) }}" class="space-y-4">
    @csrf
    @method('PATCH')

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        @include('super-admin.users.partials.matrix')
    </div>

    <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-6 py-2.5 rounded-xl">حفظ الصلاحيات</button>
</form>
@endsection
