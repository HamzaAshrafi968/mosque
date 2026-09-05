@extends('layouts.app')

@section('title', 'تسجيل قبض')

@section('content')
<h1 class="text-2xl font-bold text-gray-800 mb-6">تسجيل مبلغ تم استلامه</h1>

<div class="max-w-xl bg-white rounded-2xl shadow p-6">
    <form method="POST" action="{{ route('teacher.finance.receive.store') }}" class="space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">من</label>
            <select name="from_type" id="from-type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                <option value="student">طالب</option>
                <option value="teacher">معلم / شخص آخر</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الاسم</label>
            <select name="from_id" id="from-id-students" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                <option value="">اختر الطالب (من شعبك الموكلة)</option>
                @foreach($payingStudents as $student)
                    <option value="{{ $student->id }}" @selected(old('from_id') == $student->id)>{{ $student->name }}</option>
                @endforeach
            </select>
            <select name="from_id" id="from-id-teachers" disabled
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                <option value="">اختر الشخص</option>
                @foreach($payingTeachers as $teacher)
                    <option value="{{ $teacher->id }}" @selected(old('from_id') == $teacher->id)>{{ $teacher->name }}</option>
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
            <input type="text" name="description" value="{{ old('description') }}" placeholder="مثال: قسط شهري، تبرع..."
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>

        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-5 py-2.5 rounded-lg">حفظ القبض</button>
    </form>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var type = document.getElementById('from-type');
        var students = document.getElementById('from-id-students');
        var teachers = document.getElementById('from-id-teachers');

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
