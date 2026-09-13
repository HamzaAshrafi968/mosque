@extends('layouts.app')

@section('title', 'إضافة طالب')

@section('content')
<div class="bg-white rounded-xl shadow overflow-hidden p-6 max-w-2xl">
    <form method="POST" action="{{ route('admin.students.store') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <div class="pb-4 border-b border-gray-100">
            <x-photo-input label="صورة الطالب" />
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
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ الميلاد</label>
            <input type="date" name="birth_date" value="{{ old('birth_date') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الدوام</label>
            <select name="study_session_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="">غير محدد (كل الدوامات)</option>
                @foreach($sessions as $session)
                    <option value="{{ $session->id }}" @selected(old('study_session_id', config('app.current_study_session_id')) == $session->id)>{{ $session->name }}</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-400 mt-1">إذا اخترت شعبة مرتبطة بدوام فتُحسب من الشعبة تلقائياً</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الصف</label>
            <select name="classroom_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="">اختر الصف</option>
                @foreach($classrooms as $classroom)
                    <option value="{{ $classroom->id }}" @selected(old('classroom_id') == $classroom->id)>{{ $classroom->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الشعبة</label>
            <select name="section_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="">اختر الشعبة</option>
                @foreach($classrooms as $classroom)
                    @foreach($classroom->sections as $section)
                        <option value="{{ $section->id }}" @selected(old('section_id') == $section->id)>{{ $classroom->name }} - {{ $section->name }}</option>
                    @endforeach
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">اسم ولي الأمر</label>
            <input type="text" name="guardian_name" value="{{ old('guardian_name') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">هاتف ولي الأمر</label>
            <input type="text" name="guardian_phone" value="{{ old('guardian_phone') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ملاحظات</label>
            <textarea name="notes" rows="3"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">{{ old('notes') }}</textarea>
        </div>

        <div class="border-t border-gray-100 pt-4">
            <h3 class="font-bold text-gray-800 mb-1">سجل الحفظ القرآني (اختياري)</h3>
            <p class="text-xs text-gray-400 mb-3">ما حفظه الطالب من القرآن قبل الالتحاق وما وصل إليه.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">مقدار الحفظ (بالأجزاء)</label>
                    <input type="number" name="memorized_juz" value="{{ old('memorized_juz') }}" min="0" max="30" step="0.5"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">من سورة</label>
                    <select name="memorized_from_surah_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        <option value="">اختر السورة</option>
                        @foreach($surahs as $surah)
                            <option value="{{ $surah->id }}" @selected(old('memorized_from_surah_id') == $surah->id)>{{ $surah->name_arabic }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">من آية</label>
                    <input type="number" name="memorized_from_ayah" value="{{ old('memorized_from_ayah') }}" min="1"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">إلى سورة (ما وصل إليه)</label>
                    <select name="memorized_to_surah_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        <option value="">اختر السورة</option>
                        @foreach($surahs as $surah)
                            <option value="{{ $surah->id }}" @selected(old('memorized_to_surah_id') == $surah->id)>{{ $surah->name_arabic }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">إلى آية</label>
                    <input type="number" name="memorized_to_ayah" value="{{ old('memorized_to_ayah') }}" min="1"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
            </div>
        </div>

        <div class="border-t border-gray-100 pt-4">
            <h3 class="font-bold text-gray-800 mb-1">حساب بوابة الطالب (اختياري)</h3>
            <p class="text-xs text-gray-400 mb-3">بإنشاء الحساب يستطيع الطالب تسجيل الدخول ومتابعة بياناته الأكاديمية.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
                    <input type="email" name="portal_email" value="{{ old('portal_email') }}" dir="ltr"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور</label>
                    <input type="password" name="portal_password"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
            </div>
        </div>

        <x-custom-field-inputs :fields="$customFields" :values="old('custom_fields', [])" class="contents" />
        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">حفظ</button>
    </form>
</div>
@endsection
