@extends('layouts.app')

@section('title', 'تقييم أسبوعي - البرنامج التأهيلي')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <a href="{{ route('admin.quran.qualifying.index') }}" class="text-sm text-emerald-700 hover:text-emerald-800">← البرنامج التأهيلي</a>
    <h2 class="text-2xl font-extrabold text-gray-800">تسجيل تقييم أسبوعي</h2>
    <p class="text-sm text-gray-500">كل أسبوع يسجل تقييم جديد — التقييمات السابقة محفوظة ولا تُستبدل.</p>

    <form method="POST" action="{{ route('admin.quran.qualifying.evaluations.store') }}" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">الطالب <span class="text-red-500">*</span></label>
            <select name="student_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="">— اختر الطالب —</option>
                @foreach($students as $student)
                    <option value="{{ $student->id }}" @selected(old('student_id', $studentId) === $student->id)>{{ $student->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">بداية الأسبوع <span class="text-red-500">*</span></label>
                <input type="date" name="week_start" required value="{{ old('week_start', $weekStart) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">نهاية الأسبوع <span class="text-red-500">*</span></label>
                <input type="date" name="week_end" required value="{{ old('week_end', $weekEnd) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">المقدار <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" min="0" name="amount" required value="{{ old('amount') }}" placeholder="مثال: 5" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">النتيجة <span class="text-red-500">*</span></label>
                <select name="result" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="passed" @selected(old('result', 'passed') === 'passed')>ناجح</option>
                    <option value="needs_review" @selected(old('result') === 'needs_review')>يحتاج مراجعة</option>
                    <option value="failed" @selected(old('result') === 'failed')>راسب</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-gray-700 mb-1">المقروء (اسم المقدار)</label>
                <input type="text" name="recited_portion" value="{{ old('recited_portion') }}" placeholder="مثال: الأجزاء 1-5" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-gray-700 mb-1">المشرف / الأستاذ <span class="text-red-500">*</span></label>
                <select name="evaluated_by" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="" disabled @selected(!old('evaluated_by'))>— اختر المشرف —</option>
                    @foreach($teachers as $teacher)
                        <option value="{{ $teacher->id }}" @selected(old('evaluated_by') === $teacher->id)>{{ $teacher->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-gray-700 mb-1">ملاحظات</label>
                <textarea name="notes" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2">{{ old('notes') }}</textarea>
            </div>
        </div>
        <div class="flex items-center justify-between pt-2">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-8 py-2.5 rounded-xl">حفظ التقييم</button>
            <a href="{{ route('admin.quran.qualifying.index') }}" class="text-gray-500 text-sm hover:underline">إلغاء</a>
        </div>
    </form>
</div>
@endsection
