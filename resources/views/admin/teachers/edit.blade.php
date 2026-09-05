@extends('layouts.app')

@section('title', 'تعديل بيانات المعلم')

@section('content')
<div class="bg-white rounded-xl shadow overflow-hidden p-6 max-w-2xl">
    <form method="POST" action="{{ route('admin.teachers.update', $teacher) }}" class="space-y-4">
        @csrf
        @method('PUT')
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الاسم <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name', $teacher->name) }}" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الجنس <span class="text-red-500">*</span></label>
            <select name="gender" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="male" @selected(old('gender', $teacher->gender) === 'male')>ذكر</option>
                <option value="female" @selected(old('gender', $teacher->gender) === 'female')>أنثى</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
            <input type="email" name="email" value="{{ old('email', $teacher->email) }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الهاتف</label>
            <input type="text" name="phone" value="{{ old('phone', $teacher->phone) }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">التخصص</label>
            <input type="text" name="specialty" value="{{ old('specialty', $teacher->specialty) }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ التوظيف</label>
            <input type="date" name="hired_at" value="{{ old('hired_at', $teacher->hired_at?->format('Y-m-d')) }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div class="flex items-center gap-2">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $teacher->is_active) == '1') id="is_active"
                   class="rounded border-gray-300 text-emerald-700 focus:ring-emerald-500">
            <label for="is_active" class="text-sm font-medium text-gray-700">نشط</label>
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
