@extends('layouts.app')

@section('title', $exam->title)

@php
    $canEdit = $exam->canEdit();
    $isPublished = $exam->status === \App\Enums\ExamStatus::Published;
    $isClosed = $exam->status === \App\Enums\ExamStatus::Closed;
    $authorization = app(\App\Services\AuthorizationService::class);
    $can = fn (string $permission) => $authorization->can(auth()->user(), $permission);
@endphp

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">{{ $exam->title }}</h1>
        <p class="text-sm text-gray-500 mt-1">
            {{ $exam->kind?->label() }} — {{ $exam->subject?->name }} —
            {{ $exam->targetLabel() }} —
            {{ $exam->exam_date?->toDateString() }}
            @if($exam->duration_minutes) — المدة {{ $exam->duration_minutes }} دقيقة @endif
        </p>
    </div>
    <div class="flex items-center gap-2">
        <span class="text-xs px-3 py-1 rounded-lg
            {{ $exam->status === \App\Enums\ExamStatus::Published ? 'bg-emerald-100 text-emerald-700' : ($exam->status === \App\Enums\ExamStatus::Closed ? 'bg-gray-200 text-gray-600' : 'bg-amber-100 text-amber-700') }}">
            {{ $exam->status?->label() }}
        </span>
        <span class="text-xs px-3 py-1 rounded-lg bg-indigo-100 text-indigo-700">{{ $exam->mode?->label() }}</span>
        <a href="{{ route($routePrefix.'.exams.index') }}" class="text-sm text-gray-500 hover:underline">عودة</a>
    </div>
</div>

@if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl p-3 mb-4 text-sm">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 mb-4 text-sm">
        <ul class="list-disc pr-5 space-y-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- ===================== شريط الإجراءات ===================== --}}
<div class="bg-white rounded-xl shadow p-4 mb-6 flex flex-wrap items-center gap-3">
    @unless($isPublished || $isClosed)
        @if ($can('exams.publish'))
            <form method="POST" action="{{ route($routePrefix.'.exams.publish', $exam) }}">
                @csrf
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">
                    نشر الامتحان
                </button>
            </form>
            <p class="text-xs text-gray-500">شروط النشر: سؤال واحد على الأقل أو ملف PDF، ومجموع علامات الأسئلة = {{ $exam->total_marks }}.</p>
        @endif
    @endunless

    @if($isPublished && $can('exams.publish'))
        <form method="POST" action="{{ route($routePrefix.'.exams.close', $exam) }}">
            @csrf
            <button type="submit" class="bg-gray-700 hover:bg-gray-800 text-white font-bold px-4 py-2 rounded-lg">إغلاق الامتحان</button>
        </form>
    @endif

    @if($exam->attachment_key)
        <a href="{{ route($routePrefix.'.exams.attachment', $exam) }}"
           class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-4 py-2 rounded-lg">
            تحميل ملف الامتحان (PDF)
        </a>
        @if ($can('exams.update'))
            <form method="POST" action="{{ route($routePrefix.'.exams.attachment.destroy', $exam) }}" onsubmit="return confirm('حذف ملف الامتحان؟')">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-red-600 hover:underline text-sm">حذف الملف</button>
            </form>
        @endif
    @elseif ($can('exams.update'))
        <form method="POST" action="{{ route($routePrefix.'.exams.attachment.store', $exam) }}" enctype="multipart/form-data" class="flex items-center gap-2">
            @csrf
            <input type="file" name="attachment" accept="application/pdf" required class="text-sm">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2 rounded-lg text-sm">رفع PDF</button>
        </form>
    @endif
</div>

{{-- ===================== بناء الأسئلة ===================== --}}
@if($canEdit && $can('exams.update'))
    <form method="POST" action="{{ route($routePrefix.'.exams.questions.store', $exam) }}" id="questions-form">
        @csrf
        <x-exam-question-builder
            :types="$questionTypes"
            :old-questions="old('questions')"
            :old-type="old('type')"
            title="إضافة أسئلة (1–100 سؤالاً بأنواع متعددة)" />

        <div class="mb-6">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">حفظ الأسئلة</button>
        </div>
    </form>
@else
    <div class="bg-amber-50 border border-amber-200 text-amber-700 rounded-xl p-3 mb-6 text-sm">
        لا يمكن تعديل الامتحان أو أسئلته بعد بدء محاولات الطلاب.
    </div>
@endif

{{-- ===================== قائمة الأسئلة ===================== --}}
<div class="bg-white rounded-xl shadow overflow-hidden mb-6">
    <h2 class="text-lg font-bold text-gray-800 p-4 border-b">الأسئلة ({{ $questions->count() }}) — مجموع العلامات {{ $exam->questionsTotalMarks() }}/{{ $exam->total_marks }}</h2>
    <div class="divide-y">
        @forelse($questions as $index => $question)
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
                        @if($canEdit && $can('exams.update'))
                            <details class="inline-block">
                                <summary class="cursor-pointer text-xs text-indigo-600 hover:underline">تعديل</summary>
                                <form method="POST" action="{{ route($routePrefix.'.exams.questions.update', $question) }}" class="mt-2 w-80 space-y-2 border rounded-lg p-3">
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
                            <form method="POST" action="{{ route($routePrefix.'.exams.questions.destroy', $question) }}" onsubmit="return confirm('حذف السؤال؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline text-xs">حذف</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <p class="p-6 text-center text-gray-400 text-sm">لا توجد أسئلة بعد</p>
        @endforelse
    </div>
</div>

{{-- ===================== النتائج ===================== --}}
<div class="bg-white rounded-xl shadow overflow-hidden">
    <h2 class="text-lg font-bold text-gray-800 p-4 border-b">النتائج ({{ $attempts->count() }} محاولة)</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-gray-600">
                    <th class="px-4 py-3 text-right whitespace-nowrap">الطالب</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">الحالة</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">الدرجة</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">النتيجة</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">تصحيح / مراجعة</th>
                </tr>
            </thead>
            <tbody>
                @forelse($roster as $student)
                    @php $attempt = $attempts->get($student->id); @endphp
                    <tr>
                        <td class="px-4 py-3 border-t whitespace-nowrap font-bold">{{ $student->name }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $attempt?->statusLabel() ?? 'لم يبدأ' }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">
                            {{ $attempt?->score !== null ? $attempt->score.'/'.$exam->total_marks : '—' }}
                        </td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">
                            @if($attempt && $attempt->score !== null && $exam->pass_marks !== null)
                                <span class="text-xs px-2 py-0.5 rounded-lg {{ $exam->passed((float) $attempt->score) ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                    {{ $exam->passed((float) $attempt->score) ? 'ناجح' : 'راسب' }}
                                </span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 border-t">
                            @if($attempt)
                                <details>
                                    <summary class="cursor-pointer text-xs text-indigo-600 hover:underline">عرض / تصحيح</summary>
                                    <div class="mt-2 space-y-3 w-96 max-w-full">
                                        @if($attempt->isFinished() && $attempt->status === 'submitted')
                                            <form method="POST" action="{{ route($routePrefix.'.exams.attempts.grade', $attempt) }}" class="flex items-center gap-2">
                                                @csrf
                                                <input type="number" name="score" step="0.5" min="0" max="{{ $exam->total_marks }}" required
                                                       value="{{ $attempt->score }}" class="w-28 border border-gray-300 rounded px-2 py-1 text-sm">
                                                <button type="submit" class="bg-emerald-700 text-white rounded px-3 py-1 text-xs">حفظ التصحيح</button>
                                            </form>
                                        @endif
                                        @foreach($attempt->answers as $answer)
                                            <div class="border rounded-lg p-2 text-xs">
                                                <div class="font-bold text-gray-700">{{ $answer->question?->text }}</div>
                                                <div class="mt-1">
                                                    إجابة الطالب:
                                                    <span class="{{ $answer->is_correct === true ? 'text-emerald-700' : ($answer->is_correct === false ? 'text-red-600' : 'text-gray-600') }}">
                                                        {{ $answer->selectedList() !== [] ? implode(' / ', $answer->selectedList()) : ($answer->answer_text ?? '—') }}
                                                    </span>
                                                </div>
                                                @if($answer->question)
                                                    <div class="text-gray-500 mt-0.5">
                                                        الإجابة الصحيحة:
                                                        {{ $answer->question->type->value === 'checkbox'
                                                            ? implode(' / ', $answer->question->correctOptions())
                                                            : ($answer->question->correct_answer ?? '—') }}
                                                    </div>
                                                @endif
                                                <div class="text-gray-500 mt-0.5">العلامة: {{ $answer->marks_awarded ?? '—' }} / {{ $answer->question?->marks }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            @else
                                <span class="text-gray-300 text-xs">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">لا يوجد طلاب في صف/شعبة هذا الامتحان</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
