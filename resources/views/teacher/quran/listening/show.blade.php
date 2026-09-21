@extends('layouts.app')

@section('title', 'خطة الاستماع')

@section('content')
@php
    $authorization = app(\App\Services\AuthorizationService::class);
    $can = fn (string $permission) => $authorization->can(auth()->user(), $permission);
@endphp
<div class="max-w-6xl mx-auto">
    @include('quran.listening.plan-details', [
        'plan' => $plan,
        'listeningItems' => $listeningItems,
        'listeningSessions' => $listeningSessions,
        'reciters' => $reciters,
        'canTest' => ($canTest ?? true) && $can('quran_listening.test'),
        'canListen' => false,
        'indexRoute' => route('teacher.quran.batches.index'),
        'listenRoute' => $can('quran_listening.listen') ? fn ($item) => route('teacher.quran.listening.items.listen', $item) : null,
        'audioRoute' => fn ($item) => route('teacher.quran.listening.items.audio', $item),
        'progressRoute' => $can('quran_listening.listen') ? fn ($item) => route('teacher.quran.listening.items.progress', $item) : null,
        'testRoute' => $can('quran_listening.test') ? route('teacher.quran.listening.test', $plan) : null,
        'cancelRoute' => $can('quran_listening.update') ? route('teacher.quran.listening.cancel', $plan) : null,
        'khamsaRoute' => $can('quran_khamsa.view') ? fn ($review) => route('teacher.quran.khamsa.show', $review) : null,
    ])
</div>
@endsection
