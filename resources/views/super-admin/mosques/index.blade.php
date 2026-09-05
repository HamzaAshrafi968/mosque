@extends('layouts.app')

@section('title', 'الجوامع')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h2 class="text-2xl font-extrabold text-gray-800">إدارة الجوامع</h2>
    <a href="{{ route('super-admin.mosques.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-5 py-2.5 rounded-xl transition">+ جامع جديد</a>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50 text-gray-600 text-sm">
                    <th class="px-4 py-3 text-right">الاسم</th>
                    <th class="px-4 py-3 text-right">الرمز</th>
                    <th class="px-4 py-3 text-right">الهاتف</th>
                    <th class="px-4 py-3 text-right">المستخدمون</th>
                    <th class="px-4 py-3 text-right">الحالة</th>
                    <th class="px-4 py-3 text-right">إجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($mosques as $mosque)
                    <tr>
                        <td class="px-4 py-3 font-semibold text-gray-800">{{ $mosque->name }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $mosque->code ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500" dir="ltr">{{ $mosque->phone ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $mosque->users_count }}</td>
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
                                <a href="{{ route('super-admin.mosques.users.index', $mosque) }}" class="px-2.5 py-1.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 rounded-lg text-xs">المستخدمون</a>
                                <a href="{{ route('super-admin.mosques.roles.index', $mosque) }}" class="px-2.5 py-1.5 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg text-xs">الأدوار</a>
                                <a href="{{ route('super-admin.mosques.edit', $mosque) }}" class="px-2.5 py-1.5 bg-gray-100 text-gray-700 hover:bg-gray-200 rounded-lg text-xs">تعديل</a>
                                <form method="POST" action="{{ route('super-admin.mosques.destroy', $mosque) }}" onsubmit="return confirm('سيتم أرشفة هذا الجامع. هل أنت متأكد؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-2.5 py-1.5 bg-red-50 text-red-700 hover:bg-red-100 rounded-lg text-xs">أرشفة</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">لا توجد جوامع بعد</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4">{{ $mosques->links() }}</div>
@endsection
