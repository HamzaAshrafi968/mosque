@extends('layouts.app')

@section('title', 'جدولي الدراسي')

@php
    $days = [
        0 => 'الأحد',
        1 => 'الاثنين',
        2 => 'الثلاثاء',
        3 => 'الأربعاء',
        4 => 'الخميس',
        5 => 'الجمعة',
        6 => 'السبت',
    ];
@endphp

@section('content')
<div class="mb-4">
    <button onclick="window.print()" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">طباعة الجدول</button>
</div>

@foreach($days as $num => $dayName)
    @if($schedules->has($num))
        <div class="bg-white rounded-xl shadow overflow-hidden mb-6">
            <h2 class="text-lg font-bold text-gray-800 p-4 border-b">{{ $dayName }}</h2>
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-sm">
                        <th class="px-4 py-3 text-right">الوقت</th>
                        <th class="px-4 py-3 text-right">المادة</th>
                        <th class="px-4 py-3 text-right">الصف</th>
                        <th class="px-4 py-3 text-right">الشعبة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($schedules[$num] as $schedule)
                        <tr>
                            <td class="px-4 py-3 border-t">{{ $schedule->starts_at }} - {{ $schedule->ends_at }}</td>
                            <td class="px-4 py-3 border-t">{{ $schedule->subject?->name }}</td>
                            <td class="px-4 py-3 border-t">{{ $schedule->classroom?->name }}</td>
                            <td class="px-4 py-3 border-t">{{ $schedule->section?->name ?? 'كل الشعب' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endforeach

@if($schedules->isEmpty())
    <div class="bg-white rounded-xl shadow p-6 text-center text-gray-500">لا يوجد جدول دراسي</div>
@endif
@endsection
