@extends('layouts.app')

@section('title', 'إنشاء واجب')

@section('content')
<div class="max-w-3xl">
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="p-6">
            <form method="POST" action="{{ route('teacher.homeworks.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">العنوان</label>
                    <input type="text" name="title" value="{{ old('title') }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الوصف</label>
                    <textarea name="description" rows="3"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">{{ old('description') }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">المادة</label>
                    <select name="subject_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <option value="">اختر المادة</option>
                        @foreach($subjects as $subject)
                            <option value="{{ $subject->id }}" @selected(old('subject_id') == $subject->id)>{{ $subject->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الصف</label>
                    <select name="classroom_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <option value="">اختر الصف</option>
                        @foreach($classrooms as $classroom)
                            <option value="{{ $classroom->id }}" @selected(old('classroom_id') == $classroom->id)>{{ $classroom->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الشعبة</label>
                    <select name="section_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <option value="">كل الشعب</option>
                        @foreach($classrooms as $classroom)
                            @foreach($classroom->sections as $section)
                                <option value="{{ $section->id }}" @selected(old('section_id') == $section->id)>{{ $classroom->name }} - {{ $section->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ التسليم</label>
                        <input type="date" name="due_date" value="{{ old('due_date') }}" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ملف مرفق (اختياري)</label>
                        <input type="file" name="attachment"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الدرجة الكلية <span class="text-gray-400 text-xs">(تُملأ تلقائياً مع الأسئلة)</span></label>
                        <input type="number" name="total_marks" value="{{ old('total_marks') }}" min="0" step="0.5"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">علامة النجاح</label>
                        <input type="number" name="pass_marks" value="{{ old('pass_marks', 50) }}" min="0"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <p class="text-xs text-gray-500 mt-1">الطالب الذي يحصل على هذه الدرجة أو أكثر يعتبر ناجحاً</p>
                    </div>
                </div>

                <x-exam-question-builder
                    :types="$questionTypes"
                    :old-questions="old('questions')"
                    :old-type="old('type')"
                    title="أسئلة الواجب (تصحيح آلي)"
                    hint="اختياري — أضف أسئلة مع إجاباتها الصحيحة ليُصحح الواجب آلياً، ويمكن إضافتها لاحقاً من صفحة الواجب"
                    :plain="true" />

                <div class="pt-2">
                    <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">إنشاء الواجب</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
