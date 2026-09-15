@extends('layouts.app')

@section('title', 'إضافة معلم')

@section('content')
<div class="bg-white rounded-xl shadow overflow-hidden p-6 max-w-2xl">
    <form method="POST" action="{{ route('admin.teachers.store') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <div class="pb-4 border-b border-gray-100">
            <x-photo-input label="صورة المعلم" />
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الاسم <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name') }}" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الجنس <span class="text-red-500">*</span></label>
            <select name="gender" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="male" @selected(old('gender') === 'male')>ذكر</option>
                <option value="female" @selected(old('gender') === 'female')>أنثى</option>
            </select>
        </div>
        @php
            $currentSession = config('app.current_study_session_id');
            $selectedSessions = old('study_session_ids', $currentSession ? [$currentSession] : []);
        @endphp
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الدوامات</label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @foreach($sessions as $session)
                    <label class="flex items-center gap-2 border border-gray-200 rounded-lg px-3 py-2 cursor-pointer hover:bg-gray-50">
                        <input type="checkbox" name="study_session_ids[]" value="{{ $session->id }}" @checked(in_array($session->id, $selectedSessions))
                               class="rounded border-gray-300 text-emerald-700 focus:ring-emerald-500">
                        <span class="text-sm text-gray-700">{{ $session->name }}</span>
                    </label>
                @endforeach
            </div>
            <input type="hidden" name="study_session_ids[]" value="">
            <p class="text-xs text-gray-400 mt-1">يمكنك تحديد أكثر من دوام (مثال: الأول والثالث). إن لم تحدد شيئاً يظهر الأستاذ في كل الدوامات.</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
            <input type="email" name="email" value="{{ old('email') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الهاتف</label>
            <input type="text" name="phone" value="{{ old('phone') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">التخصص</label>
            <input type="text" name="specialty" value="{{ old('specialty') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ التوظيف</label>
            <input type="date" name="hired_at" value="{{ old('hired_at') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الراتب الشهري</label>
            <input type="number" step="0.01" min="0" name="monthly_salary" value="{{ old('monthly_salary') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none" dir="ltr">
            <p class="text-xs text-gray-400 mt-1">المبلغ الذي يعطيه مدير الجامع للأستاذ في الشهر.</p>
        </div>
        <div class="flex items-center gap-2">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', '1') == '1') id="is_active"
                   class="rounded border-gray-300 text-emerald-700 focus:ring-emerald-500">
            <label for="is_active" class="text-sm font-medium text-gray-700">نشط</label>
        </div>

        <hr class="my-4">

        <h2 class="text-lg font-bold text-gray-800">إنشاء حساب دخول (اختياري)</h2>
        <p class="text-sm text-gray-500">اتركه فارغاً إن لم ترغب بإنشاء حساب</p>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور</label>
            <input type="password" name="password"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>

        @if($customFields->isNotEmpty())
            <div class="border-t pt-4">
                <h3 class="text-sm font-bold text-gray-700 mb-3">بيانات إضافية (حقول مخصصة)</h3>
                <x-custom-field-inputs :fields="$customFields" :values="old('custom_fields', [])" />
            </div>
        @endif

        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">حفظ</button>
    </form>
</div>
@endsection
