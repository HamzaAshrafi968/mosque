@extends('layouts.app')

@section('title', 'إعدادات ساعات العمل')

@section('content')
<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-2">ساعات العمل والرواتب</h1>
    <p class="text-sm text-gray-500 mb-6">ضبط قواعد تسجيل الفترات والتوقيت المحلي المستخدم في العرض.</p>

    @include('admin.settings._tabs')

    <form method="POST" action="{{ route('admin.settings.work-hours.update') }}"
          class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-5">
        @csrf
        @method('PATCH')

        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">الحد الأقصى لساعات الفترة الواحدة</label>
            <input type="number" step="0.5" min="{{ \App\Services\WorkHoursSettingsService::MIN_MAX_SLOT_HOURS }}"
                   max="{{ \App\Services\WorkHoursSettingsService::MAX_MAX_SLOT_HOURS }}"
                   name="max_slot_hours" required value="{{ old('max_slot_hours', $maxSlotHours) }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" dir="ltr">
            <p class="text-[11px] text-gray-400 mt-1">يُرفض أي تسجيل لفترة أطول من هذا الحد (من 1 إلى 24 ساعة).</p>
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">توقيت الجامع</label>
            <select name="timezone" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" dir="ltr">
                <option value="">— الافتراضي ({{ config('app.timezone') }}) —</option>
                @foreach($timezones as $zone)
                    <option value="{{ $zone }}" @selected(old('timezone', $timezone) === $zone)>{{ $zone }}</option>
                @endforeach
            </select>
            <p class="text-[11px] text-gray-400 mt-1">أوقات العمل تُخزَّن محلية (جدارية) وتُعرض وفق هذا التوقيت.</p>
        </div>

        <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-5 py-2.5 rounded-lg">حفظ الإعدادات</button>
    </form>
</div>
@endsection
