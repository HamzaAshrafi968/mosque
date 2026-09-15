@extends('layouts.app')

@section('title', 'مراجعة 5')

@section('content')
<div class="max-w-6xl mx-auto">
    @include('quran.khamsa.review-details', [
        'review' => $review,
        'memorizedJuz' => $memorizedJuz,
        'listeningSessions' => $listeningSessions,
        'results' => $results,
        'indexRoute' => route('teacher.quran.khamsa.index'),
        'completeRoute' => fn ($item) => route('teacher.quran.khamsa.items.complete', $item),
        'cancelRoute' => route('teacher.quran.khamsa.cancel', $review),
        'memorizationStoreRoute' => route('teacher.quran.khamsa.memorization.store'),
        'memorizationDestroyRoute' => route('teacher.quran.khamsa.memorization.destroy'),
    ])
</div>
@endsection
