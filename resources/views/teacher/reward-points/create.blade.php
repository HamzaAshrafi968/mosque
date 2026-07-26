@extends('layouts.app')

@section('title', 'إضافة نقاط مكافآت')

@section('content')
<div class="max-w-2xl mx-auto space-y-6 animate-fade-in-up">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 bg-gradient-to-r from-amber-600 to-orange-600 text-white font-bold text-lg flex items-center gap-2">
            <span>🏆</span> إضافة نقاط مكافآت
        </div>

        <form method="POST" action="{{ route('teacher.reward-points.store') }}" class="p-6 space-y-5">
            @csrf

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">👨‍🎓 الطالب</label>
                <select name="student_id" required class="w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition bg-white">
                    <option value="">اختر الطالب...</option>
                    @foreach($students as $student)
                        <option value="{{ $student->id }}" @selected($studentId == $student->id)>
                            {{ $student->name }}
                        </option>
                    @endforeach
                </select>
                @error('student_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">📋 النوع</label>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="type" value="earned" checked class="w-4 h-4 text-amber-500 focus:ring-amber-500">
                        <span class="text-emerald-700 font-medium">✅ ربح</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="type" value="deducted" class="w-4 h-4 text-amber-500 focus:ring-amber-500">
                        <span class="text-red-700 font-medium">❌ خصم</span>
                    </label>
                </div>
                @error('type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">🏆 عدد النقاط</label>
                <input type="number" name="points" min="1" max="100" value="{{ old('points', 1) }}" required
                    class="w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition"
                    placeholder="أدخل عدد النقاط">
                @error('points') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">📝 السبب</label>
                <input type="text" name="reason" value="{{ old('reason') }}" maxlength="255"
                    class="w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition"
                    placeholder="مثال: تسميع ممتاز - حفظ جيد - سلوك مميز">
                @error('reason') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">📒 ملاحظات</label>
                <textarea name="notes" rows="3"
                    class="w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition"
                    placeholder="ملاحظات إضافية...">{{ old('notes') }}</textarea>
                @error('notes') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('teacher.reward-points.index') }}" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition font-medium text-sm">
                    🔙 رجوع
                </a>
                <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-amber-500 to-orange-500 text-white rounded-xl hover:from-amber-600 hover:to-orange-600 transition font-bold text-sm shadow-lg shadow-amber-500/20">
                    💾 حفظ النقاط
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
