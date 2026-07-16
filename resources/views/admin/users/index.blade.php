@extends('layouts.app')

@section('title', 'الحسابات والصلاحيات')

@section('content')
<div class="bg-white rounded-xl shadow overflow-hidden p-4 mb-6">
    <form method="POST" action="{{ route('admin.users.store') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        @csrf
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الاسم <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name') }}" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني <span class="text-red-500">*</span></label>
            <input type="email" name="email" value="{{ old('email') }}" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور <span class="text-red-500">*</span></label>
            <input type="password" name="password" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الصلاحية <span class="text-red-500">*</span></label>
            <select name="role" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="admin" @selected(old('role') === 'admin')>مدير</option>
                <option value="teacher" @selected(old('role') === 'teacher')>معلم</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الجنس <span class="text-red-500">*</span></label>
            <select name="gender" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="male" @selected(old('gender') === 'male')>ذكر</option>
                <option value="female" @selected(old('gender') === 'female')>أنثى</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الهاتف</label>
            <input type="text" name="phone" value="{{ old('phone') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg w-full">إنشاء</button>
        </div>
    </form>
</div>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <table class="w-full">
        <thead>
            <tr class="bg-gray-50 text-gray-600 text-sm">
                <th class="px-4 py-3 text-right">الاسم</th>
                <th class="px-4 py-3 text-right">البريد الإلكتروني</th>
                <th class="px-4 py-3 text-right">الصلاحية</th>
                <th class="px-4 py-3 text-right">إجراءات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $user)
                <tr>
                    <td class="px-4 py-3 border-t">
                        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="flex gap-2 items-center">
                            @csrf
                            @method('PUT')
                            <input type="text" name="name" value="{{ $user->name }}" required
                                   class="border border-gray-300 rounded-lg px-3 py-1 w-full text-sm">
                    </td>
                    <td class="px-4 py-3 border-t">
                            <input type="email" name="email" value="{{ $user->email }}" required
                                   class="border border-gray-300 rounded-lg px-3 py-1 w-full text-sm">
                    </td>
                    <td class="px-4 py-3 border-t">
                        <div class="flex gap-2 items-center">
                            <select name="role" required class="border border-gray-300 rounded-lg px-3 py-1 text-sm">
                                <option value="admin" @selected($user->role === 'admin')>مدير</option>
                                <option value="teacher" @selected($user->role === 'teacher')>معلم</option>
                            </select>
                            <input type="password" name="password" placeholder="كلمة مرور جديدة (اختياري)"
                                   class="border border-gray-300 rounded-lg px-3 py-1 text-sm">
                    </td>
                    <td class="px-4 py-3 border-t">
                        <div class="flex gap-2 items-center">
                            <button type="submit" class="text-emerald-700 hover:underline text-sm font-bold">حفظ</button>
                        </form>
                            @if(auth()->id() !== $user->id)
                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="inline" onsubmit="return confirm('هل أنت متأكد؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline text-sm">حذف</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">لا يوجد مستخدمين</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $users->links() }}
</div>
@endsection
