@extends('layouts.app')

@section('title', 'لوحة مدير الجوامع')

@section('content')
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
        <div class="text-3xl font-extrabold text-emerald-700">{{ $totals['mosques'] }}</div>
        <div class="text-sm text-gray-500 mt-1">🕌 الجوامع</div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
        <div class="text-3xl font-extrabold text-blue-700">{{ $totals['students'] }}</div>
        <div class="text-sm text-gray-500 mt-1">👨‍🎓 الطلاب</div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
        <div class="text-3xl font-extrabold text-purple-700">{{ $totals['teachers'] }}</div>
        <div class="text-sm text-gray-500 mt-1">🧑‍🏫 الأساتذة</div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
        <div class="text-3xl font-extrabold text-amber-600">{{ $totals['today_attendance'] }}</div>
        <div class="text-sm text-gray-500 mt-1">حضور اليوم</div>
    </div>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="p-5 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-lg font-bold text-gray-800">الجوامع</h3>
        <a href="{{ route('super-admin.mosques.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">
            + جامع جديد
        </a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50 text-gray-600 text-sm">
                    <th class="px-4 py-3 text-right">الجامع</th>
                    <th class="px-4 py-3 text-right">الطلاب</th>
                    <th class="px-4 py-3 text-right">الأساتذة</th>
                    <th class="px-4 py-3 text-right">الصفوف</th>
                    <th class="px-4 py-3 text-right">بانتظار الاعتماد</th>
                    <th class="px-4 py-3 text-right">الحالة</th>
                    <th class="px-4 py-3 text-right">إجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($mosques as $mosque)
                    <tr class="hover:bg-gray-50/70">
                        <td class="px-4 py-3">
                            <div class="font-bold text-gray-800">{{ $mosque->name }}</div>
                            <div class="text-xs text-gray-400">{{ $mosque->code }} · {{ $mosque->users_count }} مستخدم</div>
                        </td>
                        <td class="px-4 py-3">{{ $mosque->students_count }}</td>
                        <td class="px-4 py-3">{{ $mosque->teachers_count }}</td>
                        <td class="px-4 py-3">{{ $mosque->classrooms_count }}</td>
                        <td class="px-4 py-3">
                            @if($mosque->pending_approvals > 0)
                                <span class="px-2 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800">{{ $mosque->pending_approvals }}</span>
                            @else
                                <span class="text-gray-300">0</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span @class([
                                'px-2 py-1 rounded-full text-xs font-bold',
                                'bg-green-100 text-green-800' => $mosque->status === 'active',
                                'bg-red-100 text-red-800' => $mosque->status === 'inactive',
                                'bg-gray-100 text-gray-600' => $mosque->status === 'archived',
                            ])>
                                {{ ['active' => 'نشط', 'inactive' => 'موقوف', 'archived' => 'مؤرشف'][$mosque->status] ?? $mosque->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1.5">
                                <form method="POST" action="{{ route('super-admin.mosques.enter', $mosque) }}">
                                    @csrf
                                    <button type="submit" class="px-2.5 py-1.5 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-lg text-xs font-medium">الدخول للجامع</button>
                                </form>
                                <a href="{{ route('super-admin.mosques.users.index', $mosque) }}" class="px-2.5 py-1.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 rounded-lg text-xs font-medium">المستخدمون</a>
                                <a href="{{ route('super-admin.mosques.roles.index', $mosque) }}" class="px-2.5 py-1.5 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg text-xs font-medium">الأدوار</a>
                                <a href="{{ route('super-admin.mosques.edit', $mosque) }}" class="px-2.5 py-1.5 bg-gray-100 text-gray-700 hover:bg-gray-200 rounded-lg text-xs font-medium">تعديل</a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
