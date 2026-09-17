@extends('layouts.app')

@section('title', $exam->title)

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="bg-white rounded-xl shadow p-5 mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-800">{{ $exam->title }}</h1>
            <p class="text-sm text-gray-500 mt-1">
                {{ $exam->subject?->name }} — {{ $exam->total_marks }} درجة
                @if($exam->pass_marks !== null) — النجاح من {{ $exam->pass_marks }} @endif
            </p>
        </div>
        <div class="text-center">
            <div class="text-xs text-gray-400 mb-1">الوقت المتبقي</div>
            <div id="exam-timer" data-exam-timer data-remaining="{{ $remainingSeconds ?? '' }}" data-submit-form="#exam-form"
                 class="text-2xl font-bold text-gray-800 tabular-nums">—</div>
        </div>
    </div>

    <form method="POST" action="{{ route('student.exams.submit', $exam) }}" id="exam-form" onsubmit="return confirm('هل أنت متأكد من تسليم الامتحان؟')">
        @csrf

        @foreach($questions as $index => $question)
            <div class="bg-white rounded-xl shadow p-5 mb-4">
                <div class="flex items-start justify-between gap-2 mb-3">
                    <h2 class="font-bold text-gray-800">{{ $index + 1 }}. {{ $question->text }}</h2>
                    <span class="text-xs text-gray-400 whitespace-nowrap">{{ $question->marks }} درجة</span>
                </div>

                @if($question->type->value === 'mcq')
                    <div class="space-y-2">
                        @foreach($question->optionsList() as $option)
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="radio" name="answers[{{ $question->id }}][selected_options][]" value="{{ $option }}"
                                       class="border-gray-300 text-emerald-700 focus:ring-emerald-500">
                                {{ $option }}
                            </label>
                        @endforeach
                    </div>
                @elseif($question->type->value === 'checkbox')
                    <div class="space-y-2">
                        @foreach($question->optionsList() as $option)
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="answers[{{ $question->id }}][selected_options][]" value="{{ $option }}"
                                       class="rounded border-gray-300 text-emerald-700 focus:ring-emerald-500">
                                {{ $option }}
                            </label>
                        @endforeach
                    </div>
                @elseif($question->type->value === 'true_false')
                    <div class="flex gap-6">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="radio" name="answers[{{ $question->id }}][answer_text]" value="true"
                                   class="border-gray-300 text-emerald-700 focus:ring-emerald-500"> صح
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="radio" name="answers[{{ $question->id }}][answer_text]" value="false"
                                   class="border-gray-300 text-emerald-700 focus:ring-emerald-500"> خطأ
                        </label>
                    </div>
                @elseif($question->type->value === 'short')
                    <input type="text" name="answers[{{ $question->id }}][answer_text]" maxlength="5000"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                           placeholder="اكتب إجابتك">
                @else
                    <textarea name="answers[{{ $question->id }}][answer_text]" rows="4" maxlength="5000"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                              placeholder="اكتب إجابتك"></textarea>
                @endif
            </div>
        @endforeach

        <div class="bg-white rounded-xl shadow p-5 mb-8 flex items-center justify-between gap-3">
            <p class="text-xs text-gray-400">عند انتهاء الوقت يُسلَّم الامتحان تلقائياً.</p>
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-6 py-2 rounded-lg">تسليم الامتحان</button>
        </div>
    </form>
</div>
@endsection
