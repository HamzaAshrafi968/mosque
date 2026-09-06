@extends('layouts.app')

@section('title', 'تسجيل إتمام الحفظ')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <a href="{{ route('admin.quran.completions.index') }}" class="text-sm text-emerald-700 hover:text-emerald-800">← إتمام الحفظ</a>
    <h2 class="text-2xl font-extrabold text-gray-800">تسجيل إتمام حفظ القرآن</h2>
    <p class="text-sm text-gray-500">بعد التسجيل يُعرض الطلب للتأكيد — عند التأكيد يصبح الطالب حافظاً ويلتحق تلقائياً بالبرنامج التأهيلي.</p>

    <form method="POST" action="{{ route('admin.quran.completions.store') }}" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">الطالب <span class="text-red-500">*</span></label>
            <select name="student_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="">— اختر الطالب —</option>
                @foreach($students as $student)
                    <option value="{{ $student->id }}" @selected(old('student_id') === $student->id)>{{ $student->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">تاريخ إتمام الحفظ <span class="text-red-500">*</span></label>
            <input type="date" name="completed_at" required value="{{ old('completed_at', now()->toDateString()) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">ملاحظات</label>
            <textarea name="notes" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="مثال: أكمل حفظ القرآن كاملاً برواية حفص">{{ old('notes') }}</textarea>
        </div>
        <div class="flex items-center justify-between pt-2">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-8 py-2.5 rounded-xl">تسجيل الإتمام</button>
            <a href="{{ route('admin.quran.completions.index') }}" class="text-gray-500 text-sm hover:underline">إلغاء</a>
        </div>
    </form>
</div>
@endsection
