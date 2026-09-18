@extends('layouts.app')

@section('title', 'تسميع '.$item->label())

<x-quran-review-styles />
<x-quran-preview-scripts />

@section('content')
<div class="max-w-5xl mx-auto space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-800">تسميع الجزء مع تسجيل الأخطاء</h2>
            <p class="text-sm text-gray-500 mt-1">
                {{ $student->name }} — {{ $program->label() }} — {{ $item->label() }}
            </p>
        </div>
        <a href="{{ $backRoute }}" class="text-sm text-emerald-700 hover:text-emerald-800">← رجوع للدورة</a>
    </div>

    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 text-red-700 text-sm font-medium px-4 py-3 space-y-1">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ $storeRoute }}" data-quran-preview-form class="space-y-5">
        @csrf
        <input type="hidden" name="teacher_id" value="{{ $teacherId }}">

        <div data-preview-root>
            <x-quran-review-viewer :pages="$pages" :statuses="$statuses" />
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-end">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">التاريخ</label>
                    <input type="date" name="date" value="{{ $date }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">ملاحظات</label>
                    <input type="text" name="notes" maxlength="2000" placeholder="ملاحظات عامة (اختياري)"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 mt-5">
                <p class="text-xs text-gray-400">
                    غير المعلَّم = صحيح. حفظ التسميع يُنهي تسميع الجزء، و{{ $item->label() }} يصبح جاهزاً للاختبار عند اكتمال أجزاء الدفعة.
                </p>
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-8 py-2.5 rounded-xl">
                    💾 حفظ التسميع وإنهاء الجزء
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
