@extends('layouts.app')

@section('title', 'مراجعة 5')

@section('content')
@php
    $authorization = app(\App\Services\AuthorizationService::class);
    $can = fn (string $permission) => $authorization->can(auth()->user(), $permission);
@endphp
<div class="max-w-6xl mx-auto">
    @include('quran.khamsa.review-details', [
        'review' => $review,
        'memorizedJuz' => $memorizedJuz,
        'listeningSessions' => $listeningSessions,
        'results' => $results,
        'indexRoute' => route('admin.quran.batches.index'),
        'completeRoute' => $can('quran_khamsa.complete') ? fn ($item) => route('admin.quran.khamsa.items.complete', $item) : null,
        'cancelRoute' => $can('quran_khamsa.update') ? route('admin.quran.khamsa.cancel', $review) : null,
        'memorizationStoreRoute' => $can('quran.memorization.manage') ? route('admin.quran.khamsa.memorization.store') : null,
        'memorizationDestroyRoute' => $can('quran.memorization.manage') ? route('admin.quran.khamsa.memorization.destroy') : null,
        'testUrl' => $testUrl,
    ])
</div>
@endsection
