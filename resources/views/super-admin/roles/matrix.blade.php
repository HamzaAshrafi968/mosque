@extends('layouts.app')

@section('title', "صلاحيات دور {$role->name}")

@section('content')
<div class="mb-6">
    <a href="{{ route('super-admin.mosques.roles.index', $mosque) }}" class="text-sm text-emerald-700 hover:text-emerald-800">← أدوار {{ $mosque->name }}</a>
    <h2 class="text-2xl font-extrabold text-gray-800 mt-1">مصفوفة صلاحيات: {{ $role->name }}</h2>
    <p class="text-sm text-gray-500 mt-1">حدد لكل عملية النطاق المسموح به (شامل/الجامع/خاص بالمستخدم). العمليات غير المحددة تكون مرفوضة.</p>
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
        @php
            $resourceLabels = [
                'mosques' => 'إدارة الجوامع', 'students' => 'الطلاب', 'teachers' => 'الأساتذة',
                'classes' => 'الصفوف', 'sections' => 'الشعب', 'subjects' => 'المواد',
                'schedule' => 'الجداول', 'attendance' => 'الحضور', 'exams' => 'الامتحانات',
                'grades' => 'الدرجات', 'assignments' => 'الواجبات', 'lessons' => 'الدروس',
                'announcements' => 'الإعلانات', 'messages' => 'الرسائل', 'reports' => 'التقارير',
                'users' => 'المستخدمون', 'roles' => 'الأدوار', 'permissions' => 'الصلاحيات',
                'custom_fields' => 'الحقول المخصصة', 'audit_logs' => 'سجل العمليات',
            ];
        @endphp
        @foreach(\App\Support\PermissionCatalog::grouped() as $resource => $permissions)
            <div class="border-b border-gray-100 last:border-0">
                <div class="px-5 py-3 bg-gray-50 font-bold text-gray-700 text-sm">{{ $resourceLabels[$resource] ?? $resource }}</div>
                <table class="w-full">
                    @foreach($permissions as $permission)
                        <tr class="border-t border-gray-50">
                            <td class="px-5 py-2.5 w-1/2">
                                <span class="text-sm text-gray-700">{{ $permission['label'] }}</span>
                                <span class="text-xs text-gray-300 mr-2 font-mono" dir="ltr">{{ $permission['code'] }}</span>
                            </td>
                            <td class="px-5 py-2.5">
                                <select name="permissions[{{ $permission['code'] }}]" class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm w-full md:w-56">
                                    <option value="">— مرفوض —</option>
                                    <option value="mosque" @selected(($granted[$permission['code']] ?? null) === 'mosque')>الجامع الخاص</option>
                                    <option value="own" @selected(($granted[$permission['code']] ?? null) === 'own')>خاص بالمستخدم</option>
                                    <option value="class" @selected(($granted[$permission['code']] ?? null) === 'class')>صفوف محددة</option>
                                    <option value="section" @selected(($granted[$permission['code']] ?? null) === 'section')>شعب محددة</option>
                                    <option value="global" @selected(($granted[$permission['code']] ?? null) === 'global')>شامل (كل الجوامع)</option>
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endforeach
    </div>

    <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-6 py-2.5 rounded-xl">حفظ الصلاحيات</button>
</form>
@endsection
