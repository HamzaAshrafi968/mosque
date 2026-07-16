@extends('layouts.guest')

@section('title', 'تسجيل الدخول')

@section('content')
    <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
            <input type="email" name="email" value="{{ old('email') }}" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور</label>
            <input type="password" name="password" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-600">
            <input type="checkbox" name="remember" value="1" class="rounded">
            تذكرني
        </label>
        <button type="submit" class="w-full bg-emerald-700 hover:bg-emerald-800 text-white font-bold py-2 rounded-lg">
            دخول
        </button>
        <p class="text-sm text-center text-gray-600">
            ليس لديك حساب؟
            <a href="{{ route('register') }}" class="text-emerald-700 font-bold hover:underline">سجل جامعك الآن</a>
        </p>
    </form>
@endsection
