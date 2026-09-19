@extends('layouts.app')

@section('title', 'البوابة معطلة')

@section('content')
    <div class="max-w-2xl mx-auto">
        <section
            class="reveal relative overflow-hidden rounded-[28px] bg-white border border-pine-950/[0.06] shadow-[0_18px_40px_-18px_rgba(6,40,29,0.35)] p-7 sm:p-10 text-center">
            <span
                class="mx-auto mb-5 w-16 h-16 rounded-2xl bg-gradient-to-br from-gold-300 to-gold-600 grid place-items-center text-white shadow-md shadow-gold-700/30">
                <x-icon name="alert" class="w-8 h-8" />
            </span>

            <h1 class="text-2xl font-black text-pine-950 mb-3">البوابة معطّلة مؤقتاً</h1>

            <p class="text-gray-600 font-semibold leading-relaxed mb-2">
                تم تعطيل بوابة {{ auth()->user()->isGuardian() ? 'ولي الأمر' : 'الطالب' }} حالياً.
            </p>
            <p class="text-gray-500 text-sm font-semibold leading-relaxed mb-7">
                الحساب ما زال فعالاً ويمكنك تسجيل الدخول، لكن لا يمكن الوصول إلى صفحات البوابة.
                يرجى التواصل مع إدارة الجامع للاستفسار أو لإعادة التفعيل.
            </p>

            <div class="flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('notifications.index') }}"
                    class="inline-flex items-center gap-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-5 py-2.5 transition">
                    <x-icon name="bell" class="w-4 h-4" />
                    الإشعارات
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                        class="inline-flex items-center gap-2 rounded-xl bg-gray-100 hover:bg-gray-200 text-pine-950 text-sm font-bold px-5 py-2.5 transition">
                        <x-icon name="logout" class="w-4 h-4" />
                        تسجيل الخروج
                    </button>
                </form>
            </div>
        </section>
    </div>
@endsection
