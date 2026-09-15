@props([
    'student',
    'memorizedJuz' => [],
    'storeRoute',
    'destroyRoute',
])

<div class="bg-white rounded-2xl border border-gray-200 p-4 space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="font-bold text-gray-800">الأجزاء المحفوظة للطالب</h3>
        <span class="text-xs text-gray-400">خمسات الجزء لا تُفتح إلا بعد تسجيل حفظه</span>
    </div>

    <div class="flex flex-wrap gap-2">
        @forelse($memorizedJuz as $juz)
            <span class="inline-flex items-center gap-2 rounded-full bg-emerald-100 text-emerald-800 text-xs font-bold px-3 py-1">
                الجزء {{ $juz }}
                <form method="POST" action="{{ $destroyRoute }}" onsubmit="return confirm('إلغاء تسجيل حفظ الجزء {{ $juz }}؟')">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="student_id" value="{{ $student->id }}">
                    <input type="hidden" name="juz" value="{{ $juz }}">
                    <button class="text-red-700 hover:text-red-900" title="إلغاء التسجيل">✕</button>
                </form>
            </span>
        @empty
            <span class="text-sm text-gray-400">لا توجد أجزاء مسجّلة بعد — سجّل حفظ الجزء أولاً لتُفتح خمساته.</span>
        @endforelse
    </div>

    <form method="POST" action="{{ $storeRoute }}" class="flex flex-wrap items-center gap-2">
        @csrf
        <input type="hidden" name="student_id" value="{{ $student->id }}">
        <select name="juz" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            @for($juz = 1; $juz <= 30; $juz++)
                <option value="{{ $juz }}" @disabled(in_array($juz, $memorizedJuz))>الجزء {{ $juz }}</option>
            @endfor
        </select>
        <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">+ تسجيل حفظ جزء</button>
    </form>
</div>
