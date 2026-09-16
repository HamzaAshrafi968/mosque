@extends('layouts.app')

@section('title', 'خطة استماع جديدة')

@section('content')
<div class="max-w-5xl mx-auto space-y-4">
    <div>
        <h2 class="text-2xl font-extrabold text-gray-800">خطة استماع جديدة</h2>
        <p class="text-sm text-gray-500 mt-1">اختر الطالب، ثم حدّد عناصر الخطة: جديد بنطاق صفحات داخل الجزء، أو مراجعة 5 من الأجزاء المحفوظة (مع توليد تلقائي من المحفوظ).</p>
    </div>

    @include('quran.listening.plan-form', [
        'pickerRoute' => route('teacher.quran.listening.create'),
        'storeRoute' => route('teacher.quran.listening.store'),
        'indexRoute' => route('teacher.quran.batches.index'),
        'memorizationRoute' => $selectedStudent ? route('teacher.quran.khamsa.create', ['student_id' => $selectedStudent->id]) : null,
    ])
</div>
@endsection
