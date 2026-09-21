@extends('layouts.app')

@section('title', 'دفعات الحفظ')

@section('content')
@php
    $authorization = app(\App\Services\AuthorizationService::class);
    $can = fn (string $permission) => $authorization->can(auth()->user(), $permission);
    $canBatchUpdate = $can('quran_batch.update');
    $canTest = $can('quran_listening.test');
    $canKhamsaComplete = $can('quran_khamsa.complete');
    $canKhamsaUpdate = $can('quran_khamsa.update');
    $canMemorization = $can('quran.memorization.manage');
    $canPlanListen = $can('quran_listening.listen');
    $canPlanUpdate = $can('quran_listening.update');
    $canSessionStart = $can('quran.tasmee.create') || $can('quran_review.create');
@endphp
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
    'repeatRoute' => $canBatchUpdate ? fn ($batch) => route('teacher.quran.batches.repeat', $batch) : null,
    'batchTestRoute' => ($canTest && $currentBatch) ? route('teacher.quran.batches.test', $currentBatch) : null,
    'placementTestRoute' => ($canTest && $currentBatch) ? route('teacher.quran.batches.placement-test', $currentBatch) : null,
    'batchRetakeRoute' => ($canBatchUpdate && $currentBatch) ? route('teacher.quran.batches.retake', $currentBatch) : null,
    'journeyRoute' => $can('quran.tasmee.view') ? fn ($student) => route('teacher.quran.students.journey', $student) : null,
    'planRoute' => $can('quran_listening.view') ? fn ($plan) => route('teacher.quran.listening.show', $plan) : null,
    'khamsaRoute' => $can('quran_khamsa.view') ? fn ($review) => route('teacher.quran.khamsa.show', $review) : null,
    'reviewCompleteRoute' => $canKhamsaComplete ? fn ($item) => route('teacher.quran.khamsa.items.complete', $item) : null,
    'reviewCancelRoute' => ($canKhamsaUpdate && $review) ? route('teacher.quran.khamsa.cancel', $review) : null,
    'retakeCancelRoute' => ($canKhamsaUpdate && $retakeReview) ? route('teacher.quran.khamsa.cancel', $retakeReview) : null,
    'memorizationStoreRoute' => $canMemorization ? route('teacher.quran.khamsa.memorization.store') : null,
    'memorizationDestroyRoute' => $canMemorization ? route('teacher.quran.khamsa.memorization.destroy') : null,
    'planListenRoute' => $canPlanListen ? fn ($item) => route('teacher.quran.listening.items.listen', $item) : null,
    'planAudioRoute' => fn ($item) => route('teacher.quran.listening.items.audio', $item),
    'planProgressRoute' => $canPlanListen ? fn ($item) => route('teacher.quran.listening.items.progress', $item) : null,
    'planTestRoute' => ($canTest && $plan) ? route('teacher.quran.listening.test', $plan) : null,
    'planCancelRoute' => ($canPlanUpdate && $plan) ? route('teacher.quran.listening.cancel', $plan) : null,
    'sessionStartRoute' => $canSessionStart ? fn ($student) => route('teacher.quran.batches.session-start', ['student_id' => $student->id]) : null,
])
@endsection
