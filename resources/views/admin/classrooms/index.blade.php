@extends('layouts.app')

@section('title', 'إدارة الصفوف والشعب')

@section('content')
<div class="bg-white rounded-xl shadow overflow-hidden p-4 mb-6">
    <form method="POST" action="{{ route('admin.classrooms.store') }}" class="flex gap-3 items-end">
        @csrf
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">اسم الصف</label>
            <input type="text" name="name" value="{{ old('name') }}" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg shrink-0">إضافة صف</button>
    </form>
</div>

@forelse($classrooms as $classroom)
    <div class="bg-white rounded-xl shadow overflow-hidden mb-4">
        <div class="px-4 py-3 bg-gray-50 flex justify-between items-center">
            <div>
                <span class="font-bold text-lg">{{ $classroom->name }}</span>
                <span class="text-sm text-gray-500 mr-2">({{ $classroom->students_count }} طالب)</span>
            </div>
            <form method="POST" action="{{ route('admin.classrooms.destroy', $classroom) }}" onsubmit="return confirm('هل أنت متأكد؟')">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-red-600 hover:underline text-sm">حذف الصف</button>
            </form>
        </div>
        <div class="p-4">
            @if($classroom->sections->isNotEmpty())
                <div class="space-y-2 mb-4">
                    @foreach($classroom->sections as $section)
                        <div class="flex justify-between items-center bg-gray-50 rounded-lg px-3 py-2">
                            <span>{{ $section->name }}</span>
                            <form method="POST" action="{{ route('admin.sections.destroy', $section) }}" onsubmit="return confirm('هل أنت متأكد؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline text-sm">حذف</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-sm text-gray-500 mb-4">لا توجد شعب</div>
            @endif
            <form method="POST" action="{{ route('admin.sections.store', $classroom) }}" class="flex gap-3 items-end">
                @csrf
                <div class="flex-1">
                    <input type="text" name="name" required placeholder="اسم الشعبة"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg shrink-0 text-sm">إضافة شعبة</button>
            </form>
        </div>
    </div>
@empty
    <div class="bg-white rounded-xl shadow p-6 text-center text-gray-500">لا توجد صفوف</div>
@endforelse
@endsection
