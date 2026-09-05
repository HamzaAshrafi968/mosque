@extends('layouts.app')

@section('title', 'الإشعارات')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <h1 class="text-2xl font-bold text-gray-800">الإشعارات</h1>
    <form method="POST" action="{{ route('notifications.read-all') }}">
        @csrf
        <button type="submit" class="bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-bold px-4 py-2 rounded-lg">
            تحديد الكل كمقروء
        </button>
    </form>
</div>

@forelse($notifications as $notification)
    <div class="bg-white rounded-2xl shadow p-5 mb-3 {{ $notification->read_at ? 'opacity-75' : 'border-r-4 border-emerald-500' }}">
        <div class="flex items-start justify-between gap-3">
            <div class="flex-1">
                <div class="flex items-center gap-2">
                    @unless($notification->read_at)
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></span>
                    @endunless
                    <h3 class="font-bold text-gray-800">{{ $notification->data['title'] ?? 'إشعار' }}</h3>
                </div>
                <p class="text-sm text-gray-600 mt-1 whitespace-pre-wrap">{{ $notification->data['body'] ?? '' }}</p>
                <div class="text-xs text-gray-400 mt-2">{{ $notification->created_at->format('Y-m-d H:i') }}</div>
            </div>
            @unless($notification->read_at)
                <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                    @csrf
                    <button type="submit" class="text-xs text-emerald-700 hover:underline">مقروء</button>
                </form>
            @endunless
        </div>
    </div>
@empty
    <div class="bg-white rounded-2xl shadow p-10 text-center text-gray-400">لا توجد إشعارات</div>
@endforelse

<div class="mt-4">{{ $notifications->links() }}</div>
@endsection
