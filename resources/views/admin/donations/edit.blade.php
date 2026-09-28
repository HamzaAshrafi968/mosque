@extends('layouts.app')

@section('title', 'تعديل تبرع/مساهمة')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <span class="w-11 h-11 rounded-2xl bg-gold-100 text-gold-600 grid place-items-center shrink-0">
            <x-icon name="gift" class="w-6 h-6" />
        </span>
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-gray-800">تعديل تبرع/مساهمة</h1>
            <p class="text-sm text-gray-500 font-semibold mt-0.5">{{ $donation->status->label() }} — {{ $donation->typeLabel() }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.donations.update', $donation) }}" class="bg-white rounded-2xl shadow p-5 sm:p-6 space-y-4">
        @csrf
        @method('PATCH')
        @include('admin.donations._form')

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-5 py-2.5 rounded-xl transition">حفظ التعديلات</button>
            <a href="{{ route('admin.donations.index') }}" class="text-sm font-bold text-gray-500 hover:text-gray-700">إلغاء</a>
        </div>
    </form>
</div>
@endsection
