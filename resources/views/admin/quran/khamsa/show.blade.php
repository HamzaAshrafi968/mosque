@extends('layouts.app')

@section('title', 'مراجعة 5')

@section('content')
<div class="max-w-6xl mx-auto">
    @include('quran.khamsa.review-details', [
        'review' => $review,
        'memorizedJuz' => $memorizedJuz,
        'listeningSessions' => $listeningSessions,
        'results' => $results,
        'indexRoute' => route('admin.quran.batches.index'),
        'completeRoute' => fn ($item) => route('admin.quran.khamsa.items.complete', $item),
        'cancelRoute' => route('admin.quran.khamsa.cancel', $review),
        'memorizationStoreRoute' => route('admin.quran.khamsa.memorization.store'),
        'memorizationDestroyRoute' => route('admin.quran.khamsa.memorization.destroy'),
    ])
</div>
@endsection
