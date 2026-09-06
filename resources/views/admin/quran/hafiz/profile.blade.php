@extends('layouts.app')

@section('title', 'ملف الحافظ - '.$student->name)

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <a href="{{ route('admin.quran.hafiz.index') }}" class="text-sm text-emerald-700 hover:text-emerald-800">← ملفات الحفاظ</a>

    <div class="bg-gradient-to-l from-amber-600 to-orange-500 text-white rounded-2xl p-6 shadow-lg">
        <div class="flex flex-wrap items-center gap-6">
            <div class="text-4xl">📿</div>
            <div class="flex-1">
                <div class="text-xl font-extrabold">{{ $student->name }}</div>
                <div class="text-sm text-amber-100 mt-1">
                    الصف: {{ $student->classroom?->name ?? '—' }} ·
                    تاريخ الإتمام: {{ $completion?->completed_at?->format('Y-m-d') ?? '—' }} ·
                    أُكد بواسطة: {{ $completion?->confirmedBy?->name ?? '—' }}
                </div>
            </div>
            <a href="{{ route('admin.quran.journey', $student) }}" class="bg-white/20 hover:bg-white/30 text-white text-sm font-bold px-4 py-2 rounded-xl">الرحلة القرآنية</a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.quran.hafiz.profile.update', $student) }}" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-5">
        @csrf
        @method('PATCH')
        <h3 class="font-extrabold text-gray-800">بيانات الإجازة والقراءات</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <label class="inline-flex items-center gap-2 text-sm font-bold text-gray-700">
                <input type="checkbox" name="mujaz" value="1" @checked(old('mujaz', $profile->mujaz)) class="accent-emerald-600 w-5 h-5">
                مجاز (حصل على الإجازة)
            </label>
            <label class="inline-flex items-center gap-2 text-sm font-bold text-gray-700">
                <input type="checkbox" name="ijazah_jazariyyah" value="1" @checked(old('ijazah_jazariyyah', $profile->ijazah_jazariyyah)) class="accent-emerald-600 w-5 h-5">
                الإجازة الجزرية
            </label>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">المجيز (من منحه الإجازة)</label>
                <input type="text" name="mujiz" value="{{ old('mujiz', $profile->mujiz) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">الرواية</label>
                <input type="text" name="riwayah" value="{{ old('riwayah', $profile->riwayah) }}" placeholder="مثال: حفص عن عاصم" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">الشهادة العلمية</label>
                <textarea name="scientific_certificate" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2">{{ old('scientific_certificate', $profile->scientific_certificate) }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">الدورات الشرعية</label>
                <textarea name="sharia_courses" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2">{{ old('sharia_courses', $profile->sharia_courses) }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">الدورات التدريبية</label>
                <textarea name="training_courses" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2">{{ old('training_courses', $profile->training_courses) }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">الدراسة الأكاديمية الشرعية</label>
                <textarea name="sharia_academic_study" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2">{{ old('sharia_academic_study', $profile->sharia_academic_study) }}</textarea>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-gray-700 mb-1">ملاحظات</label>
                <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2">{{ old('notes', $profile->notes) }}</textarea>
            </div>
        </div>

        @if($customFields->isNotEmpty())
            <div class="pt-4 border-t border-gray-100">
                <h3 class="font-extrabold text-gray-800 mb-4">حقول إضافية مخصصة</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-custom-field-inputs :fields="$customFields" :values="$customValues" />
                </div>
                <p class="text-xs text-gray-400 mt-3">تُدار هذه الحقول من صفحة «الحقول المخصصة → الحفاظ» وتظهر هنا تلقائياً.</p>
            </div>
        @endif

        <div class="flex items-center justify-between pt-2">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-8 py-2.5 rounded-xl">حفظ الملف</button>
        </div>
    </form>
</div>
@endsection
