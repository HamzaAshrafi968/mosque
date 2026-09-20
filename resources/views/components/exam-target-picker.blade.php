@props([
    'studySessions' => collect(),
    'classrooms' => collect(),
    'oldMode' => null,
    'oldStudySessionId' => null,
    'oldClassrooms' => [],
    'oldSectionId' => null,
    'oldClassroomId' => null,
])

@php
    $selectedClassrooms = array_values(array_filter(array_map('strval', (array) $oldClassrooms)));
    if ($selectedClassrooms === [] && filled($oldClassroomId)) {
        $selectedClassrooms = [(string) $oldClassroomId];
    }
    $mode = $oldMode ?: ($selectedClassrooms === [] && filled($oldStudySessionId) ? 'shift' : 'classrooms');
@endphp

<div class="border border-gray-200 rounded-xl p-4 space-y-4" data-exam-target-picker>
    <div>
        <span class="block text-sm font-medium text-gray-700 mb-2">الفئة المستهدفة <span class="text-red-500">*</span></span>
        <div class="grid gap-2 sm:grid-cols-2">
            <label class="flex items-start gap-2 border border-gray-300 rounded-lg px-3 py-2 cursor-pointer has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50">
                <input type="radio" name="target_mode" value="shift" @checked($mode === 'shift') data-exam-target-mode class="mt-0.5 text-emerald-700">
                <span>
                    <span class="block font-bold text-gray-800 text-sm">دوام كامل</span>
                    <span class="block text-xs text-gray-500">كل طلاب الدوام المحدد</span>
                </span>
            </label>
            <label class="flex items-start gap-2 border border-gray-300 rounded-lg px-3 py-2 cursor-pointer has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50">
                <input type="radio" name="target_mode" value="classrooms" @checked($mode === 'classrooms') data-exam-target-mode class="mt-0.5 text-emerald-700">
                <span>
                    <span class="block font-bold text-gray-800 text-sm">صفوف محددة</span>
                    <span class="block text-xs text-gray-500">صف واحد أو أكثر مع إمكانية تحديد شعبة</span>
                </span>
            </label>
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">الدوام</label>
        <select name="study_session_id" data-exam-target-session class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            <option value="">كل الدوامات</option>
            @foreach($studySessions as $session)
                <option value="{{ $session->id }}" @selected((string) $oldStudySessionId === (string) $session->id)>{{ $session->display_name }}</option>
            @endforeach
        </select>
        <p class="text-xs text-gray-500 mt-1">في وضع «دوام كامل» يحدد هذا الدوام الطلاب المستهدفين، وفي وضع «صفوف محددة» يفلتر قائمة الصفوف.</p>
    </div>

    <div data-exam-target-classrooms>
        <span class="block text-sm font-medium text-gray-700 mb-1">الصفوف <span class="text-red-500">*</span></span>
        @if($classrooms->isEmpty())
            <p class="text-xs text-gray-500">لا توجد صفوف بعد.</p>
        @else
            <div class="grid gap-2 sm:grid-cols-2 max-h-56 overflow-y-auto border border-gray-100 rounded-lg p-2">
                @foreach($classrooms as $classroom)
                    <label class="flex items-center gap-2 text-sm border border-gray-200 rounded-lg px-3 py-2 cursor-pointer"
                           data-exam-target-classroom
                           data-session="{{ $classroom->study_session_id }}">
                        <input type="checkbox" name="classroom_ids[]" value="{{ $classroom->id }}"
                               @checked(in_array((string) $classroom->id, $selectedClassrooms, true))
                               data-exam-target-classroom-input
                               class="rounded border-gray-300 text-emerald-700">
                        <span class="text-gray-800">{{ $classroom->name }}</span>
                        @if($classroom->studySession)
                            <span class="text-xs text-gray-400">({{ $classroom->studySession->display_name }})</span>
                        @endif
                    </label>
                @endforeach
            </div>
        @endif
    </div>

    <div data-exam-target-section>
        <label class="block text-sm font-medium text-gray-700 mb-1">الشعبة</label>
        <select name="section_id" data-exam-target-section-select class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            <option value="">كل الشعب</option>
            @foreach($classrooms as $classroom)
                @foreach($classroom->sections as $section)
                    <option value="{{ $section->id }}" data-classroom="{{ $classroom->id }}"
                            @selected((string) $oldSectionId === (string) $section->id)>
                        {{ $classroom->name }} - {{ $section->name }}
                    </option>
                @endforeach
            @endforeach
        </select>
        <p class="text-xs text-gray-500 mt-1">تُحدد الشعبة عند اختيار صف واحد فقط؛ عند اختيار عدة صفوف يظهر الاختبار لكل شعبه.</p>
    </div>
</div>
