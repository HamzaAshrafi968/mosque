@extends('layouts.app')

@section('title', 'الدوامات')

@section('content')
@php
    $authorization = app(\App\Services\AuthorizationService::class);
    $can = fn (string $permission) => $authorization->can(auth()->user(), $permission);
    $currentSession = $sessions->firstWhere('id', $currentSessionId);
@endphp

<div class="bg-white rounded-xl shadow overflow-hidden mb-6">
    <div class="px-4 py-4 bg-gradient-to-l from-emerald-800 to-emerald-700 text-white flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold">الدوامات (الدورة الأولى / الثانية)</h1>
            <p class="text-sm text-emerald-100 mt-1">قسّم الطلاب والأساتذة والشعب على دوامات، ثم اختر الدوام من الشريط العلوي لتظهر بياناته فقط</p>
        </div>
        <div class="flex items-center gap-2 text-sm bg-white/10 rounded-lg px-3 py-2">
            <span>تعرض حالياً:</span>
            <span class="font-bold">{{ $currentSession?->display_name ?: 'كل الدوامات' }}</span>
        </div>
    </div>
    @if($unassigned['students'] > 0 || $unassigned['teachers'] > 0 || $unassigned['sections'] > 0 || $unassigned['classrooms'] > 0)
        <div class="px-4 py-3 bg-amber-50 border-t border-amber-200 text-sm text-amber-800 flex flex-wrap items-center gap-x-4 gap-y-2">
            <span class="font-bold">بيانات غير مرتبطة بدوام:</span>
            <span>الطلاب: {{ $unassigned['students'] }}</span>
            <span>الأساتذة: {{ $unassigned['teachers'] }}</span>
            <span>الشعب: {{ $unassigned['sections'] }}</span>
            <span>الصفوف: {{ $unassigned['classrooms'] }}</span>
            <details class="ms-auto">
                <summary class="cursor-pointer text-amber-700 hover:underline">توزيعهم على دوام...</summary>
                @if ($can('sessions.update'))
                    <form method="POST" action="{{ route('admin.sessions.assign-unassigned') }}" class="flex flex-wrap gap-2 mt-2">
                        @csrf
                        <select name="type" class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm" required>
                            <option value="students">الطلاب</option>
                            <option value="teachers">الأساتذة</option>
                            <option value="sections">الشعب</option>
                            <option value="classrooms">الصفوف (مع شعبها وطلابها)</option>
                        </select>
                        <select name="study_session_id" class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm" required>
                            @foreach($sessions as $session)
                                <option value="{{ $session->id }}">{{ $session->display_name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold px-3 py-1.5 rounded-lg">توزيع الكل</button>
                    </form>
                @endif
            </details>
        </div>
    @endif
</div>

@if ($can('sessions.create'))
<div class="bg-white rounded-xl shadow overflow-hidden p-4 mb-6">
    <h2 class="font-bold text-gray-800 mb-3">إضافة دوام جديد</h2>
    <form method="POST" action="{{ route('admin.sessions.store') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3">
        @csrf
        <input type="text" name="name" required value="{{ old('name') }}" placeholder="اسم الدوام (مثال: الدوام الأول)"
               class="w-full border border-gray-300 rounded-lg px-3 py-2">
        <select name="gender" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            <option value="">غير محدد (مختلط)</option>
            @foreach(\App\Models\StudySession::GENDERS as $value => $label)
                <option value="{{ $value }}" @selected(old('gender') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <input type="text" name="description" value="{{ old('description') }}" placeholder="وصف اختياري"
               class="w-full border border-gray-300 rounded-lg px-3 py-2">
        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">إضافة الدوام</button>
    </form>
    <p class="text-xs text-gray-400 mt-2">يمكن تكرار الاسم نفسه بجنس مختلف (مثال: «الدوام الأول (ذكور)» و«الدوام الأول (إناث)») — لكن لا يمكن تكرار نفس الاسم بنفس الجنس.</p>
</div>
@endif

@if($sessions->isNotEmpty())
    <h2 class="font-bold text-gray-800 mb-3">الدوامات الحالية</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach($sessions as $session)
            <div class="bg-white rounded-xl shadow overflow-hidden @if((string) $session->id === (string) $currentSessionId) ring-2 ring-emerald-500 @endif">
                <div class="px-4 py-3 flex items-center justify-between border-b">
                    <div>
                        <div class="font-bold text-gray-800 flex items-center gap-2 flex-wrap">
                            {{ $session->name }}
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold {{ match($session->gender) {
                                'male' => 'bg-sky-100 text-sky-800',
                                'female' => 'bg-pink-100 text-pink-800',
                                default => 'bg-gray-100 text-gray-600',
                            } }}">{{ $session->genderLabel() }}</span>
                            @if((string) $session->id === (string) $currentSessionId)
                                <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">المعروض حالياً</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-500">{{ $session->description ?: '—' }}</div>
                    </div>
                    @if(! $session->is_active)
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-700">موقوف</span>
                    @endif
                </div>
                <div class="p-4 grid grid-cols-4 gap-2 text-center text-sm">
                    <div><div class="font-bold text-emerald-700">{{ $session->students_count }}</div><div class="text-xs text-gray-500">طالب</div></div>
                    <div><div class="font-bold text-gray-700">{{ $session->teachers_count }}</div><div class="text-xs text-gray-500">أستاذ</div></div>
                    <div><div class="font-bold text-gray-700">{{ $session->classrooms_count }}</div><div class="text-xs text-gray-500">صف</div></div>
                    <div><div class="font-bold text-gray-700">{{ $session->sections_count }}</div><div class="text-xs text-gray-500">شعبة</div></div>
                </div>
                <div class="px-4 pb-3 text-xs text-gray-500">
                    <div class="flex flex-wrap items-center gap-1">
                        <span class="font-bold text-gray-600">البرامج:</span>
                        @if($session->programs->isEmpty())
                            <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">كل البرامج</span>
                        @else
                            @foreach($session->programs as $program)
                                <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">{{ $program->name }}</span>
                            @endforeach
                        @endif
                    </div>
                    @if ($can('sessions.update'))
                    <details class="mt-2">
                        <summary class="cursor-pointer text-emerald-700 hover:underline">تخصيص البرامج المتاحة لهذا الدوام...</summary>
                        <form method="POST" action="{{ route('admin.sessions.programs', $session) }}" class="mt-2 space-y-1 bg-gray-50 border border-gray-200 rounded-lg p-3">
                            @csrf
                            @foreach($programs as $program)
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="programs[]" value="{{ $program->id }}"
                                           @checked($session->programs->contains('id', $program->id))
                                           class="rounded border-gray-300 text-emerald-700">
                                    {{ $program->name }}
                                </label>
                            @endforeach
                            <p class="text-[11px] text-gray-400">اترك الكل فارغاً ليظهر كل البرامج في هذا الدوام (مثال: الدوام الأول للتحفيظ والإجازة، والثاني للتسميع فقط).</p>
                            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-3 py-1.5 rounded-lg">حفظ البرامج</button>
                        </form>
                    </details>
                    @endif
                </div>
                <div class="px-4 pb-4 flex flex-wrap items-center gap-2">
                    <form method="POST" action="{{ route('admin.sessions.switch') }}">
                        @csrf
                        <input type="hidden" name="study_session_id" value="{{ $session->id }}">
                        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-3 py-1.5 rounded-lg">عرض هذا الدوام</button>
                    </form>
                    @if ($can('sessions.update'))
                    <details class="inline-block">
                        <summary class="cursor-pointer bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold px-3 py-1.5 rounded-lg">تعديل</summary>
                        <form method="POST" action="{{ route('admin.sessions.update', $session) }}" class="mt-2 space-y-2 bg-gray-50 border border-gray-200 rounded-lg p-3 min-w-56">
                            @csrf
                            @method('PATCH')
                            <input type="text" name="name" value="{{ old('name', $session->name) }}" required
                                   class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                            <select name="gender" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                                <option value="">غير محدد (مختلط)</option>
                                @foreach(\App\Models\StudySession::GENDERS as $value => $label)
                                    <option value="{{ $value }}" @selected(old('gender', $session->gender) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="description" value="{{ old('description', $session->description) }}"
                                   class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" placeholder="وصف اختياري">
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" @checked($session->is_active) class="rounded border-gray-300 text-emerald-700">
                                دوام نشط
                            </label>
                            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-3 py-1.5 rounded-lg">حفظ التعديل</button>
                        </form>
                    </details>
                    @endif
                    @if ($can('sessions.delete'))
                    <form method="POST" action="{{ route('admin.sessions.destroy', $session) }}" class="ms-auto"
                          onsubmit="return confirm('حذف دوام {{ $session->name }}؟ (يمنع إذا كان عليه طلاب أو أساتذة أو شعب)')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-600 hover:underline text-xs px-2 py-1.5">حذف</button>
                    </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
