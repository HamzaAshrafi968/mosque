@extends('layouts.app')

@section('title', 'القرآن والبرامج')

@section('content')
@php
    $authorization = app(\App\Services\AuthorizationService::class);
    $can = fn (string $permission) => $authorization->can(auth()->user(), $permission);
    $canPrograms = $can('quran_training.view');
    $qualifyingUrl = $canPrograms
        ? route('teacher.quran.programs.index', ['type' => 'qualifying'])
        : route('teacher.quran.qualifying.index');
    $ijazahUrl = $canPrograms
        ? route('teacher.quran.programs.index', ['type' => 'ijazah'])
        : route('teacher.quran.ijazah.index');
@endphp
<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-800">🕌 القرآن والبرامج</h2>
            <p class="text-sm text-gray-500 mt-1">طلابك ونطاق إشرافك في التسميع والبرامج القرآنية</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            @if ($can('quran.tasmee.create'))
                <a href="{{ route('teacher.quran.tasmee.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">+ تسجيل تسميع</a>
            @endif
            @if ($can('qualifying.create'))
                <a href="{{ route('teacher.quran.qualifying.evaluations.create') }}" class="bg-white border border-gray-300 text-gray-700 text-sm font-bold px-4 py-2 rounded-lg">+ تقييم أسبوعي</a>
            @endif
            @if ($can('ijazah.create'))
                <a href="{{ route('teacher.quran.ijazah.evaluations.create') }}" class="bg-white border border-gray-300 text-gray-700 text-sm font-bold px-4 py-2 rounded-lg">+ تقييم شهري</a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4">
            <div class="text-3xl font-extrabold text-emerald-700">{{ $myStudentCount }}</div>
            <div class="text-xs text-gray-500 mt-1 font-bold">طلاب ضمن نطاقي</div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4">
            <div class="text-3xl font-extrabold text-sky-700">{{ $tasmeeStats['new'] }} <span class="text-sm text-gray-400">/ {{ $tasmeeStats['newThisWeek'] }} هذا الأسبوع</span></div>
            <div class="text-xs text-gray-500 mt-1 font-bold">تسميع جديد</div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4">
            <div class="text-3xl font-extrabold text-amber-700">{{ $tasmeeStats['revision'] }} <span class="text-sm text-gray-400">/ {{ $tasmeeStats['revisionThisWeek'] }} هذا الأسبوع</span></div>
            <div class="text-xs text-gray-500 mt-1 font-bold">مراجعة</div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4">
            <div class="text-3xl font-extrabold text-teal-700">{{ $untestedMonth->count() }}</div>
            <div class="text-xs text-gray-500 mt-1 font-bold">لم يختبروا في {{ $monthLabel($currentMonth) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b bg-gray-50 flex justify-between items-center">
                <span class="font-bold text-gray-800">⚠️ بحاجة إلى مراجعة</span>
                @if ($can('quran_batch.view'))
                    <a href="{{ route('teacher.quran.batches.index') }}" class="text-xs text-emerald-700 hover:underline">كل التسميع</a>
                @endif
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($weakStudents as $student)
                    <a href="{{ route('teacher.quran.students.journey', $student) }}" class="px-5 py-3 flex justify-between hover:bg-gray-50 text-sm">
                        <span class="font-bold text-gray-800">{{ $student->name }}</span>
                        <span class="text-xs text-gray-400">آخر نتيجة: يحتاج مراجعة</span>
                    </a>
                @empty
                    <div class="px-5 py-6 text-center text-gray-400 text-sm">لا يوجد طلاب بحاجة لمراجعة</div>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b bg-gray-50 flex justify-between items-center">
                <span class="font-bold text-gray-800">📋 طلاب البرنامج التأهيلي</span>
                @if ($can('qualifying.view') || $canPrograms)
                    <a href="{{ $qualifyingUrl }}" class="text-xs text-emerald-700 hover:underline">إدارة</a>
                @endif
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($qualifyingStudents as $student)
                    <a href="{{ route('teacher.quran.students.journey', $student) }}" class="px-5 py-3 flex justify-between hover:bg-gray-50 text-sm">
                        <span class="font-bold text-gray-800">{{ $student->name }}</span>
                        <span class="text-xs text-gray-400">أسبوعي</span>
                    </a>
                @empty
                    <div class="px-5 py-6 text-center text-gray-400 text-sm">لا يوجد طلاب تأهيلي ضمن نطاقك</div>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b bg-gray-50 flex justify-between items-center">
                <span class="font-bold text-gray-800">📜 طلاب برنامج الإجازة</span>
                @if ($can('ijazah.view') || $canPrograms)
                    <a href="{{ $ijazahUrl }}" class="text-xs text-emerald-700 hover:underline">إدارة</a>
                @endif
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($ijazahStudents as $student)
                    <a href="{{ route('teacher.quran.students.journey', $student) }}" class="px-5 py-3 flex justify-between hover:bg-gray-50 text-sm">
                        <span class="font-bold text-gray-800">{{ $student->name }}</span>
                        <span class="text-xs text-gray-400">شهري</span>
                    </a>
                @empty
                    <div class="px-5 py-6 text-center text-gray-400 text-sm">لا يوجد طلاب إجازة ضمن نطاقك</div>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b bg-gray-50 flex justify-between items-center">
                <span class="font-bold text-gray-800">📖 طلاب برنامج القراءات</span>
                @if ($canPrograms)
                    <a href="{{ route('teacher.quran.programs.index', ['type' => 'readings']) }}" class="text-xs text-emerald-700 hover:underline">إدارة</a>
                @endif
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($readingsStudents as $student)
                    <a href="{{ route('teacher.quran.programs.index', ['type' => 'readings', 'student_id' => $student->id]) }}" class="px-5 py-3 flex justify-between hover:bg-gray-50 text-sm">
                        <span class="font-bold text-gray-800">{{ $student->name }}</span>
                        <span class="text-xs text-gray-400">قراءات</span>
                    </a>
                @empty
                    <div class="px-5 py-6 text-center text-gray-400 text-sm">لا يوجد طلاب قراءات ضمن نطاقك</div>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b bg-gray-50 flex justify-between items-center">
                <span class="font-bold text-gray-800">🕊️ لقاءاتي القادمة</span>
                @if ($can('faith_meetings.view'))
                    <a href="{{ route('teacher.quran.faith-meetings.index') }}" class="text-xs text-emerald-700 hover:underline">الكل</a>
                @endif
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($myMeetings as $meeting)
                    <a href="{{ route('teacher.quran.faith-meetings.show', $meeting) }}" class="px-5 py-3 hover:bg-gray-50 text-sm block">
                        <span class="font-bold text-gray-800">{{ $meeting->title }}</span>
                        <span class="block text-xs text-gray-400 mt-0.5">{{ $meeting->date->format('Y-m-d') }} · {{ $meeting->student_attendances_count }} طالباً</span>
                    </a>
                @empty
                    <div class="px-5 py-6 text-center text-gray-400 text-sm">لا لقاءات قادمة لك</div>
                @endforelse
            </div>
            @if($studentAttendancePending > 0)
                <div class="px-5 py-2 bg-amber-50 text-amber-800 text-xs font-bold">⏰ {{ $studentAttendancePending }} تسجيل حضور معلق</div>
            @endif
        </div>

        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b bg-gray-50 flex justify-between items-center">
                <span class="font-bold text-gray-800">🔄 مراجعات (إعادة) معلقة لحفاظي</span>
                @if ($can('hafiz_exams.view'))
                    <a href="{{ route('teacher.quran.exams.index') }}" class="text-xs text-emerald-700 hover:underline">اختبارات الحفاظ</a>
                @endif
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($pendingRevisions as $revision)
                    <a href="{{ route('teacher.quran.exams.show', $revision->exam) }}" class="px-5 py-3 flex justify-between hover:bg-gray-50 text-sm">
                        <span class="font-bold text-gray-800">
                            {{ $revision->exam->student?->name ?? 'طالب' }}
                            <span class="text-xs text-gray-400 font-normal">— {{ $revision->juz ? 'جزء '.$revision->juz : '' }} {{ $revision->amount ? 'مقدار '.$revision->amount : '' }}</span>
                        </span>
                        <span class="text-xs text-amber-600 font-bold">قيد التنفيذ</span>
                    </a>
                @empty
                    <div class="px-5 py-6 text-center text-gray-400 text-sm">لا توجد مراجعات معلقة — ممتاز 🎉</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
