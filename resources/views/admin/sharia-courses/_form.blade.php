@php
    $course = $course ?? null;
    $selectedSupervisors = old('supervisor_ids', $course ? $course->supervisors->pluck('id')->all() : []);
@endphp

<form method="POST" action="{{ $course ? route('admin.sharia-courses.update', $course) : route('admin.sharia-courses.store') }}" class="space-y-4">
    @csrf
    @if($course)
        @method('PATCH')
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <label class="block text-sm font-bold text-gray-700 mb-1">اسم الدورة <span class="text-red-500">*</span></label>
            <input type="text" name="name" required maxlength="255" value="{{ old('name', $course?->name) }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-bold text-gray-700 mb-1">المشرفون <span class="text-xs text-gray-400 font-normal">— يمكن اختيار أكثر من مشرف</span></label>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2 max-h-56 overflow-y-auto border border-gray-200 rounded-xl p-3">
                @forelse($teachers as $teacher)
                    <label class="flex items-center gap-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg px-2 py-1.5">
                        <input type="checkbox" name="supervisor_ids[]" value="{{ $teacher->id }}" @checked(in_array($teacher->id, $selectedSupervisors, true)) class="accent-emerald-600">
                        <span class="font-bold">{{ $teacher->name }}</span>
                    </label>
                @empty
                    <span class="text-sm text-gray-400">لا يوجد معلمون نشطون</span>
                @endforelse
            </div>
            <input type="hidden" name="supervisor_ids[]" value="">
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">الحالة <span class="text-red-500">*</span></label>
            <select name="status" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                @foreach($statuses as $status)
                    <option value="{{ $status->value }}" @selected(old('status', $course?->status?->value ?? 'active') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">المكان</label>
            <input type="text" name="location" maxlength="255" value="{{ old('location', $course?->location) }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">تاريخ البداية</label>
                <input type="date" name="start_date" value="{{ old('start_date', $course?->start_date?->format('Y-m-d')) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">تاريخ النهاية</label>
                <input type="date" name="end_date" value="{{ old('end_date', $course?->end_date?->format('Y-m-d')) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-bold text-gray-700 mb-1">الوصف</label>
            <textarea name="description" rows="3" maxlength="5000" class="w-full border border-gray-300 rounded-lg px-3 py-2">{{ old('description', $course?->description) }}</textarea>
        </div>
    </div>

    <div class="flex items-center justify-between pt-2">
        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-8 py-2.5 rounded-xl">
            {{ $course ? 'حفظ التعديلات' : 'إنشاء الدورة' }}
        </button>
        <a href="{{ $course ? route('admin.sharia-courses.show', $course) : route('admin.sharia-courses.index') }}" class="text-gray-500 text-sm hover:underline">إلغاء</a>
    </div>
</form>
