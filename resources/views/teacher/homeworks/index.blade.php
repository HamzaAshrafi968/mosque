@extends('layouts.app')

@section('title', 'الواجبات')

@section('content')
<div class="mb-4">
    <a href="{{ route('teacher.homeworks.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">إنشاء واجب</a>
</div>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <table class="w-full">
        <thead>
            <tr class="bg-gray-50 text-gray-600 text-sm">
                <th class="px-4 py-3 text-right">العنوان</th>
                <th class="px-4 py-3 text-right">المادة</th>
                <th class="px-4 py-3 text-right">الصف</th>
                <th class="px-4 py-3 text-right">الشعبة</th>
                <th class="px-4 py-3 text-right">تاريخ التسليم</th>
                <th class="px-4 py-3 text-right">التسليمات</th>
                <th class="px-4 py-3 text-right">بانتظار التصحيح</th>
                <th class="px-4 py-3 text-right">إجراءات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($homeworks as $homework)
                <tr>
                    <td class="px-4 py-3 border-t">{{ $homework->title }}</td>
                    <td class="px-4 py-3 border-t">{{ $homework->subject?->name }}</td>
                    <td class="px-4 py-3 border-t">{{ $homework->classroom?->name }}</td>
                    <td class="px-4 py-3 border-t">{{ $homework->section?->name ?? 'كل الشعب' }}</td>
                    <td class="px-4 py-3 border-t">{{ $homework->due_date->format('Y-m-d') }}</td>
                    <td class="px-4 py-3 border-t">{{ $homework->submissions_count }}</td>
                    <td class="px-4 py-3 border-t">
                        @if($homework->pending_submissions_count > 0)
                            <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-sm">{{ $homework->pending_submissions_count }}</span>
                        @else
                            {{ $homework->pending_submissions_count }}
                        @endif
                    </td>
                    <td class="px-4 py-3 border-t flex gap-2">
                        <a href="{{ route('teacher.homeworks.submissions', $homework) }}" class="text-emerald-700 hover:underline font-bold">التصحيح</a>
                        <form method="POST" action="{{ route('teacher.homeworks.destroy', $homework) }}" onsubmit="return confirm('هل أنت متأكد؟')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline font-bold">حذف</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-4 py-6 text-center text-gray-500">لا توجد واجبات</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <div class="p-4">
        {{ $homeworks->links() }}
    </div>
</div>
@endsection
