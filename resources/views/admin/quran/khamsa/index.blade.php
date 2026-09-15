@extends('layouts.app')

@section('title', 'مراجعة 5')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-800">مراجعة 5 (الخمسات)</h2>
            <p class="text-sm text-gray-500 mt-1">كل جزء ٤ خمسات × ٥ صفحات — لا تُفتح خمسات جزء إلا بعد تسجيل حفظه للطالب</p>
        </div>
        <a href="{{ route('admin.quran.khamsa.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">+ تخصيص مراجعة</a>
    </div>

    <form method="GET" action="{{ route('admin.quran.khamsa.index') }}" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">الطالب</label>
            <select name="student_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">الكل</option>
                @foreach($students as $student)
                    <option value="{{ $student->id }}" @selected(request('student_id') === $student->id)>{{ $student->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">الأستاذ</label>
            <select name="teacher_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">الكل</option>
                @foreach($teachers as $teacher)
                    <option value="{{ $teacher->id }}" @selected(request('teacher_id') === $teacher->id)>{{ $teacher->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">الدوام</label>
            <select name="study_session_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">الكل</option>
                @foreach($sessions as $session)
                    <option value="{{ $session->id }}" @selected(request('study_session_id') === $session->id)>{{ $session->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">الحالة</label>
            <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">الكل</option>
                @foreach(\App\Enums\QuranKhamsaReviewStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">تصفية</button>
            <a href="{{ route('admin.quran.khamsa.index') }}" class="bg-white border border-gray-300 text-gray-700 text-sm font-bold px-4 py-2 rounded-lg">إعادة تعيين</a>
        </div>
    </form>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600">
                        <th class="px-4 py-3 text-right">الطالب</th>
                        <th class="px-4 py-3 text-right">الأستاذ / الدوام</th>
                        <th class="px-4 py-3 text-right">الخمسات</th>
                        <th class="px-4 py-3 text-right">الصفحات</th>
                        <th class="px-4 py-3 text-right">التاريخ</th>
                        <th class="px-4 py-3 text-right">الحالة</th>
                        <th class="px-4 py-3 text-center">إجراء</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($reviews as $review)
                    @php $progress = $review->progress(); @endphp
                    <tr class="border-t">
                        <td class="px-4 py-3 whitespace-nowrap font-bold text-gray-800">{{ $review->student?->name ?? '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div>{{ $review->teacher?->name ?? '—' }}</div>
                            <div class="text-xs text-gray-400">{{ $review->studySession?->name ?? '—' }}</div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="font-bold text-gray-700">{{ $progress['completed'] }} / {{ $progress['total'] }} خمسة</div>
                            <div class="w-28 h-1.5 rounded-full bg-gray-100 mt-1 overflow-hidden">
                                <div class="h-full bg-emerald-600" style="width: {{ $progress['percentage'] }}%"></div>
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $review->pagesCount() }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div>{{ $review->assigned_at?->format('Y-m-d') }}</div>
                            @if($review->due_date)
                                <div class="text-xs text-gray-400">استحقاق: {{ $review->due_date->format('Y-m-d') }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span @class([
                                'px-2 py-0.5 rounded-full text-xs font-bold',
                                'bg-amber-100 text-amber-800' => $review->status->value === 'pending',
                                'bg-emerald-100 text-emerald-800' => $review->status->value === 'completed',
                                'bg-gray-200 text-gray-600' => $review->status->value === 'cancelled',
                            ])>{{ $review->status->label() }}</span>
                        </td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            <a href="{{ route('admin.quran.khamsa.show', $review) }}" class="text-xs text-emerald-700 hover:underline">عرض</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-400">لا توجد مراجعات بعد — ابدأ بتخصيص مراجعة جديدة.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-100">{{ $reviews->links() }}</div>
    </div>
</div>
@endsection
