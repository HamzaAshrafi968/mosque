@extends('layouts.app')

@section('title', 'تسجيل تسميع')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <a href="{{ route('teacher.quran.tasmee.index') }}" class="text-sm text-emerald-700 hover:text-emerald-800">← سجلات التسميع</a>
    <h2 class="text-2xl font-extrabold text-gray-800">تسجيل تسميع لطالب</h2>

    <form method="POST" action="{{ route('teacher.quran.tasmee.store') }}" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">الطالب <span class="text-red-500">*</span></label>
            <select name="student_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="">— اختر الطالب —</option>
                @foreach($students as $student)
                    <option value="{{ $student->id }}" @selected(old('student_id', $presetStudentId) === $student->id)>{{ $student->name }}</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-400 mt-1">طلاب شعبك + الطلاب الذين تشرف عليهم في البرامج القرآنية</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">النوع <span class="text-red-500">*</span></label>
                <select name="type" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    @foreach($types as $type)
                        <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->label() }} ({{ $type->value === 'new' ? 'حفظ جديد' : 'مراجعة محفوظ' }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">التاريخ <span class="text-red-500">*</span></label>
                <input type="date" name="date" required value="{{ old('date', now()->toDateString()) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">المقدار</label>
                <input type="number" step="0.01" min="0" name="amount" data-amount-input value="{{ old('amount') }}" placeholder="مثال: 2" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <p class="text-xs text-gray-400 mt-1">مطلوب ما لم تحدد نطاق صفحات.</p>
            </div>
            <x-quran-page-range :review-url="route('teacher.quran.tasmee.review')" />
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">النتيجة</label>
                <select name="result" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">— بدون —</option>
                    @foreach($results as $result)
                        <option value="{{ $result->value }}" @selected(old('result') === $result->value)>{{ $result->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-gray-700 mb-1">المقروء</label>
                <input type="text" name="recited_portion" value="{{ old('recited_portion') }}" placeholder="مثال: سورة البقرة من آية 1 إلى 25" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-gray-700 mb-1">ملاحظات</label>
                <textarea name="notes" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2">{{ old('notes') }}</textarea>
            </div>
        </div>
        <div class="flex items-center justify-between pt-2">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-8 py-2.5 rounded-xl">حفظ التسميع</button>
            <a href="{{ route('teacher.quran.tasmee.index') }}" class="text-gray-500 text-sm hover:underline">إلغاء</a>
        </div>
    </form>
</div>
@endsection
