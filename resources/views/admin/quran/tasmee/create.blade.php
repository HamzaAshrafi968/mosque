@extends('layouts.app')

@section('title', 'تسجيل تسميع جديد')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <a href="{{ route('admin.quran.batches.index', array_filter(['student_id' => $presetStudentId])) }}" class="text-sm text-emerald-700 hover:text-emerald-800">← دفعات الحفظ</a>
    <h2 class="text-2xl font-extrabold text-gray-800">تسجيل تسميع جديد</h2>

    @if ($batch && $progress)
        <div class="bg-white rounded-2xl shadow-sm border border-emerald-200 p-5 space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <div class="text-xs text-gray-400">الدفعة الحالية</div>
                    <div class="font-black text-gray-800">{{ $batch->label() }} — {{ $batch->status->label() }}</div>
                </div>
                <span class="text-xs text-gray-500">صفحات {{ $progress['from'] }}–{{ $progress['to'] }}</span>
            </div>
            <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                <div class="h-full bg-emerald-600" style="width: {{ min(100, $progress['percentage']) }}%"></div>
            </div>
            <div class="text-xs text-gray-500">
                المغطى: <b class="text-emerald-700">{{ $progress['covered'] }}</b> / {{ $progress['total'] }} صفحة
                @if ($progress['next_page'])
                    — الصفحة التالية المقترحة: <b class="text-gray-700">{{ $progress['next_page'] }}</b>
                @endif
            </div>
            <p class="text-xs text-amber-700">تسميع «الحفظ الجديد» مسموح فقط داخل نطاق هذه الدفعة (صفحات {{ $progress['from'] }}–{{ $progress['to'] }}).</p>
        </div>
    @elseif ($presetStudentId)
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 text-sm text-amber-800">
            لا توجد دفعة مفتوحة لهذا الطالب حالياً — إن كان قد أكمل الحفظ فراجع «دفعات الحفظ»، والتسميع «المراجعة» يبقى متاحاً.
        </div>
    @endif

    <form method="POST" action="{{ route('admin.quran.tasmee.store') }}" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-4">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-gray-700 mb-1">الطالب <span class="text-red-500">*</span></label>
                <select name="student_id" required data-tasmee-student class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">— اختر الطالب —</option>
                    @foreach($students as $student)
                        <option value="{{ $student->id }}" @selected(old('student_id', $presetStudentId) === $student->id)>{{ $student->name }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400 mt-1">اختيار الطالب يعرض دفعته الحالية ونطاق التسميع المسموح.</p>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">نوع التسميع <span class="text-red-500">*</span></label>
                <select name="type" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    @foreach($types as $type)
                        <option value="{{ $type->value }}" @selected(old('type', $presetType) === $type->value)>{{ $type->label() }} ({{ $type->value === 'new' ? 'حفظ جديد' : 'مراجعة محفوظ' }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">المعلم <span class="text-red-500">*</span></label>
                <select name="teacher_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">— اختر المعلم —</option>
                    @foreach($teachers as $teacher)
                        <option value="{{ $teacher->id }}" @selected(old('teacher_id') === $teacher->id)>{{ $teacher->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">التاريخ <span class="text-red-500">*</span></label>
                <input type="date" name="date" required value="{{ old('date', now()->toDateString()) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">المقدار (صفحات/أجزاء)</label>
                <input type="number" step="0.01" min="0" name="amount" data-amount-input value="{{ old('amount') }}" placeholder="مثال: 2" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <p class="text-xs text-gray-400 mt-1">مطلوب ما لم تحدد نطاق صفحات.</p>
            </div>
            <x-quran-page-range :from="old('from_page', $suggestedFrom)" :to="old('to_page', $suggestedTo)" :review-url="route('admin.quran.tasmee.review')" />
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-gray-700 mb-1">المقروء (اسم الجزء/السورة) </label>
                <input type="text" name="recited_portion" value="{{ old('recited_portion') }}" placeholder="مثال: جزء عم أو سورة البقرة من آية 1 إلى 50" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
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
                <label class="block text-sm font-bold text-gray-700 mb-1">ملاحظات</label>
                <textarea name="notes" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2">{{ old('notes') }}</textarea>
            </div>
        </div>
        <div class="flex items-center justify-between pt-2">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-8 py-2.5 rounded-xl">حفظ التسميع</button>
            <a href="{{ route('admin.quran.batches.index', array_filter(['student_id' => $presetStudentId])) }}" class="text-gray-500 text-sm hover:underline">إلغاء</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
(function () {
    var select = document.querySelector('[data-tasmee-student]');
    if (!select) return;
    select.addEventListener('change', function () {
        if (this.value) {
            window.location = @json(route('admin.quran.tasmee.create')) + '?student_id=' + encodeURIComponent(this.value) + @json($presetType ? '&type='.$presetType : '');
        }
    });
})();
</script>
@endpush
@endsection
