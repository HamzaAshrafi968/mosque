@extends('layouts.app')

@section('title', 'تعديل واجب')

@section('content')
<div class="max-w-3xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('teacher.homeworks.index') }}" class="text-sm text-emerald-700 hover:text-emerald-800">← الواجبات</a>
            <h1 class="text-2xl font-bold text-gray-800 mt-1">{{ $homework->title }}</h1>
        </div>
        <a href="{{ route('teacher.homeworks.submissions', $homework) }}" class="bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-bold px-4 py-2 rounded-lg">صفحة التصحيح</a>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="p-6">
            <form method="POST" action="{{ route('teacher.homeworks.update', $homework) }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">العنوان</label>
                    <input type="text" name="title" value="{{ old('title', $homework->title) }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الوصف</label>
                    <textarea name="description" rows="3"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">{{ old('description', $homework->description) }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">المادة</label>
                    <select name="subject_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <option value="">اختر المادة</option>
                        @foreach($subjects as $subject)
                            <option value="{{ $subject->id }}" @selected(old('subject_id', $homework->subject_id) == $subject->id)>{{ $subject->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الصف</label>
                    <select name="classroom_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <option value="">اختر الصف</option>
                        @foreach($classrooms as $classroom)
                            <option value="{{ $classroom->id }}" @selected(old('classroom_id', $homework->classroom_id) == $classroom->id)>{{ $classroom->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الشعبة</label>
                    <select name="section_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <option value="">كل الشعب</option>
                        @foreach($classrooms as $classroom)
                            @foreach($classroom->sections as $section)
                                <option value="{{ $section->id }}" @selected(old('section_id', $homework->section_id) == $section->id)>{{ $classroom->name }} - {{ $section->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ التسليم</label>
                        <input type="date" name="due_date" value="{{ old('due_date', $homework->due_date?->format('Y-m-d')) }}" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ملف مرفق (اختياري)</label>
                        <input type="file" name="attachment"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        @if($homework->attachment_path)
                            <p class="text-xs text-gray-500 mt-1">يوجد ملف مرفق حالياً — رفع ملف جديد يستبدله.</p>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الدرجة الكلية</label>
                        <input type="number" name="total_marks" value="{{ old('total_marks', $homework->total_marks !== null ? (float) $homework->total_marks : '') }}" min="0" step="0.5"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">علامة النجاح</label>
                        <input type="number" name="pass_marks" value="{{ old('pass_marks', $homework->pass_marks) }}" min="0"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">حفظ التعديلات</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================== قائمة الأسئلة الحالية ===================== --}}
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <h2 class="text-lg font-bold text-gray-800 p-4 border-b">
            الأسئلة ({{ $homework->questions->count() }}) — مجموع العلامات {{ $homework->questionsTotalMarks() }}@if($homework->total_marks !== null)/{{ (float) $homework->total_marks }}@endif
        </h2>
        <div class="divide-y">
            @forelse($homework->questions as $index => $question)
                <div class="p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-bold text-gray-800">{{ $index + 1 }}. {{ $question->text }}</div>
                            <div class="text-xs text-gray-500 mt-1">
                                {{ $question->type->label() }} — {{ $question->marks }} درجة
                                @if($question->type->hasOptions())
                                    — الخيارات: {{ implode(' / ', $question->optionsList()) }}
                                @endif
                                @if($question->type->value === 'checkbox')
                                    — الإجابات الصحيحة: {{ implode(' / ', $question->correctOptions()) }}
                                @elseif($question->correct_answer !== null)
                                    — الإجابة الصحيحة: {{ $question->correct_answer }}
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2 whitespace-nowrap">
                            <details class="inline-block">
                                <summary class="cursor-pointer text-xs text-indigo-600 hover:underline">تعديل</summary>
                                <form method="POST" action="{{ route('teacher.homeworks.questions.update', $question) }}" class="mt-2 w-80 space-y-2 border rounded-lg p-3">
                                    @csrf
                                    @method('PUT')
                                    <textarea name="text" required class="w-full border border-gray-300 rounded px-2 py-1 text-sm" rows="2">{{ $question->text }}</textarea>
                                    <input type="number" name="marks" value="{{ (float) $question->marks }}" step="0.5" min="0" required class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                                    @if($question->type->hasOptions())
                                        <div class="space-y-1">
                                            @foreach(array_pad($question->optionsList(), 4, '') as $optionIndex => $option)
                                                <input type="text" name="options[]" value="{{ $option }}" placeholder="الخيار {{ $optionIndex + 1 }}" class="w-full border border-gray-300 rounded px-2 py-1 text-xs">
                                            @endforeach
                                        </div>
                                        @if($question->type->value === 'mcq')
                                            <select name="correct_answer" required class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                                                <option value="">اختر الإجابة الصحيحة</option>
                                                @foreach($question->optionsList() as $optionIndex => $option)
                                                    <option value="{{ $optionIndex }}" @selected($question->correct_answer === $option)>{{ $option }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <div class="text-xs text-gray-500">حدد الإجابات الصحيحة:</div>
                                            @foreach($question->optionsList() as $optionIndex => $option)
                                                <label class="flex items-center gap-2 text-xs">
                                                    <input type="checkbox" name="correct_options[]" value="{{ $optionIndex }}"
                                                           @checked(in_array($option, $question->correctOptions(), true))
                                                           class="rounded border-gray-300 text-emerald-700">
                                                    {{ $option }}
                                                </label>
                                            @endforeach
                                        @endif
                                    @elseif($question->type->value === 'true_false')
                                        <select name="correct_answer" required class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                                            <option value="true" @selected($question->correct_answer === 'true')>صح</option>
                                            <option value="false" @selected($question->correct_answer === 'false')>خطأ</option>
                                        </select>
                                    @elseif($question->type->value === 'short')
                                        <input type="text" name="correct_answer" value="{{ $question->correct_answer }}" placeholder="الإجابة المتوقعة (اختياري)" class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                                    @endif
                                    <button type="submit" class="bg-indigo-600 text-white rounded px-3 py-1 text-xs">حفظ التعديل</button>
                                </form>
                            </details>
                            <form method="POST" action="{{ route('teacher.homeworks.questions.destroy', $question) }}" onsubmit="return confirm('حذف السؤال؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline text-xs">حذف</button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <p class="p-6 text-center text-gray-400 text-sm">لا توجد أسئلة بعد — أضف أسئلة من الأسفل ليُصحح الواجب آلياً</p>
            @endforelse
        </div>
    </div>

    {{-- ===================== إضافة أسئلة جديدة ===================== --}}
    <form method="POST" action="{{ route('teacher.homeworks.questions.store', $homework) }}">
        @csrf
        <x-exam-question-builder
            :types="$questionTypes"
            :old-questions="old('questions')"
            :old-type="old('type')"
            title="إضافة أسئلة جديدة"
            hint="تُضاف إلى أسئلة الواجب الحالية وتُحدَّث الدرجة الكلية تلقائياً" />

        <div class="mb-2">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">حفظ الأسئلة</button>
        </div>
    </form>
</div>
@endsection
