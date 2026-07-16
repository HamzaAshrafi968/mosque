@extends('layouts.app')

@section('title', 'إدارة المواد الدراسية')

@section('content')
<div class="bg-white rounded-xl shadow overflow-hidden p-4 mb-6">
    <form method="POST" action="{{ route('admin.subjects.store') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        @csrf
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">اسم المادة</label>
            <input type="text" name="name" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الحصص الأسبوعية</label>
            <input type="number" name="weekly_lessons" min="1" value="1" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">المعلم</label>
            <select name="teacher_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="">بدون معلم</option>
                @foreach($teachers as $teacher)
                    <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">إضافة مادة</button>
    </form>
</div>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <table class="w-full">
        <thead>
            <tr class="bg-gray-50 text-gray-600 text-sm">
                <th class="px-4 py-3 text-right">المادة</th>
                <th class="px-4 py-3 text-right">الحصص الأسبوعية</th>
                <th class="px-4 py-3 text-right">المعلم</th>
                <th class="px-4 py-3 text-right">إجراءات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($subjects as $subject)
                <tr>
                    <td class="px-4 py-3 border-t">
                        <form method="POST" action="{{ route('admin.subjects.update', $subject) }}" class="flex gap-2 items-center">
                            @csrf
                            @method('PUT')
                            <input type="text" name="name" value="{{ $subject->name }}" required
                                   class="border border-gray-300 rounded-lg px-3 py-1 w-full text-sm">
                    </td>
                    <td class="px-4 py-3 border-t">
                            <input type="number" name="weekly_lessons" value="{{ $subject->weekly_lessons }}" min="1" required
                                   class="border border-gray-300 rounded-lg px-3 py-1 w-20 text-sm">
                    </td>
                    <td class="px-4 py-3 border-t">
                            <select name="teacher_id" class="border border-gray-300 rounded-lg px-3 py-1 text-sm">
                                <option value="">بدون معلم</option>
                                @foreach($teachers as $teacher)
                                    <option value="{{ $teacher->id }}" @selected($subject->teacher_id == $teacher->id)>{{ $teacher->name }}</option>
                                @endforeach
                            </select>
                    </td>
                    <td class="px-4 py-3 border-t">
                        <div class="flex gap-2 items-center">
                            <button type="submit" class="text-emerald-700 hover:underline text-sm font-bold">حفظ</button>
                        </form>
                            <form method="POST" action="{{ route('admin.subjects.destroy', $subject) }}" class="inline" onsubmit="return confirm('هل أنت متأكد؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline text-sm">حذف</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">لا توجد مواد</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
