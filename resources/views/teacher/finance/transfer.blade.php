@extends('layouts.app')

@section('title', 'تحويل بين أشخاص')

@section('content')
<h1 class="text-2xl font-bold text-gray-800 mb-2">تحويل مبلغ إلى شخص آخر</h1>
<p class="text-sm text-gray-500 mb-6">
    يُسجل التحويل على الطرفين معاً (حسابك كمُحوِّل وحساب المستلم) فيعمل التوازن تلقائياً — مثال: إرجاع مبلغ لطالب أو تحويل لمعلم آخر.
</p>

<div class="max-w-xl bg-white rounded-2xl shadow p-6">
    <form method="POST" action="{{ route('teacher.finance.transfer.store') }}" class="space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">المستلم</label>
            <select name="to_type" id="to-type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                <option value="student">طالب</option>
                <option value="teacher">معلم / شخص آخر</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الاسم</label>
            <select name="to_id" id="to-id-students" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                <option value="">اختر الطالب</option>
                @foreach($payingStudents as $student)
                    <option value="{{ $student->id }}" @selected(old('to_id') == $student->id)>{{ $student->name }}</option>
                @endforeach
            </select>
            <select name="to_id" id="to-id-teachers" disabled
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                <option value="">اختر المعلم</option>
                @foreach($colleagues as $colleague)
                    <option value="{{ $colleague->id }}" @selected(old('to_id') == $colleague->id)>{{ $colleague->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">المبلغ</label>
            <input type="number" step="0.01" min="0.01" name="amount" required value="{{ old('amount') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الوصف</label>
            <input type="text" name="description" value="{{ old('description') }}" placeholder="سبب التحويل"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>

        <button type="submit" class="bg-indigo-700 hover:bg-indigo-800 text-white font-bold px-5 py-2.5 rounded-lg">تنفيذ التحويل</button>
    </form>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var type = document.getElementById('to-type');
        var students = document.getElementById('to-id-students');
        var teachers = document.getElementById('to-id-teachers');

        function sync() {
            var isStudent = type.value === 'student';
            students.disabled = !isStudent;
            students.required = isStudent;
            teachers.disabled = isStudent;
            teachers.required = !isStudent;
            students.classList.toggle('hidden', !isStudent);
            teachers.classList.toggle('hidden', isStudent);
        }

        type.addEventListener('change', sync);
        sync();
    });
</script>
@endpush
