@extends('layouts.app')

@section('title', 'التسميع مع المعلم')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <a href="{{ $backUrl }}" class="text-sm text-emerald-700 hover:text-emerald-800">← دفعات الحفظ</a>

    <div>
        <h2 class="text-2xl font-extrabold text-gray-800">التسميع مع المعلم</h2>
        <p class="text-sm text-gray-500 mt-1">اختر نوع الجلسة — البوابة الواحدة للتسميع والتقييم.</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <div class="text-xs text-gray-400">الطالب</div>
                <div class="font-black text-gray-800">{{ $student->name }}</div>
            </div>
            @if ($currentBatch)
                <div class="text-xs text-gray-500">
                    الدفعة الحالية: <b class="text-emerald-700">{{ $currentBatch->label() }}</b>
                    — {{ $currentBatch->status->label() }}
                </div>
            @endif
        </div>
    </div>

    <div class="space-y-3">
        @foreach ($options as $option)
            <a href="{{ $option->url }}" class="block bg-white rounded-2xl border-2 border-gray-200 hover:border-emerald-500 hover:shadow p-5 transition">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 w-5 h-5 shrink-0 rounded-full border-2 border-emerald-600 flex items-center justify-center">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                    </span>
                    <div>
                        <div class="font-bold text-gray-800">{{ $option->type->label() }}</div>
                        <div class="text-xs text-gray-400 mt-0.5">{{ $option->type->description() }}</div>
                    </div>
                </div>
            </a>
        @endforeach

        @if ($options->isEmpty())
            <div class="bg-white rounded-2xl border border-amber-200 p-4 text-sm text-amber-800">
                لا توجد أنواع جلسات متاحة لك لهذا الطالب.
            </div>
        @endif
    </div>
</div>
@endsection
