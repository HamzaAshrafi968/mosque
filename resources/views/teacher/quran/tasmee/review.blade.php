@extends('layouts.app')

@section('title', $session ? 'تعديل تسميع' : 'تسجيل تسميع')

<x-quran-review-styles />
<x-quran-preview-scripts />

@section('content')
<div class="max-w-5xl mx-auto space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-800">{{ $session ? 'تعديل تسميع' : 'تسجيل تسميع' }}</h2>
            <p class="text-sm text-gray-500 mt-1">
                {{ $student->name }} — {{ $type->label() }} — {{ $date }} — صفحة {{ $fromPage }} → صفحة {{ $toPage }}
            </p>
        </div>
        <a href="{{ $session ? route('teacher.quran.tasmee.edit', $session) : route('teacher.quran.tasmee.create') }}" class="text-sm text-emerald-700 hover:text-emerald-800">← رجوع للنموذج</a>
    </div>

    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 text-red-700 text-sm font-medium px-4 py-3 space-y-1">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ $session ? route('teacher.quran.tasmee.update', $session) : route('teacher.quran.tasmee.store') }}" data-quran-preview-form class="space-y-5">
        @csrf
        @if($session)
            @method('PATCH')
        @endif
        <input type="hidden" name="student_id" value="{{ $student->id }}">
        <input type="hidden" name="type" value="{{ $type->value }}">
        <input type="hidden" name="date" value="{{ $date }}">
        <input type="hidden" name="amount" value="{{ $amount }}">
        <input type="hidden" name="from_page" value="{{ $fromPage }}">
        <input type="hidden" name="to_page" value="{{ $toPage }}">
        <input type="hidden" name="recited_portion" value="{{ $recitedPortion }}">
        <input type="hidden" name="notes" value="{{ $notes }}">

        <div data-preview-root>
            <x-quran-review-viewer :pages="$pages" :statuses="$statuses" />
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-end">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">النتيجة</label>
                    <select name="result" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        <option value="">— بدون —</option>
                        @foreach($results as $result)
                            <option value="{{ $result->value }}" @selected($resultValue === $result->value)>{{ $result->label() }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">تُعبَّأ تلقائياً حسب نسبة الإتقان إن تركت فارغة.</p>
                </div>
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-8 py-2.5 rounded-xl">💾 حفظ التسميع</button>
            </div>
        </div>
    </form>
</div>
@endsection
