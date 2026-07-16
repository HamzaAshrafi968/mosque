@extends('layouts.app')

@section('title', 'إدارة الدروس')

@section('content')
<div class="mb-4">
    <a href="{{ route('teacher.lessons.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">إضافة درس</a>
</div>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <table class="w-full">
        <thead>
            <tr class="bg-gray-50 text-gray-600 text-sm">
                <th class="px-4 py-3 text-right">العنوان</th>
                <th class="px-4 py-3 text-right">المادة</th>
                <th class="px-4 py-3 text-right">الصف</th>
                <th class="px-4 py-3 text-right">النوع</th>
                <th class="px-4 py-3 text-right">الرابط</th>
                <th class="px-4 py-3 text-right">إجراءات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lessons as $lesson)
                <tr>
                    <td class="px-4 py-3 border-t">{{ $lesson->title }}</td>
                    <td class="px-4 py-3 border-t">{{ $lesson->subject?->name }}</td>
                    <td class="px-4 py-3 border-t">{{ $lesson->classroom?->name ?? '—' }}</td>
                    <td class="px-4 py-3 border-t">
                        @if($lesson->type === 'file')
                            ملف
                        @elseif($lesson->type === 'video')
                            فيديو
                        @elseif($lesson->type === 'link')
                            رابط
                        @elseif($lesson->type === 'presentation')
                            عرض تقديمي
                        @endif
                    </td>
                    <td class="px-4 py-3 border-t">
                        @if($lesson->url)
                            <a href="{{ $lesson->url }}" target="_blank" class="text-emerald-700 hover:underline">فتح الرابط</a>
                        @elseif($lesson->file_path)
                            <a href="{{ asset('storage/'.$lesson->file_path) }}" target="_blank" class="text-emerald-700 hover:underline">تحميل الملف</a>
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-3 border-t">
                        <form method="POST" action="{{ route('teacher.lessons.destroy', $lesson) }}" onsubmit="return confirm('هل أنت متأكد؟')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline font-bold">حذف</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-6 text-center text-gray-500">لا توجد دروس</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <div class="p-4">
        {{ $lessons->links() }}
    </div>
</div>
@endsection
