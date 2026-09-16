@extends('layouts.app')

@section('title', 'دفعات الحفظ')

@section('content')
@include('quran.batches.index-panel', [
    'batches' => $batches,
    'selectedStudent' => $selectedStudent,
    'states' => $states,
    'currentBatch' => $currentBatch,
    'plan' => $plan,
    'review' => $review,
    'listeningItems' => $listeningItems,
    'memorizedJuz' => $memorizedJuz,
    'listeningSessions' => $listeningSessions,
    'timeline' => $timeline,
    'memorizationProgress' => $memorizationProgress,
    'reciters' => $reciters,
    'tasmeeResults' => $tasmeeResults,
    'students' => $students,
    'statuses' => $statuses,
    'minimumPassingPercentage' => $minimumPassingPercentage,
    'indexRoute' => route('admin.quran.batches.index'),
    'repeatRoute' => fn ($batch) => route('admin.quran.batches.repeat', $batch),
    'journeyRoute' => fn ($student) => route('admin.quran.journey', $student),
    'planRoute' => fn ($plan) => route('admin.quran.listening.show', $plan),
    'khamsaRoute' => fn ($review) => route('admin.quran.khamsa.show', $review),
    'reviewCompleteRoute' => fn ($item) => route('admin.quran.khamsa.items.complete', $item),
    'reviewCancelRoute' => $review ? route('admin.quran.khamsa.cancel', $review) : null,
    'memorizationStoreRoute' => route('admin.quran.khamsa.memorization.store'),
    'memorizationDestroyRoute' => route('admin.quran.khamsa.memorization.destroy'),
    'planListenRoute' => fn ($item) => route('admin.quran.listening.items.listen', $item),
    'planAudioRoute' => fn ($item) => route('admin.quran.listening.items.audio', $item),
    'planProgressRoute' => fn ($item) => route('admin.quran.listening.items.progress', $item),
    'planTestRoute' => $plan ? route('admin.quran.listening.test', $plan) : null,
    'planCancelRoute' => $plan ? route('admin.quran.listening.cancel', $plan) : null,
    'sessionStartRoute' => fn ($student) => route('admin.quran.batches.session-start', ['student_id' => $student->id]),
])
@endsection
