@extends('layouts.app')

@section('title', $meeting->title)

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('admin.faith-meetings.index') }}" class="text-sm text-emerald-700 hover:text-emerald-800">← اللقاءات الإيمانية</a>
            <h2 class="text-2xl font-extrabold text-gray-800 mt-1">{{ $meeting->title }}</h2>
        </div>
        <div class="flex gap-2 items-center">
            <span @class([
                'px-3 py-1.5 rounded-full text-xs font-bold',
                'bg-sky-100 text-sky-800' => $meeting->status->value === 'scheduled',
                'bg-green-100 text-green-800' => $meeting->status->value === 'completed',
                'bg-red-100 text-red-800' => $meeting->status->value === 'cancelled',
            ])>{{ $meeting->status->label() }}</span>
            <a href="{{ route('admin.faith-meetings.edit', $meeting) }}" class="bg-white border border-gray-300 text-gray-700 text-sm font-bold px-4 py-2 rounded-lg">تعديل</a>
            <form method="POST" action="{{ route('admin.faith-meetings.destroy', $meeting) }}" onsubmit="return confirm('سيُحذف اللقاء وسجله بالكامل. متأكد؟')">
                @csrf
                @method('DELETE')
                <button class="bg-white border border-red-200 text-red-600 text-sm font-bold px-4 py-2 rounded-lg">حذف</button>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div><span class="block text-xs text-gray-400 font-bold">التاريخ</span><span class="font-bold text-gray-800">{{ $meeting->date->format('Y-m-d') }}</span></div>
            <div><span class="block text-xs text-gray-400 font-bold">الوقت</span><span class="font-bold text-gray-800">{{ $meeting->start_time ? \Carbon\Carbon::parse($meeting->start_time)->format('H:i') : '—' }} {{ $meeting->end_time ? 'إلى '.\Carbon\Carbon::parse($meeting->end_time)->format('H:i') : '' }}</span></div>
            <div><span class="block text-xs text-gray-400 font-bold">المشرف</span><span class="font-bold text-gray-800">{{ $meeting->supervisor?->name ?? '—' }}</span></div>
            <div><span class="block text-xs text-gray-400 font-bold">المعلم</span><span class="font-bold text-gray-800">{{ $meeting->teacher?->name ?? '—' }}</span></div>
            <div><span class="block text-xs text-gray-400 font-bold">المكان</span><span class="font-bold text-gray-800">{{ $meeting->location ?? '—' }}</span></div>
            <div class="col-span-3"><span class="block text-xs text-gray-400 font-bold">الوصف</span><span class="text-gray-700">{{ $meeting->description ?? '—' }}</span></div>
        </div>
        @if($meeting->status->value !== 'completed')
            <form method="POST" action="{{ route('admin.faith-meetings.status', $meeting) }}" class="flex gap-2 mt-4 pt-4 border-t border-gray-100">
                @csrf
                <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}" @selected($meeting->status === $status)>{{ $status->label() }}</option>
                    @endforeach
                </select>
                <button class="bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold px-4 py-2 rounded-lg">تحديث الحالة</button>
            </form>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b bg-gray-50 flex justify-between items-center">
                <span class="font-bold text-gray-800">👥 الطلاب المحددون والحضور</span>
                <span class="text-xs text-gray-400">المحددون فقط يُرفقون باللقاء</span>
            </div>
            <form method="POST" action="{{ route('admin.faith-meetings.attendance', $meeting) }}" class="p-4 space-y-3">
                @csrf
                @forelse($meeting->studentAttendances as $attendance)
                    <div class="flex flex-wrap items-center gap-3 border border-gray-100 rounded-xl p-3">
                        <div class="flex-1 min-w-40">
                            <span class="font-bold text-sm text-gray-800">{{ $attendance->student->name }}</span>
                            <span class="text-xs text-gray-400 mr-2">{{ $attendance->student->classroom?->name }}</span>
                        </div>
                        <select name="statuses[{{ $attendance->student_id }}]" class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                            <option value="">— الحالة —</option>
                            @foreach($attendanceStatuses as $status)
                                <option value="{{ $status->value }}" @selected($attendance->attendance_status?->value === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="notes[{{ $attendance->student_id }}]" value="{{ $attendance->note }}" placeholder="ملاحظة عن الطالب" class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm w-40">
                    </div>
                @empty
                    <div class="text-center text-gray-400 py-6 text-sm">لم يُحدد طلاب لهذا اللقاء — أضفهم من «تعديل»</div>
                @endforelse
                @if($meeting->studentAttendances->isNotEmpty())
                    <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-5 py-2 rounded-lg">حفظ الحضور</button>
                @endif
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b bg-gray-50 font-bold text-gray-800">📝 إضافة ملاحظة / اقتراح / إجراء</div>
            <form method="POST" action="{{ route('admin.faith-meetings.notes.store', $meeting) }}" class="p-4 space-y-3">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">النوع</label>
                        <select name="note_type" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            @foreach($noteTypes as $noteType)
                                <option value="{{ $noteType->value }}" @selected($noteType->value === 'note')>{{ $noteType->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">الطالب (اختياري)</label>
                        <select name="student_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="">— عام —</option>
                            @foreach($students as $student)
                                <option value="{{ $student->id }}">{{ $student->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">المحتوى</label>
                    <textarea name="content" rows="2" required class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="نص الملاحظة/الاقتراح/الإجراء..."></textarea>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">موكل إلى (لإجراء)</label>
                        <select name="assigned_to" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="">— بدون —</option>
                            @foreach($staffUsers as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">تاريخ الاستحقاق</label>
                        <input type="date" name="due_date" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-5 py-2 rounded-lg">إضافة</button>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b bg-gray-50 font-bold text-gray-800">🗒️ سجل الملاحظات والإجراءات ({{ $meeting->notes->count() }})</div>
        <div class="divide-y divide-gray-50">
            @forelse($meeting->notes as $note)
                <div class="px-5 py-3 flex flex-wrap items-start gap-3">
                    <span @class([
                        'px-2 py-0.5 rounded-full text-[11px] font-bold shrink-0',
                        'bg-gray-100 text-gray-600' => $note->note_type->value === 'note',
                        'bg-sky-100 text-sky-800' => $note->note_type->value === 'suggestion',
                        'bg-amber-100 text-amber-800' => $note->note_type->value === 'action_item',
                    ])>{{ $note->note_type->label() }}</span>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm text-gray-800">{{ $note->content }}</div>
                        <div class="text-[11px] text-gray-400 mt-1">
                            {{ $note->student?->name ? 'الطالب: '.$note->student->name.' · ' : '' }}
                            {{ $note->createdBy?->name ?? '—' }} · {{ $note->created_at->format('Y-m-d H:i') }}
                            @if($note->assignedTo) · موكل إلى: {{ $note->assignedTo->name }} @endif
                            @if($note->due_date) · يستحق: {{ $note->due_date->format('Y-m-d') }} @endif
                            @if($note->status)
                                · الحالة: <span class="font-bold {{ $note->status->value === 'completed' ? 'text-green-600' : 'text-amber-600' }}">{{ $note->status->label() }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="flex gap-2 items-center">
                        @if($note->status?->value === 'pending')
                            <form method="POST" action="{{ route('admin.faith-meetings.notes.complete', $note) }}">
                                @csrf
                                <button class="text-xs text-green-700 hover:underline font-bold">إنجاز ✔️</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.faith-meetings.notes.destroy', $note) }}" onsubmit="return confirm('حذف الملاحظة؟')">
                            @csrf
                            @method('DELETE')
                            <button class="text-xs text-red-500 hover:underline">حذف</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-gray-400 text-sm">لا توجد ملاحظات بعد — أضف أول ملاحظة من الأعلى</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
