@extends('layouts.app')

@section('title', 'إضافة طالب')

@section('content')
<div class="bg-white rounded-xl shadow overflow-hidden p-6 max-w-2xl">
    <form method="POST" action="{{ route('admin.students.store') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الاسم <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name') }}" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الجنس <span class="text-red-500">*</span></label>
            <select name="gender" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="male" @selected(old('gender') === 'male')>ذكر</option>
                <option value="female" @selected(old('gender') === 'female')>أنثى</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ الميلاد</label>
            <input type="date" name="birth_date" value="{{ old('birth_date') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الصف</label>
            <select name="classroom_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="">اختر الصف</option>
                @foreach($classrooms as $classroom)
                    <option value="{{ $classroom->id }}" @selected(old('classroom_id') == $classroom->id)>{{ $classroom->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الشعبة</label>
            <select name="section_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="">اختر الشعبة</option>
                @foreach($classrooms as $classroom)
                    @foreach($classroom->sections as $section)
                        <option value="{{ $section->id }}" @selected(old('section_id') == $section->id)>{{ $classroom->name }} - {{ $section->name }}</option>
                    @endforeach
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">اسم ولي الأمر</label>
            <input type="text" name="guardian_name" value="{{ old('guardian_name') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">هاتف ولي الأمر</label>
            <input type="text" name="guardian_phone" value="{{ old('guardian_phone') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ملاحظات</label>
            <textarea name="notes" rows="3"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">{{ old('notes') }}</textarea>
        </div>
        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">حفظ</button>
    </form>
</div>
@endsection
