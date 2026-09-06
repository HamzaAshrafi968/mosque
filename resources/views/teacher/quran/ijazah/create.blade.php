@extends('layouts.app')

@section('title', 'تقييم شهري - برنامج الإجازة')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <a href="{{ route('teacher.quran.ijazah.index') }}" class="text-sm text-emerald-700 hover:text-emerald-800">← تقييماتي الشهرية</a>
    <h2 class="text-2xl font-extrabold text-gray-800">تسجيل تقييم شهري لبرنامج الإجازة</h2>

    <form method="POST" action="{{ route('teacher.quran.ijazah.evaluations.store') }}" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-4">
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
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">الشهر <span class="text-red-500">*</span></label>
            <input type="month" name="month" required value="{{ old('month', $defaultMonth) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">المقدار <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" min="0" name="amount" required value="{{ old('amount') }}" placeholder="مثال: 30" class="w-full border border-gray-300 rounded-lg px-3 py-2">
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
                <label class="block text-sm font-bold text-gray-700 mb-1">المقروء</label>
                <input type="text" name="recited_portion" value="{{ old('recited_portion') }}" placeholder="مثال: القرآن كاملاً" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-gray-700 mb-1">ملاحظات</label>
                <textarea name="notes" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2">{{ old('notes') }}</textarea>
            </div>
        </div>
        <div class="flex items-center justify-between pt-2">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-8 py-2.5 rounded-xl">حفظ التقييم</button>
            <a href="{{ route('teacher.quran.ijazah.index') }}" class="text-gray-500 text-sm hover:underline">إلغاء</a>
        </div>
    </form>
</div>
@endsection
