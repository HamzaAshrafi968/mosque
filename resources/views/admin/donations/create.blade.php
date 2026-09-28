@extends('layouts.app')

@section('title', 'تسجيل تبرع/مساهمة')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <span class="w-11 h-11 rounded-2xl bg-gold-100 text-gold-600 grid place-items-center shrink-0">
            <x-icon name="gift" class="w-6 h-6" />
        </span>
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-gray-800">تسجيل تبرع/مساهمة</h1>
            <p class="text-sm text-gray-500 font-semibold mt-0.5">سجل تبرعًا وصل إليكم (مادي/عيني/معنوي)</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.donations.store') }}" class="bg-white rounded-2xl shadow p-5 sm:p-6 space-y-4">
        @csrf
        @include('admin.donations._form')

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الحالة</label>
            <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption->value }}" @selected(old('status', \App\Enums\DonationStatus::Accepted->value) === $statusOption->value)>{{ $statusOption->label() }}</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-400 mt-1">«مقبول» يظهر مباشرة في صفحة الجامع العامة.</p>
            @error('status')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-5 py-2.5 rounded-xl transition">حفظ</button>
            <a href="{{ route('admin.donations.index') }}" class="text-sm font-bold text-gray-500 hover:text-gray-700">إلغاء</a>
        </div>
    </form>
</div>
@endsection
