@props(['status'])

@php
    $status = $status instanceof \App\Enums\AttendanceStatus ? $status : \App\Enums\AttendanceStatus::tryFrom($status);
    $colors = match ($status) {
        \App\Enums\AttendanceStatus::Present => 'bg-green-100 text-green-800',
        \App\Enums\AttendanceStatus::Absent => 'bg-red-100 text-red-800',
        \App\Enums\AttendanceStatus::Late => 'bg-yellow-100 text-yellow-800',
        \App\Enums\AttendanceStatus::Excused => 'bg-sky-100 text-sky-800',
        default => 'bg-gray-100 text-gray-600',
    };
@endphp

@if($status)
    <span class="px-2 py-0.5 rounded-full text-xs font-bold whitespace-nowrap {{ $colors }}">{{ $status->label() }}</span>
@else
    <span class="text-gray-300">—</span>
@endif
