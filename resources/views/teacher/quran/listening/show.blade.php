@extends('layouts.app')

@section('title', 'خطة الاستماع')

@section('content')
<div class="max-w-6xl mx-auto">
    @include('quran.listening.plan-details', [
        'plan' => $plan,
        'listeningItems' => $listeningItems,
        'listeningSessions' => $listeningSessions,
        'reciters' => $reciters,
        'canTest' => true,
        'canListen' => true,
        'indexRoute' => route('teacher.quran.batches.index'),
        'listenRoute' => fn ($item) => route('teacher.quran.listening.items.listen', $item),
        'audioRoute' => fn ($item) => route('teacher.quran.listening.items.audio', $item),
        'progressRoute' => fn ($item) => route('teacher.quran.listening.items.progress', $item),
        'testRoute' => route('teacher.quran.listening.test', $plan),
        'cancelRoute' => route('teacher.quran.listening.cancel', $plan),
        'khamsaRoute' => fn ($review) => route('teacher.quran.khamsa.show', $review),
    ])
</div>
@endsection
