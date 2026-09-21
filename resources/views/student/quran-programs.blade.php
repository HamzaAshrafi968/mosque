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

        {{-- المرحلة المتقدمة الاختيارية: برنامج القراءات (بعد إتمام الإجازة) --}}
        @if ($canSelfEnroll)
            @if ($hasCompletedIjazah)
                @php
                    $activeReadings = $programs
                        ->where('type', \App\Enums\ProgramType::Readings)
                        ->where('status', \App\Enums\QuranListeningProgramStatus::Active);
                @endphp
                <div class="bg-violet-50 border border-violet-200 rounded-2xl p-5">
                    <h2 class="font-extrabold text-violet-900">المرحلة المتقدمة — برنامج القراءات (اختياري)</h2>
                    <p class="text-xs text-violet-800/80 mt-1 mb-4">
                        ما شاء الله، أتممت برنامج الإجازة. يمكنك الآن — إن رغبت — التسجيل في قراءة من القراءات العشر.
                        التسجيل اختياري تماماً، ويمكنك التسجيل في أكثر من قراءة (قراءات متوازية).
                    </p>

                    @if ($activeReadings->isNotEmpty())
                        <div class="flex flex-wrap gap-2 mb-4">
                            @foreach ($activeReadings as $row)
                                <a href="{{ route('student.quran-programs.index', ['program_id' => $row->id]) }}"
                                    class="text-[11px] font-bold px-3 py-1 rounded-full bg-white border border-violet-200 text-violet-800 hover:border-violet-400">
                                    {{ $row->readingLabel() }}
                                </a>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('student.quran-programs.enroll') }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        <div class="min-w-56 flex-1">
                            <label class="block text-xs font-bold text-gray-600 mb-1">القراءة</label>
                            <select name="reading" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                                @foreach ($readings as $reading)
                                    <option value="{{ $reading->value }}" @selected(old('reading') === $reading->value)>
                                        {{ $reading->label() }}@if ($enrolledReadings->contains($reading)) — مسجّلة مسبقاً @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('reading')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <button class="bg-violet-700 hover:bg-violet-800 text-white font-bold px-6 py-2 rounded-xl text-sm">سجّلني في القراءات</button>
                    </form>
                </div>
            @else
                <div class="bg-gray-50 border border-gray-200 rounded-2xl p-5">
                    <h2 class="font-extrabold text-gray-700">🔒 برنامج القراءات — المرحلة المتقدمة الاختيارية</h2>
                    <p class="text-xs text-gray-500 mt-1">
                        يُفتح التسجيل الاختياري في برنامج القراءات (القراءات العشر) بعد إتمام برنامج الإجازة.
                    </p>
                </div>
            @endif
        @endif

        @if ($programs->isEmpty())
            <div class="bg-white rounded-2xl shadow p-8 text-center text-gray-500">
                لا توجد برامج استماع مسجّلة لك حالياً — عند تسجيلك في البرنامج التأهيلي أو الإجازة
                أو برنامج القراءات يظهر هنا تقدمك واختباراتك.
            </div>
        @else
            <div class="flex flex-wrap gap-2">
                @foreach ($programs as $row)
                    <a href="{{ route('student.quran-programs.index', ['program_id' => $row->id]) }}"
                        class="px-4 py-2 rounded-xl text-sm font-bold border {{ $selectedProgram?->id === $row->id ? 'bg-emerald-700 text-white border-emerald-700' : 'bg-white text-gray-600 border-gray-200 hover:border-emerald-300' }}">
                        {{ $row->displayLabel() }}
                        <span class="text-[10px] font-bold opacity-80">({{ $row->status->label() }})</span>
                    </a>
                @endforeach
            </div>

            @include('quran.programs.cycle')
        @endif
    </div>
@endsection
