@extends('layouts.app')

@section('title', 'البرامج والتخصصات')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-800">🗂️ البرامج والتخصصات</h2>
            <p class="text-sm text-gray-500 mt-1">برنامج التحفيظ، الإجازة، اختبارات الحفظ، الدورات الشرعية، البرامج القرآنية — ولكل برنامج فتراته وخصائصه</p>
        </div>
        <a href="{{ route('admin.programs.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">+ برنامج جديد</a>
    </div>

    <form method="GET" action="{{ route('admin.programs.index') }}" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        <div class="md:col-span-2">
            <label class="block text-xs font-bold text-gray-600 mb-1">بحث بالاسم</label>
            <input type="text" name="search" value="{{ $search }}" placeholder="اسم البرنامج" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">النوع</label>
            <select name="type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">الكل</option>
                @foreach($programTypes as $programType)
                    <option value="{{ $programType->value }}" @selected($type === $programType)>{{ $programType->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">الحالة</label>
            <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">الكل</option>
                <option value="1" @selected(request('status') === '1')>مفعّل</option>
                <option value="0" @selected(request('status') === '0')>معطّل</option>
            </select>
        </div>
        <div class="md:col-span-4 flex gap-2">
            <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">تصفية</button>
            <a href="{{ route('admin.programs.index') }}" class="text-gray-500 text-sm px-3 py-2 hover:underline">إعادة تعيين</a>
        </div>
    </form>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @forelse($programs as $program)
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 flex flex-col gap-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="w-3.5 h-3.5 rounded-full shrink-0" style="background: {{ $program->color ?: $program->type->color() }}"></span>
                        <div class="min-w-0">
                            <div class="font-extrabold text-gray-800 truncate">{{ $program->name }}</div>
                            <div class="text-[11px] text-gray-400 font-mono">{{ $program->code }}</div>
                        </div>
                    </div>
                    <span @class([
                        'px-2 py-0.5 rounded-full text-[11px] font-bold whitespace-nowrap',
                        'bg-emerald-100 text-emerald-800' => $program->is_active,
                        'bg-gray-100 text-gray-500' => ! $program->is_active,
                    ])>{{ $program->is_active ? 'مفعّل' : 'معطّل' }}</span>
                </div>

                <div class="flex items-center gap-2 flex-wrap text-[11px]">
                    <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 font-bold">{{ $program->type->label() }}</span>
                    @if($program->type->moduleRoute())
                        <a href="{{ route($program->type->moduleRoute()) }}" class="text-emerald-700 hover:underline">فتح الوحدة ←</a>
                    @endif
                </div>

                <div class="flex items-center gap-1 flex-wrap text-[11px]">
                    <span class="font-bold text-gray-500">الدوامات:</span>
                    @if($program->studySessions->isEmpty())
                        <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">كل الدوامات</span>
                    @else
                        @foreach($program->studySessions as $session)
                            <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700">{{ $session->name }}</span>
                        @endforeach
                    @endif
                </div>

                @if($program->description)
                    <p class="text-xs text-gray-500 line-clamp-2">{{ $program->description }}</p>
                @endif

                <div class="grid grid-cols-3 gap-2 text-center">
                    <div class="bg-gray-50 rounded-xl py-2">
                        <div class="text-lg font-extrabold text-gray-800">{{ $program->periods_count }}</div>
                        <div class="text-[11px] text-gray-500">فترات</div>
                    </div>
                    <div class="bg-gray-50 rounded-xl py-2">
                        <div class="text-lg font-extrabold text-gray-800">{{ $program->attributes_count }}</div>
                        <div class="text-[11px] text-gray-500">خصائص</div>
                    </div>
                    <div class="bg-gray-50 rounded-xl py-2">
                        <div class="text-lg font-extrabold text-gray-800">{{ $program->schedules_count }}</div>
                        <div class="text-[11px] text-gray-500">حصص</div>
                    </div>
                </div>

                <div class="flex items-center justify-between border-t border-gray-100 pt-3 mt-auto">
                    <a href="{{ route('admin.schedules.index', ['program_id' => $program->id]) }}" class="text-xs text-emerald-700 hover:underline">جدول البرنامج</a>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('admin.programs.edit', $program) }}" class="text-xs text-blue-600 hover:underline">تعديل</a>
                        <form method="POST" action="{{ route('admin.programs.destroy', $program) }}" onsubmit="return confirm('حذف البرنامج «{{ $program->name }}»؟')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs text-red-600 hover:underline">حذف</button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="md:col-span-2 xl:col-span-3 bg-white rounded-2xl border border-dashed border-gray-300 p-10 text-center text-gray-400">
                لا توجد برامج بعد — ابدأ بإضافة برنامج
            </div>
        @endforelse
    </div>
</div>
@endsection
