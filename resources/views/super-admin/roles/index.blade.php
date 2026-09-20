@extends('layouts.app')

@section('title', "أدوار {$mosque->name}")

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <a href="{{ route('super-admin.mosques.index') }}" class="text-sm text-emerald-700 hover:text-emerald-800">← الجوامع</a>
        <h2 class="text-2xl font-extrabold text-gray-800 mt-1">الأدوار والصلاحيات في {{ $mosque->name }}</h2>
    </div>
    <a href="{{ route('super-admin.mosques.users.index', $mosque) }}" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-5 py-2.5 rounded-xl transition">المستخدمون</a>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    @forelse($roles as $role)
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 flex flex-col gap-3">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <div class="font-bold text-gray-800 flex items-center gap-2">
                        {{ $role->name }}
                        @if($role->is_system)
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-500">افتراضي</span>
                        @endif
                    </div>
                    <div class="text-xs text-gray-400 mt-1">{{ $role->description }}</div>
                </div>
            </div>
            <div class="text-xs text-gray-500">{{ $role->users_count }} مستخدم · {{ $role->permissions_count }} صلاحية</div>
            <div class="flex gap-2 mt-auto">
                <a href="{{ route('super-admin.mosques.roles.edit', [$mosque, $role]) }}" class="flex-1 text-center bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold px-3 py-2 rounded-lg">الصلاحيات</a>
                @if(!$role->is_system)
                    <form method="POST" action="{{ route('super-admin.mosques.roles.destroy', [$mosque, $role]) }}" onsubmit="return confirm('حذف الدور نهائياً؟ سيُفصل من جميع مستخدميه')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="bg-red-50 text-red-700 hover:bg-red-100 text-sm font-bold px-3 py-2 rounded-lg">حذف</button>
                    </form>
                @endif
            </div>
        </div>
    @empty
        <div class="md:col-span-3 text-center py-10 text-gray-400">لا توجد أدوار بعد</div>
    @endforelse
</div>
@endsection
