@extends('layouts.app')

@section('title', "مستخدمي {$mosque->name}")

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <a href="{{ route('super-admin.mosques.index') }}" class="text-sm text-emerald-700 hover:text-emerald-800">← الجوامع</a>
        <h2 class="text-2xl font-extrabold text-gray-800 mt-1">مستخدمو {{ $mosque->name }}</h2>
    </div>
    <a href="{{ route('super-admin.mosques.roles.index', $mosque) }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-5 py-2.5 rounded-xl transition">الأدوار والصلاحيات</a>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-6">
    <h3 class="font-bold text-gray-700 mb-4">إضافة مستخدم</h3>
    <form method="POST" action="{{ route('super-admin.mosques.users.store', $mosque) }}" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
        @csrf
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الاسم *</label>
            <input type="text" name="name" required value="{{ old('name') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">البريد *</label>
            <input type="email" name="email" required value="{{ old('email') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور *</label>
            <input type="password" name="password" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الدور *</label>
            <select name="role_code" id="create-role" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                @foreach($roles as $role)
                    <option value="{{ $role->code }}" @selected(old('role_code', 'teacher') === $role->code)>{{ $role->name }} ({{ $role->code }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الجنس *</label>
            <select name="gender" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="male" @selected(old('gender') === 'male')>ذكر</option>
                <option value="female" @selected(old('gender') === 'female')>أنثى</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الهاتف</label>
            <input type="text" name="phone" value="{{ old('phone') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <div id="create-teacher-specialty">
            <label class="block text-sm font-medium text-gray-700 mb-1">التخصص <span class="text-gray-400 text-xs">(للمعلم)</span></label>
            <input type="text" name="specialty" value="{{ old('specialty') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <div id="create-teacher-session">
            <label class="block text-sm font-medium text-gray-700 mb-1">الدوام <span class="text-gray-400 text-xs">(للمعلم)</span></label>
            <select name="study_session_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="">غير محدد (كل الدوامات)</option>
                @foreach($studySessions as $session)
                    <option value="{{ $session->id }}" @selected(old('study_session_id') == $session->id)>{{ $session->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="md:col-span-2">
            <x-photo-input label="الصورة الشخصية" help="صورة مدير الجامع / المعلم (اختياري) — JPG, PNG أو WebP بحد أقصى 2MB" />
        </div>
        <div>
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-5 py-2 rounded-lg">إضافة</button>
        </div>
    </form>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
    <table class="w-full">
        <thead>
            <tr class="bg-gray-50 text-gray-600 text-sm">
                <th class="px-4 py-3 text-right">الاسم</th>
                <th class="px-4 py-3 text-right">البريد</th>
                <th class="px-4 py-3 text-right">الأدوار الحالية</th>
                <th class="px-4 py-3 text-right">إجراءات</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($users as $user)
                <tr>
                    <td class="px-4 py-3 font-semibold text-gray-800">
                        <div class="flex items-center gap-3">
                            <x-avatar :src="$user->avatarUrl()" :name="$user->name" size="sm" />
                            {{ $user->name }}
                        </div>
                    </td>
                    <td class="px-4 py-3 text-gray-500" dir="ltr">{{ $user->email }}</td>
                    <td class="px-4 py-3">
                        <div class="flex flex-wrap gap-1">
                            @foreach($user->roles as $userRole)
                                <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700">{{ $userRole->name }}</span>
                            @endforeach
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('super-admin.mosques.users.edit', [$mosque, $user]) }}" class="px-2.5 py-1.5 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-lg text-xs">تعديل المستخدم والصلاحيات</a>
                            @if(!$user->isSuperAdmin())
                                <form method="POST" action="{{ route('super-admin.mosques.users.destroy', [$mosque, $user]) }}" onsubmit="return confirm('حذف هذا المستخدم نهائياً؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-2.5 py-1.5 bg-red-50 text-red-700 hover:bg-red-100 rounded-lg text-xs">حذف</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">لا يوجد مستخدمون</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $users->links() }}</div>

@php
    $nonTeacherRoles = [
        \App\Services\RoleService::ROLE_MOSQUE_MANAGER,
        \App\Services\RoleService::ROLE_GUARDIAN,
        \App\Services\RoleService::ROLE_STUDENT,
    ];
@endphp

<script>
    // حقول التخصص والدوام تخص المعلم/الأدوار المخصصة فقط.
    const nonTeacherRoles = @json($nonTeacherRoles);

    function toggleCreateTeacherFields() {
        const role = document.getElementById('create-role');
        const fields = [
            document.getElementById('create-teacher-specialty'),
            document.getElementById('create-teacher-session'),
        ];

        const show = role && !nonTeacherRoles.includes(role.value);

        fields.forEach(function (field) {
            if (field) { field.classList.toggle('hidden', !show); }
        });
    }

    document.getElementById('create-role')?.addEventListener('change', toggleCreateTeacherFields);
    toggleCreateTeacherFields();
</script>
@endsection
