@extends('layouts.app')

@section('title', 'أولياء الأمور')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <h1 class="text-2xl font-bold text-gray-800">أولياء الأمور</h1>
    <a href="{{ route('admin.parents.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2.5 rounded-lg">+ إضافة ولي أمر</a>
</div>

<form method="GET" class="bg-white rounded-2xl shadow p-4 mb-6 flex flex-wrap gap-3 items-center">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="بحث بالاسم أو الجوال أو البريد"
           class="flex-1 min-w-[220px] border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
    <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <option value="">كل الحالات</option>
        <option value="active" @selected(request('status') === 'active')>نشط</option>
        <option value="inactive" @selected(request('status') === 'inactive')>غير نشط</option>
    </select>
    <button type="submit" class="bg-gray-800 text-white text-sm font-bold px-4 py-2 rounded-lg">بحث</button>
</form>

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-gray-500 text-right">
                    <th class="px-4 py-3 font-medium">الاسم</th>
                    <th class="px-4 py-3 font-medium">الجوال</th>
                    <th class="px-4 py-3 font-medium">حساب البوابة</th>
                    <th class="px-4 py-3 font-medium">الأبناء</th>
                    <th class="px-4 py-3 font-medium">الحالة</th>
                    <th class="px-4 py-3 font-medium">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($guardians as $guardian)
                    <tr class="border-t border-gray-100">
                        <td class="px-4 py-3 font-bold text-gray-800">{{ $guardian->name }}</td>
                        <td class="px-4 py-3 text-gray-600" dir="ltr">{{ $guardian->phone ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if($guardian->user)
                                <span class="text-xs px-2 py-1 rounded-lg bg-emerald-100 text-emerald-700 font-bold">متاح</span>
                                <div class="text-xs text-gray-400 mt-1" dir="ltr">{{ $guardian->user->email }}</div>
                            @else
                                <span class="text-xs px-2 py-1 rounded-lg bg-gray-100 text-gray-500">بدون حساب</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @forelse($guardian->students as $student)
                                <div class="text-xs">{{ $student->name }} <span class="text-gray-400">({{ $student->classroom?->name }})</span></div>
                            @empty
                                <span class="text-gray-400">—</span>
                            @endforelse
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold {{ $guardian->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $guardian->status === 'active' ? 'نشط' : 'غير نشط' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.parents.edit', $guardian) }}" class="text-emerald-700 hover:underline text-xs">تعديل</a>
                            <form method="POST" action="{{ route('admin.parents.destroy', $guardian) }}" class="inline mr-3" onsubmit="return confirm('سيتم حذف ولي الأمر وربطاته، هل أنت متأكد؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-500 hover:underline text-xs">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400">لا يوجد أولياء أمور</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4">{{ $guardians->links() }}</div>
</div>
@endsection
