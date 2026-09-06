@extends('layouts.guest')

@section('title', 'تسجيل الدخول')

@section('content')
    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
        @csrf
        <div>
            <label class="block text-sm font-bold text-pine-900 mb-2">البريد الإلكتروني</label>
            <div class="relative group">
                <span class="absolute inset-y-0 start-4 flex items-center pointer-events-none text-pine-300 group-focus-within:text-gold-600 transition-colors duration-300">
                    <x-icon name="mail" class="w-5 h-5" />
                </span>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus dir="ltr" placeholder="admin@mosque.test"
                       class="w-full border border-gray-200 bg-white rounded-2xl ps-12 pe-4 py-3.5 text-sm font-semibold placeholder:text-gray-300 focus:ring-4 focus:ring-gold-400/15 focus:border-gold-400 focus:bg-white transition shadow-sm">
            </div>
        </div>

        <div>
            <label class="block text-sm font-bold text-pine-900 mb-2">كلمة المرور</label>
            <div class="relative group">
                <span class="absolute inset-y-0 start-4 flex items-center pointer-events-none text-pine-300 group-focus-within:text-gold-600 transition-colors duration-300">
                    <x-icon name="shield" class="w-5 h-5" />
                </span>
                <input type="password" name="password" required placeholder="••••••••" dir="ltr"
                       class="w-full border border-gray-200 bg-white rounded-2xl ps-12 pe-12 py-3.5 text-sm font-semibold placeholder:text-gray-300 focus:ring-4 focus:ring-gold-400/15 focus:border-gold-400 focus:bg-white transition shadow-sm">
                <button type="button" data-toggle-password
                        class="absolute inset-y-0 end-2.5 my-auto w-9 h-9 grid place-items-center rounded-xl text-gray-400 hover:text-pine-700 hover:bg-gray-50 transition"
                        aria-label="إظهار كلمة المرور">
                    <x-icon name="eye" class="w-5 h-5" data-eye-icon />
                    <x-icon name="eye-off" class="w-5 h-5 hidden" data-eye-off-icon />
                </button>
            </div>
        </div>

        <div class="flex items-center justify-between pt-1">
            <label class="flex items-center gap-2.5 text-[13px] text-gray-600 cursor-pointer select-none font-semibold">
                <input type="checkbox" name="remember" value="1" checked class="w-4 h-4 rounded-md border-gray-300 text-emerald-600 focus:ring-gold-400 accent-emerald-600 cursor-pointer">
                تذكرني
            </label>
            <span class="text-[11px] text-gray-300 font-semibold">بيئة آمنة ومشفّرة 🔒</span>
        </div>

        <button type="submit" class="btn-shine group w-full bg-gradient-to-l from-pine-800 via-emerald-700 to-emerald-600 hover:from-pine-900 hover:to-emerald-700 text-white font-black py-4 rounded-2xl transition-all duration-300 shadow-xl shadow-emerald-900/30 hover:shadow-emerald-800/40 hover:-translate-y-0.5 active:translate-y-0 text-lg flex items-center justify-center gap-2">
            <span>دخول</span>
            <span class="transition-transform duration-300 group-hover:-translate-x-1">
                <x-icon name="chevron" class="w-5 h-5 rotate-90" />
            </span>
        </button>

        <div class="flex items-center gap-2 pt-1 text-[13px] text-center text-gray-400 font-medium justify-center">
            <x-icon name="info" class="w-4 h-4 shrink-0 text-gold-500" />
            <span>التسجيل متاح فقط من خلال مدير الجوامع الرئيسي</span>
        </div>
    </form>
@endsection
