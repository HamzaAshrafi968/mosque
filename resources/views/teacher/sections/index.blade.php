@extends('layouts.app')

@section('title', 'شعبي والطلاب')

@section('content')
<h1 class="text-2xl font-bold text-gray-800 mb-6">الشعب الموكلة إليّ</h1>

@forelse($sections as $section)
    <a href="{{ route('teacher.sections.show', $section) }}" class="block bg-white rounded-2xl shadow hover:shadow-lg transition mb-4 p-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-2xl">🏫</div>
                <div>
                    <div class="text-lg font-bold text-gray-800">شعبة {{ $section->name }}</div>
                    <div class="text-sm text-gray-500">
                        {{ $section->classroom?->name ?? '—' }}
                    </div>
                </div>
            </div>
            <div class="flex gap-6 text-sm text-gray-600">
                <div class="text-center">
                    <div class="text-xl font-bold text-gray-800">{{ $section->students_count }}</div>
                    <div class="text-xs text-gray-400">طالب نشط</div>
                </div>
                <div class="text-center">
                    <div class="text-xl font-bold text-gray-800">{{ $section->attendance_sessions_count }}</div>
                    <div class="text-xs text-gray-400">جلسة حضور</div>
                </div>
            </div>
        </div>
    </a>
@empty
    <div class="bg-white rounded-2xl shadow p-8 text-center text-gray-500">
        لم تُسند إليك أي شعب بعد — تواصل مع إدارة الجامع لتكليفك بالشعب.
    </div>
@endforelse
@endsection
