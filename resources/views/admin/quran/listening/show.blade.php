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
        'indexRoute' => route('admin.quran.batches.index'),
        'listenRoute' => fn ($item) => route('admin.quran.listening.items.listen', $item),
        'audioRoute' => fn ($item) => route('admin.quran.listening.items.audio', $item),
        'progressRoute' => fn ($item) => route('admin.quran.listening.items.progress', $item),
        'testRoute' => route('admin.quran.listening.test', $plan),
        'cancelRoute' => route('admin.quran.listening.cancel', $plan),
        'khamsaRoute' => fn ($review) => route('admin.quran.khamsa.show', $review),
    ])
</div>
@endsection
