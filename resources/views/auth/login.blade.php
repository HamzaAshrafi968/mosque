@extends('layouts.guest')

@section('title', 'تسجيل الدخول')

@section('content')
    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
        @csrf
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">📧 البريد الإلكتروني</label>
            <input type="email" name="email" value="{{ old('email') }}" required
                   class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-3 focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 focus:bg-white transition">
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">🔒 كلمة المرور</label>
            <input type="password" name="password" required
                   class="w-full border border-gray-200 bg-gray-50 rounded-xl px-4 py-3 focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 focus:bg-white transition">
        </div>
        <label class="flex items-center gap-2.5 text-sm text-gray-600 cursor-pointer">
            <input type="checkbox" name="remember" value="1" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
            تذكرني
        </label>
        <button type="submit" class="w-full bg-gradient-to-r from-emerald-700 to-emerald-600 hover:from-emerald-800 hover:to-emerald-700 text-white font-bold py-3 rounded-xl transition shadow-lg shadow-emerald-700/25 text-lg">
            🚪 دخول
        </button>
        <p class="text-sm text-center text-gray-400">
            التسجيل متاح فقط من خلال مدير الجوامع الرئيسي
        </p>
    </form>
@endsection
