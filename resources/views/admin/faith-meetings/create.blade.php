@extends('layouts.app')

@section('title', 'إنشاء لقاء إيماني')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <a href="{{ route('admin.faith-meetings.index') }}" class="text-sm text-emerald-700 hover:text-emerald-800">← اللقاءات الإيمانية</a>
    <h2 class="text-2xl font-extrabold text-gray-800">إنشاء لقاء إيماني</h2>

    @if($preset)
        <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-4 py-3 text-sm flex items-center gap-2">
            ⚡ نموذج مستخدم: <b>{{ $preset->title }}</b> — البيانات التالية معبأة مسبقاً
        </div>
    @endif

    <form method="POST" action="{{ route('admin.faith-meetings.store') }}" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-5">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-gray-700 mb-1">عنوان اللقاء <span class="text-red-500">*</span></label>
                <input type="text" name="title" required value="{{ old('title', $preset?->title) }}" placeholder="مثال: لقاء التزكية الأسبوعي" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">المشرف</label>
                <select name="supervisor_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">— اختر المشرف —</option>
                    @foreach($teachers as $teacher)
                        <option value="{{ $teacher->id }}" @selected(old('supervisor_id') === $teacher->id)>{{ $teacher->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">معلم آخر (اختياري)</label>
                <select name="teacher_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">— بدون —</option>
                    @foreach($teachers as $teacher)
                        <option value="{{ $teacher->id }}" @selected(old('teacher_id') === $teacher->id)>{{ $teacher->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">التاريخ <span class="text-red-500">*</span></label>
                <input type="date" name="date" required value="{{ old('date', now()->toDateString()) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">البداية</label>
                    <input type="time" name="start_time" value="{{ old('start_time') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">النهاية</label>
                    <input type="time" name="end_time" value="{{ old('end_time') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">المكان</label>
                <input type="text" name="location" value="{{ old('location', $preset?->location) }}" placeholder="مثال: قاعة الجامع الكبرى" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-gray-700 mb-1">الوصف</label>
                <textarea name="description" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2">{{ old('description', $preset?->description) }}</textarea>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-gray-700 mb-1">ملاحظات عامة</label>
                <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2">{{ old('notes', $preset?->notes) }}</textarea>
            </div>
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">
                اختيار الطلاب المشاركين ({{ $students->count() }} طالباً)
                <span class="text-xs text-gray-400 font-normal">— الطلاب المحددون فقط يُرفقون باللقاء</span>
            </label>
            <input type="text" id="student-filter" placeholder="بحث سريع بالاسم..." class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-3 text-sm">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2 max-h-80 overflow-y-auto border border-gray-100 rounded-xl p-3">
                @foreach($students as $student)
                    <label class="flex items-center gap-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg px-2 py-1.5 student-option">
                        <input type="checkbox" name="student_ids[]" value="{{ $student->id }}" @checked(in_array($student->id, old('student_ids', []), true)) class="accent-emerald-600 student-check">
                        <span class="font-bold">{{ $student->name }}</span>
                        <span class="text-xs text-gray-400">{{ $student->classroom?->name }}</span>
                    </label>
                @endforeach
            </div>
            <div class="text-xs text-gray-400 mt-1">عدد المحددين: <span id="student-count">0</span></div>
        </div>

        <div class="flex items-center justify-between pt-2">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-8 py-2.5 rounded-xl">إنشاء اللقاء</button>
            <a href="{{ route('admin.faith-meetings.index') }}" class="text-gray-500 text-sm hover:underline">إلغاء</a>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
    const filter = document.getElementById('student-filter');
    if (filter) {
        filter.addEventListener('input', () => {
            const q = filter.value.trim();
            document.querySelectorAll('.student-option').forEach(el => {
                el.classList.toggle('hidden', q !== '' && !el.textContent.includes(q));
            });
        });
    }
    const updateCount = () => {
        const count = document.querySelectorAll('.student-check:checked').length;
        const target = document.getElementById('student-count');
        if (target) target.textContent = count;
    };
    document.querySelectorAll('.student-check').forEach(cb => cb.addEventListener('change', updateCount));
    updateCount();
</script>
@endsection
