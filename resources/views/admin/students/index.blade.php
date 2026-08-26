@extends('layouts.app')

@section('title', 'إدارة الطلاب')

@section('content')
<div class="bg-white rounded-xl shadow overflow-hidden mb-6 p-4">
    <form method="GET" action="{{ route('admin.students.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">بحث</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="اسم الطالب..."
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الصف</label>
            <select name="classroom_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="">الكل</option>
                @foreach($classrooms as $classroom)
                    <option value="{{ $classroom->id }}" @selected(request('classroom_id') == $classroom->id)>{{ $classroom->name }}</option>
                @endforeach
            </select>
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
            <label class="block text-sm font-medium text-gray-700 mb-1">الحالة</label>
            <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="">نشط</option>
                <option value="active" @selected(request('status') === 'active')>نشط</option>
                <option value="archived" @selected(request('status') === 'archived')>مؤرشف</option>
            </select>
        </div>
        <div>
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg w-full">بحث</button>
        </div>
    </form>
</div>

<div class="mb-4">
    <a href="{{ route('admin.students.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg inline-block">إضافة طالب</a>
</div>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50 text-gray-600 text-sm">
                    <th class="px-4 py-3 text-right whitespace-nowrap">الاسم</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">الجنس</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">الصف</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">الشعبة</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">ولي الأمر</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">الهاتف</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">الحالة</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($students as $student)
                    <tr>
                        <td class="px-4 py-3 border-t whitespace-nowrap">
                            <a href="{{ route('admin.students.show', $student) }}" class="text-emerald-700 font-bold hover:underline">
                                {{ $student->name }}
                            </a>
                        </td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $student->gender === 'male' ? 'ذكر' : 'أنثى' }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $student->classroom?->name }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $student->section?->name }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $student->guardian_name }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $student->guardian_phone }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">
                            <span @class([
                                'px-2 py-1 rounded-full text-xs font-bold',
                                'bg-green-100 text-green-800' => $student->status === 'active',
                                'bg-gray-100 text-gray-800' => $student->status === 'archived',
                            ])>
                                {{ $student->status === 'active' ? 'نشط' : 'مؤرشف' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">
                            <div class="flex gap-2">
                                <a href="{{ route('admin.students.edit', $student) }}" class="text-blue-600 hover:underline text-sm">تعديل</a>
                                <form method="POST" action="{{ route('admin.students.archive', $student) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="text-yellow-600 hover:underline text-sm">أرشفة/تفعيل</button>
                                </form>
                                <form method="POST" action="{{ route('admin.students.destroy', $student) }}" class="inline" onsubmit="return confirm('هل أنت متأكد؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline text-sm">حذف</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-gray-500">لا يوجد طلاب</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">
    {{ $students->links() }}
</div>
@endsection
