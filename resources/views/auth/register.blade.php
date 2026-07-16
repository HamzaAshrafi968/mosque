@extends('layouts.guest')

@section('title', 'تسجيل جامع جديد')

@section('content')
    <form method="POST" action="{{ route('register.store') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">🕌 اسم الجامع</label>
            <input type="text" name="mosque_name" value="{{ old('mosque_name') }}" required
                   class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-3 focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 focus:bg-white transition">
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">👤 اسم المدير</label>
            <input type="text" name="name" value="{{ old('name') }}" required
                   class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-3 focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 focus:bg-white transition">
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">⚧ الجنس</label>
            <select name="gender" required class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-3 focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 focus:bg-white transition">
                <option value="male" @selected(old('gender') === 'male')>ذكر</option>
                <option value="female" @selected(old('gender') === 'female')>أنثى</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">📧 البريد الإلكتروني</label>
            <input type="email" name="email" value="{{ old('email') }}" required
                   class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-3 focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 focus:bg-white transition">
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">📱 رقم الهاتف</label>
            <input type="text" name="phone" value="{{ old('phone') }}"
                   class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-3 focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 focus:bg-white transition">
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">📍 العنوان</label>
            <input type="text" name="address" value="{{ old('address') }}"
                   class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-3 focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 focus:bg-white transition">
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">🔒 كلمة المرور</label>
            <input type="password" name="password" required
                   class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-3 focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 focus:bg-white transition">
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">🔒 تأكيد كلمة المرور</label>
            <input type="password" name="password_confirmation" required
                   class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-3 focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 focus:bg-white transition">
        </div>
        <button type="submit" class="w-full bg-gradient-to-r from-emerald-700 to-emerald-600 hover:from-emerald-800 hover:to-emerald-700 text-white font-bold py-3 rounded-xl transition shadow-lg shadow-emerald-700/25 text-lg">
            ✨ إنشاء الحساب
        </button>
        <p class="text-sm text-center text-gray-500">
            لديك حساب؟
            <a href="{{ route('login') }}" class="text-emerald-600 font-bold hover:text-emerald-800 transition">سجل الدخول</a>
        </p>
    </form>
@endsection
