@props([
    'student',
    'statuses',
    'action',
    'canEdit' => true,
])

@php
    $current = $student->memorization_status;
@endphp

<div data-memorization-editor class="inline-block text-right align-middle">
    @if($canEdit)
        <button type="button" data-memorization-toggle
                class="text-xs font-bold text-blue-600 hover:underline select-none">
            تعديل
        </button>
    @endif

    <div data-memorization-panel
         class="hidden mt-2 w-[22rem] max-w-[85vw] bg-white border border-gray-200 rounded-2xl shadow-xl p-4 text-right">
        <form method="POST" action="{{ $action }}" class="space-y-3">
            @csrf
            @method('PATCH')

            <div class="flex items-center justify-between gap-2">
                <span class="text-sm font-black text-gray-800">حالة الحفظ — {{ $student->name }}</span>
                <button type="button" data-memorization-close
                        class="text-gray-400 hover:text-gray-600 text-lg leading-none"
                        aria-label="إغلاق">&times;</button>
            </div>

            <div class="space-y-2">
                @foreach($statuses as $status)
                    <label class="block cursor-pointer">
                        <input type="radio" name="memorization_status" value="{{ $status->value }}"
                               @checked($current === $status) class="peer sr-only">
                        <span @class([
                            'flex items-start gap-2.5 rounded-xl border border-gray-200 p-2.5 transition',
                            'hover:border-gray-300 peer-checked:ring-2 peer-checked:ring-current peer-checked:border-current',
                            $status->textClass(),
                        ])>
                            <span class="w-3.5 h-3.5 mt-0.5 rounded-full bg-current shrink-0"></span>
                            <span class="min-w-0">
                                <span class="block text-sm font-bold">{{ $status->label() }}</span>
                                <span class="block text-[11px] text-gray-500">{{ $status->description() }}</span>
                            </span>
                        </span>
                    </label>
                @endforeach

                <label class="block cursor-pointer">
                    <input type="radio" name="memorization_status" value=""
                           @checked($current === null) class="peer sr-only">
                    <span class="flex items-center gap-2.5 rounded-xl border border-gray-200 p-2.5 transition text-gray-500 hover:border-gray-300 peer-checked:ring-2 peer-checked:ring-current peer-checked:border-current">
                        <span class="w-3.5 h-3.5 rounded-full border-2 border-current shrink-0"></span>
                        <span class="text-sm font-bold">غير محدد</span>
                    </span>
                </label>
            </div>

            <textarea name="memorization_notes" rows="2" maxlength="2000" placeholder="ملاحظات الحفظ (اختياري)"
                      class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">{{ $student->memorization_notes }}</textarea>

            @if($student->memorization_updated_at)
                <div class="text-[11px] text-gray-400">
                    آخر تحديث:
                    {{ $student->memorizationUpdatedBy?->name ?? 'غير معروف' }}
                    — {{ $student->memorization_updated_at->format('Y-m-d H:i') }}
                </div>
            @endif

            <button type="submit" class="w-full bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-xl">
                حفظ حالة الحفظ
            </button>
        </form>
    </div>
</div>
