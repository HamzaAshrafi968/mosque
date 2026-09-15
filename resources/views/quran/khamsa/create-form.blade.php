@php
    $teacherOptions = $teachers ?? collect();
    $studentShift = old('study_session_id', $selectedStudent->study_session_id ?? $currentSessionId);
@endphp

<div class="space-y-6">
    <form method="GET" action="{{ $pickerRoute }}" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-gray-700 mb-1">الطالب <span class="text-red-500">*</span></label>
                <select name="student_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">— اختر الطالب —</option>
                    @foreach($students as $student)
                        <option value="{{ $student->id }}" @selected($selectedStudent && $selectedStudent->id === $student->id)>{{ $student->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <button type="submit" class="w-full bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">عرض الخمسات المتاحة</button>
            </div>
        </div>
    </form>

    @if($selectedStudent)
        <form method="POST" action="{{ $storeRoute }}" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-6">
            @csrf
            <input type="hidden" name="student_id" value="{{ $selectedStudent->id }}">

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @if($teacherOptions->count() > 1)
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">الأستاذ المسؤول <span class="text-red-500">*</span></label>
                        <select name="teacher_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                            <option value="">— اختر الأستاذ —</option>
                            @foreach($teacherOptions as $teacher)
                                <option value="{{ $teacher->id }}" @selected(old('teacher_id') === $teacher->id)>{{ $teacher->name }}</option>
                            @endforeach
                        </select>
                        @error('teacher_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                @elseif($teacherOptions->count() === 1)
                    <input type="hidden" name="teacher_id" value="{{ $teacherOptions->first()->id }}">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">الأستاذ المسؤول</label>
                        <div class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-gray-700">{{ $teacherOptions->first()->name }}</div>
                    </div>
                @else
                    <div class="md:col-span-3">
                        <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">لا يوجد أستاذ متاح في هذا الدوام — أضف أستاذاً أولاً.</p>
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">الدوام <span class="text-red-500">*</span></label>
                    <select name="study_session_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        <option value="">— اختر الدوام —</option>
                        @foreach($sessions as $session)
                            <option value="{{ $session->id }}" @selected($studentShift === $session->id)>{{ $session->name }}</option>
                        @endforeach
                    </select>
                    @error('study_session_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">تاريخ التخصيص <span class="text-red-500">*</span></label>
                    <input type="date" name="assigned_at" required value="{{ old('assigned_at', now()->toDateString()) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">تاريخ الاستحقاق (اختياري)</label>
                    <input type="date" name="due_date" value="{{ old('due_date') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>

                <div class="md:col-span-3">
                    <label class="block text-sm font-bold text-gray-700 mb-1">ملاحظات</label>
                    <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2">{{ old('notes') }}</textarea>
                </div>
            </div>

            @include('quran.khamsa.memorization-panel', [
                'student' => $selectedStudent,
                'memorizedJuz' => $memorizedJuz,
                'storeRoute' => $memorizationStoreRoute,
                'destroyRoute' => $memorizationDestroyRoute,
            ])

            <div>
                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                    <h3 class="font-bold text-gray-800">اختر الخمسات</h3>
                    <span class="text-xs text-gray-400">كل خمسة ٥ صفحات — مثال: ٣ خمسات = ١٥ صفحة</span>
                </div>
                @error('items')
                    <p class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2 mb-3">{{ $message }}</p>
                @enderror
                <x-khamsa-picker :khamsat="$khamsat" :selected="old('items', [])" />
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-8 py-2.5 rounded-xl">حفظ المراجعة</button>
                <a href="{{ $indexRoute }}" class="text-gray-500 text-sm hover:underline">إلغاء</a>
            </div>
        </form>
    @else
        <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-8 text-center text-gray-400">
            اختر الطالب أولاً لعرض خمساته المتاحة وحالة القفل.
        </div>
    @endif
</div>
