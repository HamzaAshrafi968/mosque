@extends('layouts.app')

@section('title', 'برامجي')

@section('content')
    <div class="p-4 md:p-6 max-w-6xl mx-auto space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">برامجي</h1>
            <p class="text-sm text-gray-500 mt-1">
                برامج الاستماع: يسمّعك الأستاذ أجزاء الدفعة مع تسجيل الأخطاء، ثم يُسجَّل اختبارك. تابع «وين موصل» و«شو مسمع».
            </p>
        </div>

        @if ($programs->isEmpty())
            <div class="bg-white rounded-2xl shadow p-8 text-center text-gray-500">
                لا توجد برامج استماع مسجّلة لك حالياً — سجّل الدخول في البرنامج التدريبي أو التأهيلي أو الإجازة
                ليظهر هنا تقدمك واختباراتك.
            </div>
        @else
            <div class="flex flex-wrap gap-2">
                @foreach ($programs as $row)
                    <a href="{{ route('student.quran-programs.index', ['program_id' => $row->id]) }}"
                        class="px-4 py-2 rounded-xl text-sm font-bold border {{ $selectedProgram?->id === $row->id ? 'bg-emerald-700 text-white border-emerald-700' : 'bg-white text-gray-600 border-gray-200 hover:border-emerald-300' }}">
                        {{ $row->type->label() }}
                        <span class="text-[10px] font-bold opacity-80">({{ $row->status->label() }})</span>
                    </a>
                @endforeach
            </div>

            @include('quran.programs.cycle')
        @endif
    </div>
@endsection
