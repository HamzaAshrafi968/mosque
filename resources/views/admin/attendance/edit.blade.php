@extends('layouts.app')

@section('title', 'تصحيح الحضور')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.attendance.index', ['date' => $session->date->toDateString()]) }}" class="text-sm text-emerald-700 hover:underline">← جلسات اليوم</a>
</div>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <h2 class="text-lg font-bold text-gray-800 p-4 border-b flex items-center justify-between">
        <span>تصحيح الحضور — {{ $session->section->classroom?->name }} / {{ $session->section->name }}</span>
        <span class="text-sm font-normal text-gray-500">{{ $session->date->translatedFormat('l Y-m-d') }}</span>
    </h2>
    <x-attendance-marks-form
        :students="$students"
        :records="$records"
        date="{{ $session->date->toDateString() }}"
        :action="route('admin.attendance.sessions.update', $session)"
        method="PATCH"
        submit-label="حفظ التصحيحات" />
</div>
@endsection
