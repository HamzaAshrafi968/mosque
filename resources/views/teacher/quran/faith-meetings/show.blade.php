@extends('layouts.app')

@section('title', $meeting->title)

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('teacher.quran.faith-meetings.index') }}" class="text-sm text-emerald-700 hover:text-emerald-800">← اللقاءات الإيمانية</a>
            <h2 class="text-2xl font-extrabold text-gray-800 mt-1">{{ $meeting->title }}</h2>
        </div>
        <div class="flex items-center gap-2">
            <span @class([
                'px-3 py-1.5 rounded-full text-xs font-bold',
                'bg-sky-100 text-sky-800' => $meeting->status->value === 'scheduled',
                'bg-green-100 text-green-800' => $meeting->status->value === 'completed',
                'bg-red-100 text-red-800' => $meeting->status->value === 'cancelled',
            ])>{{ $meeting->status->label() }}</span>
            @if($meeting->status->value === 'scheduled')
                <form method="POST" action="{{ route('teacher.quran.faith-meetings.complete', $meeting) }}">
                    @csrf
                    <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">إنهاء اللقاء</button>
                </form>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
        <div><span class="block text-xs text-gray-400 font-bold">التاريخ</span><span class="font-bold text-gray-800">{{ $meeting->date->format('Y-m-d') }}</span></div>
        <div><span class="block text-xs text-gray-400 font-bold">الوقت</span><span class="font-bold text-gray-800">{{ $meeting->start_time ? \Carbon\Carbon::parse($meeting->start_time)->format('H:i') : '—' }}</span></div>
        <div><span class="block text-xs text-gray-400 font-bold">المكان</span><span class="font-bold text-gray-800">{{ $meeting->location ?? '—' }}</span></div>
        <div><span class="block text-xs text-gray-400 font-bold">المشرف</span><span class="font-bold text-gray-800">{{ $meeting->supervisor?->name ?? '—' }}</span></div>
        <div class="col-span-4"><span class="block text-xs text-gray-400 font-bold">الوصف</span><span class="text-gray-700">{{ $meeting->description ?? '—' }}</span></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b bg-gray-50 font-bold text-gray-800">👥 حضور الطلاب</div>
            <form method="POST" action="{{ route('teacher.quran.faith-meetings.attendance', $meeting) }}" class="p-4 space-y-3">
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
                        <input type="text" name="notes[{{ $attendance->student_id }}]" value="{{ $attendance->note }}" placeholder="ملاحظة" class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm w-36">
                    </div>
                @empty
                    <div class="text-center text-gray-400 py-6 text-sm">لا يوجد طلاب محددون لهذا اللقاء</div>
                @endforelse
                @if($meeting->studentAttendances->isNotEmpty())
                    <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-5 py-2 rounded-lg">حفظ الحضور</button>
                @endif
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b bg-gray-50 font-bold text-gray-800">📝 ملاحظات / اقتراحات / إجراءات</div>
            <div class="p-4 space-y-4 max-h-[28rem] overflow-y-auto">
                <form method="POST" action="{{ route('teacher.quran.faith-meetings.notes.store', $meeting) }}" class="space-y-3 border border-gray-100 rounded-xl p-3">
                    @csrf
                    <div class="grid grid-cols-2 gap-3">
                        <select name="note_type" required class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            @foreach($noteTypes as $noteType)
                                <option value="{{ $noteType->value }}" @selected($noteType->value === 'note')>{{ $noteType->label() }}</option>
                            @endforeach
                        </select>
                        <input type="date" name="due_date" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <textarea name="content" rows="2" required placeholder="نص الملاحظة/الاقتراح/الإجراء..." class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
                    <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-4 py-2 rounded-lg">إضافة</button>
                </form>
                @forelse($meeting->notes as $note)
                    <div class="border border-gray-100 rounded-xl p-3">
                        <span @class([
                            'px-2 py-0.5 rounded-full text-[11px] font-bold',
                            'bg-gray-100 text-gray-600' => $note->note_type->value === 'note',
                            'bg-sky-100 text-sky-800' => $note->note_type->value === 'suggestion',
                            'bg-amber-100 text-amber-800' => $note->note_type->value === 'action_item',
                        ])>{{ $note->note_type->label() }}</span>
                        <p class="text-sm text-gray-800 mt-2">{{ $note->content }}</p>
                        <div class="text-[11px] text-gray-400 mt-1">
                            {{ $note->student?->name ? 'الطالب: '.$note->student->name.' · ' : '' }}
                            {{ $note->createdBy?->name ?? '—' }} · {{ $note->created_at->format('Y-m-d H:i') }}
                            @if($note->due_date) · يستحق: {{ $note->due_date->format('Y-m-d') }} @endif
                        </div>
                        @if($note->status?->value === 'pending')
                            <form method="POST" action="{{ route('teacher.quran.faith-meetings.notes.complete', $note) }}" class="mt-2">
                                @csrf
                                <button class="text-xs text-green-700 hover:underline font-bold">إنجاز ✔️</button>
                            </form>
                        @elseif($note->status)
                            <span class="text-[11px] font-bold {{ $note->status->value === 'completed' ? 'text-green-600' : 'text-amber-600' }}">الحالة: {{ $note->status->label() }}</span>
                        @endif
                    </div>
                @empty
                    <div class="text-center text-gray-400 py-6 text-sm">لا توجد ملاحظات بعد</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
