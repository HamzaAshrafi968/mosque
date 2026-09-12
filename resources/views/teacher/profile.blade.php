@extends('layouts.app')

@section('title', 'الملف الشخصي')

@section('content')
<div class="max-w-2xl">
    <div class="bg-white rounded-2xl shadow-sm border border-pine-950/5 overflow-hidden">
        <div class="gradient-sidebar relative overflow-hidden px-6 py-6 text-white">
            <div aria-hidden="true" class="absolute -top-10 -end-10 w-44 h-44 rounded-full bg-gold-300/15 blur-3xl pointer-events-none"></div>
            <div class="relative flex items-center gap-4">
                <div class="rounded-full p-[2px] bg-gradient-to-br from-gold-200 via-gold-400 to-gold-600 shrink-0">
                    <div class="w-16 h-16 rounded-full bg-pine-900 overflow-hidden grid place-items-center text-gold-200 text-xl font-black">
                        @if($user->avatarUrl())
                            <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" class="w-full h-full object-cover">
                        @else
                            {{ mb_substr($user->name, 0, 1) }}
                        @endif
                    </div>
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl font-black truncate">{{ $user->name }}</h1>
                    <p class="text-[12px] text-gold-200/90 font-semibold mt-0.5">الملف الشخصي للمعلم — تُستخدم الصورة في البوابات وقوائم المعلمين</p>
                </div>
            </div>
        </div>

        <div class="p-6">
            <form method="POST" action="{{ route('teacher.profile.update') }}" enctype="multipart/form-data" class="space-y-5">
                @csrf
                @method('PATCH')

                <div class="rounded-2xl bg-gray-50/80 border border-gray-100 p-4">
                    <x-photo-input label="الصورة الشخصية" help="JPG أو PNG أو WebP — بحد أقصى 2MB" :current-src="$user->avatarUrl()" :current-name="$user->name" />
                </div>

                <div class="h-px bg-gray-100"></div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الاسم</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required dir="ltr"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف</label>
                    <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" dir="ltr"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>

                <div class="h-px bg-gray-100"></div>

                <div>
                    <h3 class="font-bold text-gray-800 mb-3">تغيير كلمة المرور (اختياري)</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور الجديدة</label>
                            <input type="password" name="password"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">تأكيد كلمة المرور</label>
                            <input type="password" name="password_confirmation"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        </div>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" class="btn-shine bg-gradient-to-l from-emerald-700 to-pine-800 hover:from-emerald-600 hover:to-pine-700 text-white font-bold px-6 py-2.5 rounded-xl shadow-lg shadow-emerald-900/20 transition active:scale-95">حفظ التعديلات</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
