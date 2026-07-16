@extends('layouts.app')

@section('title', 'الرسائل والإشعارات')

@section('content')
<div class="bg-white rounded-xl shadow overflow-hidden p-6 mb-6">
    <h2 class="text-lg font-bold text-gray-800 mb-4">إرسال رسالة</h2>
    <form method="POST" action="{{ route('teacher.messages.store') }}" class="space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">المستلم</label>
            <select name="recipient_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                <option value="">اختر المستلم</option>
                @foreach($recipients as $recipient)
                    <option value="{{ $recipient->id }}" @selected(old('recipient_id') == $recipient->id)>{{ $recipient->name }} ({{ $recipient->role === 'admin' ? 'الإدارة' : 'معلم' }})</option>
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
            <textarea name="body" rows="3" required
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">{{ old('body') }}</textarea>
        </div>

        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">إرسال</button>
    </form>
</div>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <h2 class="text-lg font-bold text-gray-800 p-4 border-b">الرسائل</h2>
    @forelse($messages as $message)
        <div class="p-4 border-b border-gray-100">
            <div class="flex justify-between items-start mb-2">
                <div class="text-sm {{ $message->sender_id === auth()->id() ? 'text-blue-700' : 'text-emerald-700' }}">
                    @if($message->sender_id === auth()->id())
                        صادرة إلى: {{ $message->recipient?->name }}
                    @else
                        واردة من: {{ $message->sender?->name }}
                    @endif
                </div>
                <div class="text-sm text-gray-500">{{ $message->created_at->format('Y-m-d H:i') }}</div>
            </div>
            @if($message->subject)
                <div class="font-bold text-gray-800 mb-1">{{ $message->subject }}</div>
            @endif
            <div class="text-gray-600 text-sm whitespace-pre-wrap">{{ $message->body }}</div>
        </div>
    @empty
        <p class="text-gray-500 p-6 text-center">لا توجد رسائل</p>
    @endforelse
    <div class="p-4">
        {{ $messages->links() }}
    </div>
</div>
@endsection
