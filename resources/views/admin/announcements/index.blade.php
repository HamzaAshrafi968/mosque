@extends('layouts.app')

@section('title', 'الإعلانات')

@section('content')
<div class="bg-white rounded-xl shadow overflow-hidden p-4 mb-6">
    <form method="POST" action="{{ route('admin.announcements.store') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">العنوان <span class="text-red-500">*</span></label>
            <input type="text" name="title" value="{{ old('title') }}" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">المحتوى <span class="text-red-500">*</span></label>
            <textarea name="body" rows="3" required
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">{{ old('body') }}</textarea>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الجمهور المستهدف <span class="text-red-500">*</span></label>
                <select name="audience" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="all" @selected(old('audience') === 'all')>الجميع</option>
                    <option value="teachers" @selected(old('audience') === 'teachers')>المعلمون</option>
                    <option value="guardians" @selected(old('audience') === 'guardians')>أولياء الأمور</option>
                    <option value="classroom" @selected(old('audience') === 'classroom')>صف معين</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">اختر الصف عند تحديد صف معين</label>
                <select name="classroom_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">—</option>
                    @foreach($classrooms as $classroom)
                        <option value="{{ $classroom->id }}" @selected(old('classroom_id') == $classroom->id)>{{ $classroom->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">نشر</button>
    </form>
</div>

@php
    $audienceLabels = [
        'all' => 'الجميع',
        'teachers' => 'المعلمون',
        'guardians' => 'أولياء الأمور',
        'classroom' => 'صف معين',
    ];
@endphp

@forelse($announcements as $announcement)
    <div class="bg-white rounded-xl shadow overflow-hidden mb-4 p-4">
        <div class="flex justify-between items-start mb-2">
            <h3 class="font-bold text-lg">{{ $announcement->title }}</h3>
            <div class="flex items-center gap-2 shrink-0 mr-2">
                <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-700">{{ $audienceLabels[$announcement->audience] ?? $announcement->audience }}</span>
                @if($announcement->classroom)
                    <span class="text-xs text-gray-500">{{ $announcement->classroom->name }}</span>
                @endif
                <form method="POST" action="{{ route('admin.announcements.destroy', $announcement) }}" onsubmit="return confirm('هل أنت متأكد؟')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-red-600 hover:underline text-sm">حذف</button>
                </form>
            </div>
        </div>
        <p class="text-gray-600 text-sm mb-2">{{ $announcement->body }}</p>
        <div class="text-xs text-gray-400">
            {{ $announcement->author?->name }}
            @if($announcement->published_at)
                &bull; {{ $announcement->published_at->format('Y-m-d H:i') }}
            @endif
        </div>
    </div>
@empty
    <div class="bg-white rounded-xl shadow p-6 text-center text-gray-500">لا توجد إعلانات</div>
@endforelse

<div class="mt-4">
    {{ $announcements->links() }}
</div>
@endsection
