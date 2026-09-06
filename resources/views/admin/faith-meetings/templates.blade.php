@extends('layouts.app')

@section('title', 'قوالب اللقاءات')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <a href="{{ route('admin.faith-meetings.index') }}" class="text-sm text-emerald-700 hover:text-emerald-800">← اللقاءات الإيمانية</a>
    <h2 class="text-2xl font-extrabold text-gray-800 mt-1">قوالب اللقاءات المتكررة</h2>
    <p class="text-sm text-gray-500">أنشئ قالباً مرة ثم استخدمه لإنشاء لقاء جديد ببيانات معبأة مسبقاً.</p>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b bg-gray-50 font-bold text-gray-800">+ قالب جديد</div>
        <form method="POST" action="{{ route('admin.faith-meetings.templates.store') }}" class="p-5 grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
            @csrf
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">العنوان <span class="text-red-500">*</span></label>
                <input type="text" name="title" required placeholder="مثال: اللقاء الأسبوعي للتزكية" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">المكان</label>
                <input type="text" name="location" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-5 py-2 rounded-lg">إنشاء القالب</button>
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-600 mb-1">الوصف</label>
                <input type="text" name="description" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">ملاحظات افتراضية</label>
                <input type="text" name="notes" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
        </form>
    </div>

    <div class="space-y-3">
        @forelse($templates as $template)
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex-1 min-w-52">
                        <div class="font-bold text-gray-800">
                            {{ $template->title }}
                            <span @class([
                                'text-[10px] px-2 py-0.5 rounded-full mr-2 font-bold',
                                'bg-green-100 text-green-800' => $template->is_active,
                                'bg-gray-100 text-gray-500' => !$template->is_active,
                            ])>{{ $template->is_active ? 'مفعل' : 'معطل' }}</span>
                        </div>
                        <div class="text-xs text-gray-400 mt-1">{{ $template->description ?? '—' }} @if($template->location) · مكان: {{ $template->location }} @endif</div>
                    </div>
                    <a href="{{ route('admin.faith-meetings.create', ['template' => $template->id]) }}" class="text-xs bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-3 py-2 rounded-lg">إنشاء لقاء منه</a>
                </div>
                <div class="mt-3 pt-3 border-t border-gray-100 grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                    <form method="POST" action="{{ route('admin.faith-meetings.templates.update', $template) }}" class="contents">
                        @csrf
                        @method('PATCH')
                        <div class="md:col-span-3 grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold text-gray-500 mb-1">العنوان</label>
                                <input type="text" name="title" value="{{ $template->title }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-gray-500 mb-1">الوصف</label>
                                <input type="text" name="description" value="{{ $template->description }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-gray-500 mb-1">المكان</label>
                                <input type="text" name="location" value="{{ $template->location }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            </div>
                        </div>
                        <div class="flex items-center gap-3 pb-1">
                            <label class="inline-flex items-center gap-1 text-xs text-gray-600">
                                <input type="checkbox" name="is_active" value="1" @checked($template->is_active) class="accent-emerald-600"> مفعل
                            </label>
                            <button class="bg-gray-800 hover:bg-gray-900 text-white text-xs font-bold px-3 py-2 rounded-lg">حفظ</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('admin.faith-meetings.templates.destroy', $template) }}" onsubmit="return confirm('حذف القالب؟')">
                        @csrf
                        @method('DELETE')
                        <button class="text-xs text-red-500 hover:underline">حذف</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 text-center text-gray-400 text-sm">
                لا توجد قوالب بعد — مثال: «اللقاء الأسبوعي للتزكية» أو «لقاء التحفيظ الشهري»
            </div>
        @endforelse
    </div>
</div>
@endsection
