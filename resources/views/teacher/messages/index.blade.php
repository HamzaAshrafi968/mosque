@extends('layouts.app')

@section('title', 'الرسائل')

@php
    $roleLabel = fn ($user) => match ($user?->role) {
        'admin' => 'الإدارة',
        'super_admin' => 'مدير الجوامع',
        'guardian' => 'ولي أمر',
        'student' => 'طالب',
        default => 'معلم',
    };
    $initial = fn ($name) => mb_substr(trim((string) $name), 0, 1);
@endphp

@section('content')
@php
    $authorization = app(\App\Services\AuthorizationService::class);
    $can = fn (string $permission) => $authorization->can(auth()->user(), $permission);
@endphp
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">الرسائل</h1>
    <p class="text-gray-500 text-sm mt-1">تواصل مع الإدارة والمعلمين في محادثات مرتبة</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
    {{-- ===================== قائمة المحادثات ===================== --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden lg:sticky lg:top-4">
        <div class="p-3 border-b">
            <form method="GET" action="{{ route('teacher.messages.index') }}" class="flex gap-2">
                @if($partner)
                    <input type="hidden" name="with" value="{{ $partner->id }}">
                @endif
                <input type="search" name="q" value="{{ $search }}" placeholder="ابحث عن محادثة..."
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-bold px-3 rounded-lg">بحث</button>
            </form>
        </div>

        <div class="divide-y divide-gray-100 max-h-[65vh] overflow-y-auto">
            @forelse($conversations as $conversation)
                @php
                    $other = $conversation['partner'];
                    $last = $conversation['last'];
                    $isActive = $partner && $partner->id === $other->id;
                @endphp
                <a href="{{ route('teacher.messages.index', ['with' => $other->id]) }}"
                   @class([
                       'flex items-start gap-3 p-3 transition',
                       'bg-emerald-50/70' => $isActive,
                       'hover:bg-gray-50' => ! $isActive,
                   ])>
                    <span @class([
                        'w-10 h-10 rounded-full grid place-items-center font-black shrink-0',
                        'bg-emerald-700 text-white' => $isActive,
                        'bg-gray-100 text-gray-600' => ! $isActive,
                    ])>{{ $initial($other->name) }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center justify-between gap-2">
                            <span class="font-bold text-gray-800 text-sm truncate">{{ $other->name }}</span>
                            <span class="text-[10px] text-gray-400 whitespace-nowrap">
                                {{ $last->created_at->isToday() ? $last->created_at->format('H:i') : $last->created_at->format('m/d') }}
                            </span>
                        </span>
                        <span class="flex items-center justify-between gap-2 mt-0.5">
                            <span class="text-xs text-gray-500 truncate">
                                @if($last->sender_id === auth()->id())
                                    <span class="text-gray-400">أنت:</span>
                                @endif
                                {{ $last->subject ?: $last->body }}
                            </span>
                            @if($conversation['unread'] > 0)
                                <span class="bg-emerald-600 text-white text-[10px] font-bold rounded-full min-w-5 h-5 px-1.5 grid place-items-center shrink-0">
                                    {{ $conversation['unread'] }}
                                </span>
                            @endif
                        </span>
                    </span>
                </a>
            @empty
                <p class="p-6 text-center text-gray-400 text-sm">
                    {{ $search !== '' ? 'لا توجد محادثات مطابقة للبحث' : 'لا توجد محادثات بعد' }}
                </p>
            @endforelse
        </div>
    </div>

    {{-- ===================== المحادثة ===================== --}}
    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden flex flex-col">
        @if($partner)
            <div class="p-4 border-b flex items-center gap-3">
                <span class="w-10 h-10 rounded-full bg-emerald-700 text-white grid place-items-center font-black">{{ $initial($partner->name) }}</span>
                <div>
                    <div class="font-bold text-gray-800">{{ $partner->name }}</div>
                    <div class="text-xs text-gray-400">{{ $roleLabel($partner) }}</div>
                </div>
            </div>

            <div class="p-4 space-y-1 h-[55vh] overflow-y-auto bg-gray-50/50">
                <div class="mb-2">{{ $thread->links() }}</div>

                @php $currentDate = null; @endphp
                @foreach($thread as $message)
                    @php
                        $date = $message->created_at->toDateString();
                        $isMine = $message->sender_id === auth()->id();
                    @endphp

                    @if($date !== $currentDate)
                        @php $currentDate = $date; @endphp
                        <div class="text-center my-3">
                            <span class="text-[10px] bg-gray-200 text-gray-600 rounded-full px-3 py-1">
                                {{ $message->created_at->isToday() ? 'اليوم' : ($message->created_at->isYesterday() ? 'أمس' : $message->created_at->format('Y-m-d')) }}
                            </span>
                        </div>
                    @endif

                    <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}">
                        <div @class([
                            'max-w-[85%] sm:max-w-[70%] rounded-2xl px-3.5 py-2 shadow-sm',
                            'bg-emerald-700 text-white rounded-bl-md' => $isMine,
                            'bg-white border border-gray-200 text-gray-800 rounded-br-md' => ! $isMine,
                        ])>
                            @if($message->subject)
                                <div @class([
                                    'text-xs font-bold mb-1',
                                    'text-emerald-100' => $isMine,
                                    'text-emerald-700' => ! $isMine,
                                ])>{{ $message->subject }}</div>
                            @endif
                            <div class="text-sm whitespace-pre-wrap break-words">{{ $message->body }}</div>
                            <div @class([
                                'text-[10px] mt-1 text-left',
                                'text-emerald-200/80' => $isMine,
                                'text-gray-400' => ! $isMine,
                            ])>{{ $message->created_at->format('H:i') }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="p-4 border-t">
                @if ($can('messages.create'))
                    <form method="POST" action="{{ route('teacher.messages.store') }}" class="space-y-2">
                        @csrf
                        <input type="hidden" name="recipient_id" value="{{ $partner->id }}">
                        <input type="text" name="subject" value="{{ old('subject') }}" placeholder="الموضوع (اختياري)"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <div class="flex items-end gap-2">
                            <textarea name="body" rows="2" required placeholder="اكتب رسالتك..."
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">{{ old('body') }}</textarea>
                            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-5 py-2.5 rounded-lg whitespace-nowrap">إرسال</button>
                        </div>
                    </form>
                @else
                    <p class="text-xs text-gray-400">لا تملك صلاحية إرسال الرسائل.</p>
                @endif
            </div>
        @else
            <div class="p-6 border-b bg-gray-50/60">
                <h2 class="font-bold text-gray-800">رسالة جديدة</h2>
                <p class="text-xs text-gray-500 mt-0.5">اختر المستلم واكتب رسالتك لبدء محادثة جديدة</p>
            </div>
            <div class="p-4">
                @if ($can('messages.create'))
                <form method="POST" action="{{ route('teacher.messages.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">المستلم</label>
                        <select name="recipient_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                            <option value="">اختر المستلم</option>
                            @foreach($recipients as $recipient)
                                <option value="{{ $recipient->id }}" @selected(old('recipient_id') == $recipient->id)>{{ $recipient->name }} ({{ $roleLabel($recipient) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الموضوع</label>
                        <input type="text" name="subject" value="{{ old('subject') }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نص الرسالة</label>
                        <textarea name="body" rows="4" required
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">{{ old('body') }}</textarea>
                    </div>
                    <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">إرسال</button>
                </form>
                @else
                    <p class="text-xs text-gray-400">لا تملك صلاحية إرسال الرسائل.</p>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection
