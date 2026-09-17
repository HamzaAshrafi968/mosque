@extends('layouts.app')

@section('title', 'نتيجة '.$exam->title)

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="bg-white rounded-xl shadow p-5 mb-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-gray-800">{{ $exam->title }}</h1>
                <p class="text-sm text-gray-500 mt-1">
                    {{ $exam->subject?->name }} — {{ $attempt->statusLabel() }}
                    @if($attempt->submitted_at) — سُلِّم {{ $attempt->submitted_at->format('Y-m-d H:i') }} @endif
                </p>
            </div>
            <div class="text-center">
                @if($attempt->score !== null)
                    <div class="text-3xl font-bold {{ $exam->passed((float) $attempt->score) ? 'text-emerald-600' : 'text-red-500' }}">
                        {{ $attempt->score }}<span class="text-lg text-gray-400">/{{ $exam->total_marks }}</span>
                    </div>
                    @if($exam->pass_marks !== null)
                        <span class="text-xs px-2 py-0.5 rounded-lg {{ $exam->passed((float) $attempt->score) ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                            {{ $exam->passed((float) $attempt->score) ? 'ناجح' : 'راسب' }}
                        </span>
                    @endif
                @else
                    <div class="text-sm text-amber-600 font-bold">بانتظار التصحيح</div>
                @endif
            </div>
        </div>
    </div>

    <a href="{{ route('student.exams') }}" class="text-sm text-emerald-700 hover:underline mb-4 inline-block">عودة للامتحانات</a>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <h2 class="text-lg font-bold text-gray-800 p-4 border-b">مراجعة الإجابات</h2>
        <div class="divide-y">
            @forelse($questions as $index => $question)
                @php $answer = $attempt->answers->firstWhere('question_id', $question->id); @endphp
                <div class="p-4">
                    <div class="font-bold text-gray-800">{{ $index + 1 }}. {{ $question->text }}</div>
                    <div class="text-sm mt-2">
                        <span class="text-gray-500">إجابتك:</span>
                        <span class="{{ $answer?->is_correct === true ? 'text-emerald-700 font-bold' : ($answer?->is_correct === false ? 'text-red-600 font-bold' : 'text-gray-700') }}">
                            @if($answer === null)
                                —
                            @elseif($answer->selectedList() !== [])
                                {{ implode(' / ', $answer->selectedList()) }}
                            @else
                                {{ $answer->answer_text ?: '—' }}
                            @endif
                        </span>
                    </div>
                    <div class="text-sm text-gray-500 mt-1">
                        <span>الإجابة الصحيحة:</span>
                        <span class="text-gray-700">
                            @if($question->type->value === 'checkbox')
                                {{ implode(' / ', $question->correctOptions()) }}
                            @elseif($question->type->value === 'true_false')
                                {{ $question->correct_answer === 'true' ? 'صح' : 'خطأ' }}
                            @else
                                {{ $question->correct_answer ?: 'يُصحح يدوياً' }}
                            @endif
                        </span>
                    </div>
                    @if($answer?->marks_awarded !== null)
                        <div class="text-xs text-gray-400 mt-1">العلامة: {{ $answer->marks_awarded }} / {{ $question->marks }}</div>
                    @endif
                </div>
            @empty
                <p class="p-6 text-center text-gray-400 text-sm">لا توجد أسئلة لهذا الامتحان</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
