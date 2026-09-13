@extends('layouts.app')

@section('title', 'اختبارات الحفاظ الشهرية')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div>
        <h2 class="text-2xl font-extrabold text-gray-800">✅ اختبارات الحفاظ الشهرية</h2>
        <p class="text-sm text-gray-500 mt-1">شبكة أشهر السنة — «لم يُختبر» لا تعني «راسب»</p>
    </div>

    <x-year-months-grid
        :year="$year"
        :months="$months"
        :hafiz-count="$hafizCount"
        route-name="teacher.quran.exams.month"
        year-route-name="teacher.quran.exams.index" />
</div>
@endsection
