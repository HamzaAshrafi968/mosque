@extends('layouts.app')

@section('title', 'تعديل تسميع')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <a href="{{ route('admin.quran.batches.index', ['student_id' => $session->student_id]) }}" class="text-sm text-emerald-700 hover:text-emerald-800">← دفعات الحفظ</a>
    <h2 class="text-2xl font-extrabold text-gray-800">تعديل تسميع — {{ $session->student->name }}</h2>

    <form method="POST" action="{{ route('admin.quran.tasmee.update', $session) }}" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-4">
        @csrf
        @method('PATCH')
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">الطالب <span class="text-red-500">*</span></label>
                <select name="student_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    @foreach($students as $student)
                        <option value="{{ $student->id }}" @selected(old('student_id', $session->student_id) === $student->id)>{{ $student->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">المعلم <span class="text-red-500">*</span></label>
                <select name="teacher_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    @foreach($teachers as $teacher)
                        <option value="{{ $teacher->id }}" @selected(old('teacher_id', $session->teacher_id) === $teacher->id)>{{ $teacher->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">النوع <span class="text-red-500">*</span></label>
                <select name="type" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    @foreach($types as $type)
                        <option value="{{ $type->value }}" @selected(old('type', $session->type->value) === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">التاريخ <span class="text-red-500">*</span></label>
                <input type="date" name="date" required value="{{ old('date', $session->date->format('Y-m-d')) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">المقدار</label>
                <input type="number" step="0.01" min="0" name="amount" data-amount-input value="{{ old('amount', $session->amount) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <p class="text-xs text-gray-400 mt-1">مطلوب ما لم تحدد نطاق صفحات.</p>
            </div>
            <x-quran-page-range :from="$session->from_page" :to="$session->to_page" :statuses="$session->word_statuses ?? []" :review-url="route('admin.quran.tasmee.review', $session)" />
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">النتيجة</label>
                <select name="result" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">— بدون —</option>
                    @foreach($results as $result)
                        <option value="{{ $result->value }}" @selected(old('result', $session->result?->value) === $result->value)>{{ $result->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-gray-700 mb-1">المقروء</label>
                <input type="text" name="recited_portion" value="{{ old('recited_portion', $session->recited_portion) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-gray-700 mb-1">ملاحظات</label>
                <textarea name="notes" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2">{{ old('notes', $session->notes) }}</textarea>
            </div>
        </div>
        <div class="flex items-center justify-between pt-2">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-8 py-2.5 rounded-xl">حفظ التعديلات</button>
            <a href="{{ route('admin.quran.batches.index', ['student_id' => $session->student_id]) }}" class="text-gray-500 text-sm hover:underline">إلغاء</a>
        </div>
    </form>
</div>
@endsection
