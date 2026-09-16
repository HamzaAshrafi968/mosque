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
    'indexRoute' => route('teacher.quran.batches.index'),
    'repeatRoute' => fn ($batch) => route('teacher.quran.batches.repeat', $batch),
    'journeyRoute' => fn ($student) => route('teacher.quran.students.journey', $student),
    'planRoute' => fn ($plan) => route('teacher.quran.listening.show', $plan),
    'khamsaRoute' => fn ($review) => route('teacher.quran.khamsa.show', $review),
    'reviewCompleteRoute' => fn ($item) => route('teacher.quran.khamsa.items.complete', $item),
    'reviewCancelRoute' => $review ? route('teacher.quran.khamsa.cancel', $review) : null,
    'memorizationStoreRoute' => route('teacher.quran.khamsa.memorization.store'),
    'memorizationDestroyRoute' => route('teacher.quran.khamsa.memorization.destroy'),
    'planListenRoute' => fn ($item) => route('teacher.quran.listening.items.listen', $item),
    'planAudioRoute' => fn ($item) => route('teacher.quran.listening.items.audio', $item),
    'planProgressRoute' => fn ($item) => route('teacher.quran.listening.items.progress', $item),
    'planTestRoute' => $plan ? route('teacher.quran.listening.test', $plan) : null,
    'planCancelRoute' => $plan ? route('teacher.quran.listening.cancel', $plan) : null,
    'sessionStartRoute' => fn ($student) => route('teacher.quran.batches.session-start', ['student_id' => $student->id]),
])
@endsection
