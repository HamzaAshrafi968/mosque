@extends('layouts.app')

@section('title', 'خطة الاستماع')

@section('content')
<div class="max-w-6xl mx-auto">
    @include('quran.listening.plan-details', [
        'plan' => $plan,
        'memorizedJuz' => $memorizedJuz,
        'listeningItems' => $plan->items->where('status', \App\Enums\QuranListeningItemStatus::Listened),
        'listeningSessions' => collect(),
        'reciters' => $reciters,
        'canTest' => false,
        'canListen' => true,
        'indexRoute' => route('student.quran-profile'),
        'listenRoute' => fn ($item) => route('student.quran-listening.items.listen', $item),
        'audioRoute' => fn ($item) => route('student.quran-listening.items.audio', $item),
        'progressRoute' => fn ($item) => route('student.quran-listening.items.progress', $item),
        'testRoute' => null,
        'cancelRoute' => null,
    ])
</div>
@endsection
