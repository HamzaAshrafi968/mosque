@extends('layouts.app')

@section('title', "تعديل {$user->name}")

@section('content')
<div class="mb-6">
    <a href="{{ route('super-admin.mosques.users.index', $mosque) }}" class="text-sm text-emerald-700 hover:text-emerald-800">← مستخدمو {{ $mosque->name }}</a>
    <h2 class="text-2xl font-extrabold text-gray-800 mt-1">تعديل المستخدم: {{ $user->name }}</h2>
    <p class="text-sm text-gray-500 mt-1">بيانات المستخدم ودوره وخصائصه، ومعها مصفوفة صلاحياته الفردية (تتقدم على صلاحيات الدور).</p>
</div>

<form method="POST" action="{{ route('super-admin.mosques.users.update', [$mosque, $user]) }}" enctype="multipart/form-data" class="space-y-6">
    @csrf
    @method('PATCH')

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-bold text-gray-700 mb-4">بيانات المستخدم</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الاسم *</label>
                <input type="text" name="name" required value="{{ old('name', $user->name) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">البريد *</label>
                <input type="email" name="email" required value="{{ old('email', $user->email) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">كلمة مرور جديدة <span class="text-gray-400 text-xs">(اختياري)</span></label>
                <input type="password" name="password" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الدور *</label>
                <select name="role_code" id="user-role" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    @foreach($roles as $role)
                        <option value="{{ $role->code }}" @selected(old('role_code', $user->roles->first()->code ?? null) === $role->code)>{{ $role->name }} ({{ $role->code }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الجنس *</label>
                <select name="gender" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="male" @selected(old('gender', $user->gender) === 'male')>ذكر</option>
                    <option value="female" @selected(old('gender', $user->gender) === 'female')>أنثى</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الهاتف</label>
                <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div id="teacher-specialty-field">
                <label class="block text-sm font-medium text-gray-700 mb-1">التخصص <span class="text-gray-400 text-xs">(للمعلم)</span></label>
                <input type="text" name="specialty" value="{{ old('specialty', $teacher->specialty ?? '') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            @php
                $selectedSessions = old('study_session_ids', $teacher?->studySessions->pluck('id')->all() ?? []);
            @endphp
            <div id="teacher-session-field">
                <label class="block text-sm font-medium text-gray-700 mb-1">الدوامات <span class="text-gray-400 text-xs">(للمعلم)</span></label>
                <div class="grid grid-cols-2 gap-2">
                    @foreach($studySessions as $session)
                        <label class="flex items-center gap-2 border border-gray-200 rounded-lg px-3 py-2 cursor-pointer hover:bg-gray-50">
                            <input type="checkbox" name="study_session_ids[]" value="{{ $session->id }}" @checked(in_array($session->id, $selectedSessions))
                                   class="rounded border-gray-300 text-emerald-700 focus:ring-emerald-500">
                            <span class="text-sm text-gray-700">{{ $session->name }}</span>
                        </label>
                    @endforeach
                </div>
                <input type="hidden" name="study_session_ids[]" value="">
                <p class="text-xs text-gray-400 mt-1">يمكن تحديد أكثر من دوام.</p>
            </div>
            <div class="md:col-span-2">
                <x-photo-input
                    label="الصورة الشخصية"
                    help="صورة المستخدم (اختياري) — JPG, PNG أو WebP بحد أقصى 2MB"
                    :current-src="$user->avatarUrl()"
                    :current-name="$user->name"
                />
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="font-bold text-gray-700">مصفوفة الصلاحيات الفردية</h3>
            <p class="text-xs text-gray-500 mt-1">
                لكل عملية: «وراثة الدور» تُبقي صلاحية الدور، و«منع صريح» يسحبها لهذا المستخدم فقط، واختيار النطاق يمنحها ويستبدل نطاق الدور.
                العمليات غير المحددة تكون مرفوضة.
            </p>
            <p class="text-xs text-gray-400 mt-1">الأدوار: {{ $user->roles->pluck('name')->join('، ') ?: '—' }}</p>
        </div>

        @include('super-admin.users.partials.matrix')
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-6 py-2.5 rounded-xl">حفظ المستخدم والصلاحيات</button>
        <a href="{{ route('super-admin.mosques.users.index', $mosque) }}" class="text-gray-500 text-sm hover:underline">إلغاء</a>
    </div>
</form>

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

    function toggleTeacherFields() {
        const role = document.getElementById('user-role');
        const fields = [
            document.getElementById('teacher-specialty-field'),
            document.getElementById('teacher-session-field'),
        ];

        const show = role && !nonTeacherRoles.includes(role.value);

        fields.forEach(function (field) {
            if (field) { field.classList.toggle('hidden', !show); }
        });
    }

    document.getElementById('user-role')?.addEventListener('change', toggleTeacherFields);
    toggleTeacherFields();
</script>
@endsection
