@extends('layouts.app')

@section('title', 'إنشاء اختبار')

@section('content')
<div class="bg-white rounded-xl shadow overflow-hidden p-6 max-w-2xl">
    <form method="POST" action="{{ route('admin.exams.store') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">العنوان <span class="text-red-500">*</span></label>
            <input type="text" name="title" value="{{ old('title') }}" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">المادة <span class="text-red-500">*</span></label>
            <select name="subject_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="">اختر المادة</option>
                @foreach($subjects as $subject)
                    <option value="{{ $subject->id }}" @selected(old('subject_id') == $subject->id)>{{ $subject->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الصف <span class="text-red-500">*</span></label>
            <select name="classroom_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="">اختر الصف</option>
                @foreach($classrooms as $classroom)
                    <option value="{{ $classroom->id }}" @selected(old('classroom_id') == $classroom->id)>{{ $classroom->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الشعبة</label>
            <select name="section_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="">كل الشعب</option>
                @foreach($classrooms as $classroom)
                    @foreach($classroom->sections as $section)
                        <option value="{{ $section->id }}" @selected(old('section_id') == $section->id)>{{ $classroom->name }} - {{ $section->name }}</option>
                    @endforeach
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ الاختبار <span class="text-red-500">*</span></label>
            <input type="date" name="exam_date" required value="{{ old('exam_date') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الدرجة الكلية <span class="text-red-500">*</span></label>
            <input type="number" name="total_marks" required value="{{ old('total_marks', 100) }}" min="1" max="1000"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">علامة النجاح</label>
            <input type="number" name="pass_marks" value="{{ old('pass_marks', 50) }}" min="0"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            <p class="text-xs text-gray-500 mt-1">الطالب الذي يحصل على هذه الدرجة أو أكثر يعتبر ناجحاً</p>
        </div>
        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">إنشاء</button>
    </form>
</div>
@endsection
