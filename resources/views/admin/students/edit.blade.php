@extends('layouts.app')

@section('title', 'تعديل بيانات الطالب')

@section('content')
<div class="bg-white rounded-xl shadow overflow-hidden p-6 max-w-2xl">
    <form method="POST" action="{{ route('admin.students.update', $student) }}" class="space-y-4">
        @csrf
        @method('PUT')
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الاسم <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name', $student->name) }}" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الجنس <span class="text-red-500">*</span></label>
            <select name="gender" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="male" @selected(old('gender', $student->gender) === 'male')>ذكر</option>
                <option value="female" @selected(old('gender', $student->gender) === 'female')>أنثى</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ الميلاد</label>
            <input type="date" name="birth_date" value="{{ old('birth_date', $student->birth_date?->format('Y-m-d')) }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الصف</label>
            <select name="classroom_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="">اختر الصف</option>
                @foreach($classrooms as $classroom)
                    <option value="{{ $classroom->id }}" @selected(old('classroom_id', $student->classroom_id) == $classroom->id)>{{ $classroom->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الشعبة</label>
            <select name="section_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="">اختر الشعبة</option>
                @foreach($classrooms as $classroom)
                    @foreach($classroom->sections as $section)
                        <option value="{{ $section->id }}" @selected(old('section_id', $student->section_id) == $section->id)>{{ $classroom->name }} - {{ $section->name }}</option>
                    @endforeach
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">اسم ولي الأمر</label>
            <input type="text" name="guardian_name" value="{{ old('guardian_name', $student->guardian_name) }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">هاتف ولي الأمر</label>
            <input type="text" name="guardian_phone" value="{{ old('guardian_phone', $student->guardian_phone) }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ملاحظات</label>
            <textarea name="notes" rows="3"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">{{ old('notes', $student->notes) }}</textarea>
        </div>

        <div class="border-t border-gray-100 pt-4">
            <h3 class="font-bold text-gray-800 mb-1">حساب بوابة الطالب</h3>
            <p class="text-xs text-gray-400 mb-3">لإزالة الحساب امسح البريد الإلكتروني واحفظ. كلمة المرور تُترك فارغة لعدم تغييرها.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
                    <input type="email" name="portal_email" value="{{ old('portal_email', $student->user?->email) }}" dir="ltr"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">كلمة مرور جديدة</label>
                    <input type="password" name="portal_password"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
            </div>
        </div>

        @if($customFields->isNotEmpty())
            <div class="border-t pt-4">
                <h3 class="text-sm font-bold text-gray-700 mb-3">بيانات إضافية (حقول مخصصة)</h3>
                <x-custom-field-inputs :fields="$customFields" :values="$customValues" />
            </div>
        @endif
        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">حفظ</button>
    </form>
</div>
@endsection
