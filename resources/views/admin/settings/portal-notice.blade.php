@extends('layouts.app')

@section('title', 'إعلان بوابة أولياء الأمور')

@section('content')
@php
    $authorization = app(\App\Services\AuthorizationService::class);
    $can = fn (string $permission) => $authorization->can(auth()->user(), $permission);
@endphp
<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-2">إعلان بوابة أولياء الأمور</h1>
    <p class="text-sm text-gray-500 mb-6">نص ثابت يظهر في أعلى الصفحة الرئيسية لبوابة ولي الأمر. اتركه فارغاً لإخفاء الإعلان.</p>

    @include('admin.settings._tabs')

    <form method="POST" action="{{ route('admin.settings.portal-notice.update') }}" class="bg-white rounded-2xl shadow p-6 space-y-6">
        @csrf
        @method('PATCH')

        <div>
            <label for="notice" class="block text-sm font-bold text-gray-700 mb-2">نص الإعلان</label>
            <textarea name="notice" id="notice" rows="5" maxlength="{{ \App\Services\PortalNoticeSettingsService::MAX_LENGTH }}"
                class="w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500"
                placeholder="مثال: يبدأ التسجيل للعام الدراسي الجديد يوم الأحد القادم، يرجى مراجعة الإدارة.">{{ old('notice', $notice) }}</textarea>
            @error('notice')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
            <p class="text-xs text-gray-400 mt-2 leading-relaxed">
                يظهر النص كما هو (مع فواصل الأسطر) داخل بطاقة «إعلان هام» أعلى الصفحة الرئيسية لبوابة ولي الأمر.
                تركه فارغاً = لا يظهر أي إعلان.
            </p>
        </div>

        <div class="rounded-xl bg-gray-50 border border-gray-200 p-4 text-xs text-gray-500 leading-relaxed">
            الإعلان ثابت لكل جامع: يكتبه مدير الجامع من هنا فقط، ولا يستطيع أولياء الأمور إخفاؤه.
            أما الإعلانات العادية (التي تصل كإشعارات) فتبقى كما هي في قسم الإعلانات.
        </div>

        @if ($can('portal_notice.update'))
            <div class="flex justify-end">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-5 py-2.5 rounded-lg">حفظ الإعلان</button>
            </div>
        @else
            <p class="text-xs text-gray-400">لا تملك صلاحية تعديل إعلان بوابة أولياء الأمور.</p>
        @endif
    </form>
</div>
@endsection
