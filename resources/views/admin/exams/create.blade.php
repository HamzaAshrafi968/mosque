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
        <x-exam-target-picker
            :study-sessions="$studySessions"
            :classrooms="$classrooms"
            :old-mode="old('target_mode')"
            :old-study-session-id="old('study_session_id')"
            :old-classrooms="old('classroom_ids', [])"
            :old-section-id="old('section_id')"
            :old-classroom-id="old('classroom_id')" />
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">النوع <span class="text-red-500">*</span></label>
            <select name="kind" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="exam" @selected(old('kind', 'exam') === 'exam')>امتحان</option>
                <option value="quiz" @selected(old('kind') === 'quiz')>مذاكرة</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">طريقة الأداء <span class="text-red-500">*</span></label>
            <select name="mode" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="onsite" @selected(old('mode', 'onsite') === 'onsite')>حضوري</option>
                <option value="online" @selected(old('mode') === 'online')>إلكتروني (أسئلة داخل النظام)</option>
                <option value="hybrid" @selected(old('mode') === 'hybrid')>هجين</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">مدة الامتحان (دقائق) <span class="text-gray-400 text-xs">اختياري — للمؤقت والتسليم التلقائي</span></label>
            <input type="number" name="duration_minutes" value="{{ old('duration_minutes') }}" min="1" max="600"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
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
        <x-exam-question-builder
            :types="$questionTypes"
            :old-questions="old('questions')"
            :old-type="old('type')"
            title="الأسئلة"
            hint="اختياري — يمكن إضافتها لاحقاً من صفحة الامتحان"
            :plain="true" />

        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">إنشاء</button>
    </form>
</div>
@endsection
