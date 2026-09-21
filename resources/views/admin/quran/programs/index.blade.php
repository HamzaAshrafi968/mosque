@extends('layouts.app')

@section('title', 'برامج الاستماع')

@section('content')
    <div class="p-4 md:p-6 max-w-7xl mx-auto space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">برامج الاستماع</h1>
            <p class="text-sm text-gray-500 mt-1">
                دورة الدفعات: تسميع 5 أجزاء مع الأخطاء ← اختبار تراكمي من الجزء 1 ← الدفعة التالية. مع «وين موصل» و«شو مسمع».
            </p>
        </div>

        {{-- تبويبات الأنواع --}}
        <div class="flex flex-wrap gap-2">
            @foreach ($types as $type)
                <a href="{{ route('admin.quran.programs.index', ['type' => $type->value]) }}"
                    class="px-4 py-2 rounded-xl text-sm font-bold border {{ $selectedType === $type ? 'bg-emerald-700 text-white border-emerald-700' : 'bg-white text-gray-600 border-gray-200 hover:border-emerald-300' }}">
                    {{ $type->label() }}
                </a>
            @endforeach
        </div>

        {{-- تسجيل في برنامج القراءات (قراءة من القراءات العشر) --}}
        @if ($selectedType === \App\Enums\ProgramType::Readings && ($canEnroll ?? false))
            <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-5">
                <h2 class="font-extrabold text-emerald-900">تسجيل طالب في برنامج القراءات</h2>
                <p class="text-xs text-emerald-800/80 mt-1 mb-4">
                    اختر الطالب والقراءة — يمكن تسجيل الطالب في أكثر من قراءة (قراءات متوازية).
                </p>
                <form method="POST" action="{{ route('admin.quran.programs.enroll') }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="min-w-56 flex-1">
                        <label class="block text-xs font-bold text-gray-600 mb-1">الطالب</label>
                        <select name="student_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                            <option value="">اختر الطالب</option>
                            @foreach ($students as $student)
                                <option value="{{ $student->id }}" @selected(old('student_id') === $student->id)>{{ $student->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-56 flex-1">
                        <label class="block text-xs font-bold text-gray-600 mb-1">القراءة</label>
                        <select name="reading" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                            @foreach ($readings as $reading)
                                <option value="{{ $reading->value }}" @selected(old('reading') === $reading->value)>{{ $reading->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-6 py-2 rounded-xl text-sm">تسجيل في القراءات</button>
                </form>
            </div>
        @endif

        {{-- تصفية بالطالب --}}
        <div class="bg-white rounded-2xl shadow p-5">
            <form method="GET" action="{{ route('admin.quran.programs.index') }}" class="flex flex-wrap items-end gap-3">
                <input type="hidden" name="type" value="{{ $selectedType->value }}">
                <div class="min-w-56 flex-1">
                    <label class="block text-xs font-bold text-gray-500 mb-1">تصفية بالطالب</label>
                    <select name="student_id" onchange="this.form.submit()"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">كل الطلاب</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}" @selected($selectedStudent?->id === $student->id)>{{ $student->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="bg-gray-800 hover:bg-gray-900 text-white font-bold px-5 py-2 rounded-xl text-sm">عرض</button>
            </form>
        </div>

        {{-- قائمة البرامج --}}
        <div class="bg-white rounded-2xl shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-right text-[11px] text-gray-400 border-b border-gray-100 bg-gray-50">
                            <th class="px-4 py-3">الطالب</th>
                            <th class="px-4 py-3">البرنامج</th>
                            <th class="px-4 py-3">الحالة</th>
                            <th class="px-4 py-3">آخر تحديث</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($programs as $row)
                            <tr class="border-b border-gray-50 hover:bg-emerald-50/40 {{ ($program->id ?? null) === $row->id ? 'bg-emerald-50/70' : '' }}">
                                <td class="px-4 py-3 font-bold text-gray-700">
                                    <a href="{{ route('admin.quran.programs.index', ['type' => $row->type->value, 'program_id' => $row->id]) }}"
                                        class="hover:text-emerald-700 hover:underline">{{ $row->student?->name ?? '—' }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-500">{{ $row->displayLabel() }}</td>
                                <td class="px-4 py-3">
                                    <span class="text-[11px] font-bold px-2 py-0.5 rounded-full {{ $row->isActive() ? 'bg-emerald-100 text-emerald-800' : ($row->isCompleted() ? 'bg-sky-100 text-sky-800' : 'bg-gray-100 text-gray-600') }}">
                                        {{ $row->status->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-400 text-xs">{{ $row->updated_at?->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-3 text-left">
                                    <a href="{{ route('admin.quran.programs.index', ['type' => $row->type->value, 'program_id' => $row->id]) }}"
                                        class="text-xs font-bold text-emerald-700 hover:underline">عرض الدورة</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-400">لا توجد برامج في هذا التبويب بعد.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($programs->hasPages())
                <div class="p-4 border-t border-gray-100">{{ $programs->links() }}</div>
            @endif
        </div>

        {{-- دورة البرنامج المحدد --}}
        @include('quran.programs.cycle')
    </div>
@endsection
