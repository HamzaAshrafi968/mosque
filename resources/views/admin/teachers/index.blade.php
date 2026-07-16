@extends('layouts.app')

@section('title', 'إدارة المعلمين')

@section('content')
<div class="bg-white rounded-xl shadow overflow-hidden mb-6 p-4">
    <form method="GET" action="{{ route('admin.teachers.index') }}" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">بحث</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="اسم المعلم..."
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الجنس</label>
            <select name="gender" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="">الكل</option>
                <option value="male" @selected(request('gender') === 'male')>ذكر</option>
                <option value="female" @selected(request('gender') === 'female')>أنثى</option>
            </select>
        </div>
        <div>
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg w-full">بحث</button>
        </div>
    </form>
</div>

<div class="mb-4">
    <a href="{{ route('admin.teachers.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg inline-block">إضافة معلم</a>
</div>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <table class="w-full">
        <thead>
            <tr class="bg-gray-50 text-gray-600 text-sm">
                <th class="px-4 py-3 text-right">الاسم</th>
                <th class="px-4 py-3 text-right">الجنس</th>
                <th class="px-4 py-3 text-right">التخصص</th>
                <th class="px-4 py-3 text-right">الهاتف</th>
                <th class="px-4 py-3 text-right">البريد الإلكتروني</th>
                <th class="px-4 py-3 text-right">عدد المواد</th>
                <th class="px-4 py-3 text-right">الحالة</th>
                <th class="px-4 py-3 text-right">إجراءات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($teachers as $teacher)
                <tr>
                    <td class="px-4 py-3 border-t font-bold">{{ $teacher->name }}</td>
                    <td class="px-4 py-3 border-t">{{ $teacher->gender === 'male' ? 'ذكر' : 'أنثى' }}</td>
                    <td class="px-4 py-3 border-t">{{ $teacher->specialty }}</td>
                    <td class="px-4 py-3 border-t">{{ $teacher->phone }}</td>
                    <td class="px-4 py-3 border-t">{{ $teacher->email }}</td>
                    <td class="px-4 py-3 border-t">{{ $teacher->subjects_count }}</td>
                    <td class="px-4 py-3 border-t">
                        <span @class([
                            'px-2 py-1 rounded-full text-xs font-bold',
                            'bg-green-100 text-green-800' => $teacher->is_active,
                            'bg-red-100 text-red-800' => !$teacher->is_active,
                        ])>
                            {{ $teacher->is_active ? 'نشط' : 'غير نشط' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 border-t">
                        <div class="flex gap-2">
                            <a href="{{ route('admin.teachers.edit', $teacher) }}" class="text-blue-600 hover:underline text-sm">تعديل</a>
                            <form method="POST" action="{{ route('admin.teachers.destroy', $teacher) }}" class="inline" onsubmit="return confirm('هل أنت متأكد؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline text-sm">حذف</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-4 py-6 text-center text-gray-500">لا يوجد معلمون</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $teachers->links() }}
</div>
@endsection
