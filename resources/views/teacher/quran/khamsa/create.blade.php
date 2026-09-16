@extends('layouts.app')

@section('title', 'تخصيص مراجعة 5')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div>
        <a href="{{ route('teacher.quran.batches.index') }}" class="text-sm text-emerald-700 hover:text-emerald-800">← كل المراجعات</a>
        <h2 class="text-2xl font-extrabold text-gray-800 mt-1">تخصيص مراجعة 5</h2>
        <p class="text-sm text-gray-500 mt-1">اختر الطالب ثم حدّد الخمسات المطلوب مراجعتها معك في الدوام.</p>
    </div>

    @include('quran.khamsa.create-form', [
        'pickerRoute' => route('teacher.quran.khamsa.create'),
        'storeRoute' => route('teacher.quran.khamsa.store'),
        'indexRoute' => route('teacher.quran.batches.index'),
        'memorizationStoreRoute' => route('teacher.quran.khamsa.memorization.store'),
        'memorizationDestroyRoute' => route('teacher.quran.khamsa.memorization.destroy'),
        'students' => $students,
        'teachers' => $teachers,
        'sessions' => $sessions,
        'selectedStudent' => $selectedStudent,
        'khamsat' => $khamsat,
        'memorizedJuz' => $memorizedJuz,
        'currentSessionId' => $currentSessionId,
    ])
</div>
@endsection
