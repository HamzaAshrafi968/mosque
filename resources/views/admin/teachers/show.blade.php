@extends('layouts.app')

@section('title', 'ملف المعلم - ' . $teacher->name)

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.teachers.index') }}" class="text-emerald-700 hover:underline text-sm">&larr; العودة للمعلمين</a>
</div>

<div class="bg-white rounded-xl shadow overflow-hidden mb-6">
    <div class="p-6 flex flex-wrap items-center gap-5">
        <div class="w-20 h-20 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-700 font-bold text-3xl">
            {{ mb_substr($teacher->name, 0, 1) }}
        </div>
        <div class="flex-1 min-w-48">
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-800">{{ $teacher->name }}</h1>
                <span @class([
                    'px-2 py-1 rounded-full text-xs font-bold',
                    'bg-green-100 text-green-800' => $teacher->is_active,
                    'bg-red-100 text-red-800' => !$teacher->is_active,
                ])>
                    {{ $teacher->is_active ? 'نشط' : 'غير نشط' }}
                </span>
            </div>
            <div class="text-gray-500 text-sm mt-2 space-y-1">
                <div>التخصص: {{ $teacher->specialty ?? '—' }}</div>
                <div>الجنس: {{ $teacher->gender === 'male' ? 'ذكر' : 'أنثى' }}</div>
                <div>الهاتف: {{ $teacher->phone ?? '—' }} &bull; البريد: {{ $teacher->email ?? '—' }}</div>
                <div>تاريخ التعيين: {{ $teacher->hired_at?->format('Y-m-d') ?? '—' }}</div>
                <div>المواد: {{ $teacher->subjects->pluck('name')->join('، ') ?: '—' }}</div>
            </div>
        </div>
        <div class="text-center px-6 border-r border-gray-200">
            <div class="text-4xl font-bold text-amber-500">{{ $avgRating > 0 ? $avgRating : '—' }}</div>
            <div class="text-sm text-gray-500 mt-1">متوسط التقييم</div>
            <div class="text-amber-400 text-sm mt-1">{{ str_repeat('★', round($avgRating)) }}<span class="text-gray-300">{{ str_repeat('★', 5 - round($avgRating)) }}</span></div>
            <div class="text-xs text-gray-400 mt-1">({{ $teacher->ratings->count() }} تقييم)</div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="px-4 py-3 bg-emerald-700 text-white font-bold">⭐ تقييم المعلم</div>
        <div class="p-4">
            <form method="POST" action="{{ route('admin.teachers.ratings.store', $teacher) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">التقييم (1-5) <span class="text-red-500">*</span></label>
                    <div class="flex items-center gap-2">
                        @for($i = 1; $i <= 5; $i++)
                            <label class="cursor-pointer">
                                <input type="radio" name="rating" value="{{ $i }}" class="peer sr-only" required>
                                <span class="peer-checked:bg-amber-100 peer-checked:scale-110 peer-checked:border-amber-400 border border-gray-300 rounded-lg px-3 py-2 text-amber-500 font-bold transition">★{{ $i }}</span>
                            </label>
                        @endfor
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ملاحظات</label>
                    <textarea name="comment" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none"></textarea>
                </div>
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg w-full">إضافة التقييم</button>
            </form>
        </div>
        <div class="divide-y max-h-72 overflow-y-auto">
            @forelse($teacher->ratings as $rating)
                <div class="px-4 py-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-amber-400">{{ str_repeat('★', $rating->rating) }}</span>
                            <span class="text-xs text-gray-500">{{ $rating->user?->name ?? 'مستخدم' }} &bull; {{ $rating->created_at->format('Y-m-d') }}</span>
                        </div>
                        <form method="POST" action="{{ route('admin.teachers.ratings.destroy', [$teacher, $rating]) }}" onsubmit="return confirm('هل أنت متأكد من حذف التقييم؟')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-500 hover:underline text-xs">حذف</button>
                        </form>
                    </div>
                    @if($rating->comment)
                        <p class="text-sm text-gray-600 mt-1">{{ $rating->comment }}</p>
                    @endif
                </div>
            @empty
                <div class="px-4 py-6 text-center text-gray-500 text-sm">لا توجد تقييمات بعد</div>
            @endforelse
        </div>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="px-4 py-3 bg-emerald-700 text-white font-bold">🏆 شهادات المعلم</div>
        <div class="p-4">
            <form method="POST" action="{{ route('admin.teachers.certificates.store', $teacher) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">اسم الشهادة <span class="text-red-500">*</span></label>
                    <input type="text" name="title" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الجهة المانحة</label>
                    <input type="text" name="issuer" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">السنة</label>
                    <input type="text" name="year" maxlength="10" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg w-full">إضافة الشهادة</button>
            </form>
        </div>
        <div class="divide-y max-h-72 overflow-y-auto">
            @forelse($teacher->certificates as $certificate)
                <div class="px-4 py-3 flex items-center justify-between gap-2">
                    <div>
                        <div class="font-bold text-gray-800">🎓 {{ $certificate->title }}</div>
                        <div class="text-xs text-gray-500 mt-0.5">
                            {{ $certificate->issuer ? $certificate->issuer . ' — ' : '' }}{{ $certificate->year ?? '' }}
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.teachers.certificates.destroy', [$teacher, $certificate]) }}" onsubmit="return confirm('هل أنت متأكد من حذف الشهادة؟')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-500 hover:underline text-xs">حذف</button>
                    </form>
                </div>
            @empty
                <div class="px-4 py-6 text-center text-gray-500 text-sm">لا توجد شهادات بعد</div>
            @endforelse
        </div>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="px-4 py-3 bg-emerald-700 text-white font-bold">📊 نشاط المعلم</div>
        <div class="p-4 grid grid-cols-2 gap-3">
            <div class="bg-emerald-50 rounded-xl p-4 text-center">
                <div class="text-2xl font-bold text-emerald-700">{{ $activity['lessons_count'] }}</div>
                <div class="text-xs text-gray-600 mt-1">درس منشور</div>
            </div>
            <div class="bg-blue-50 rounded-xl p-4 text-center">
                <div class="text-2xl font-bold text-blue-700">{{ $activity['exams_count'] }}</div>
                <div class="text-xs text-gray-600 mt-1">امتحان</div>
            </div>
            <div class="bg-purple-50 rounded-xl p-4 text-center">
                <div class="text-2xl font-bold text-purple-700">{{ $activity['homeworks_count'] }}</div>
                <div class="text-xs text-gray-600 mt-1">واجب</div>
            </div>
            <div class="bg-amber-50 rounded-xl p-4 text-center">
                <div class="text-2xl font-bold text-amber-700">{{ $activity['schedules_count'] }}</div>
                <div class="text-xs text-gray-600 mt-1">حصّة أسبوعية</div>
            </div>
            <div class="bg-cyan-50 rounded-xl p-4 text-center">
                <div class="text-2xl font-bold text-cyan-700">{{ $activity['attendance_days'] }}</div>
                <div class="text-xs text-gray-600 mt-1">يوم تسجيل حضور</div>
            </div>
            <div class="bg-green-50 rounded-xl p-4 text-center">
                <div class="text-2xl font-bold text-green-700">{{ $activity['graded_students'] }}</div>
                <div class="text-xs text-gray-600 mt-1">طالب مُصحَّح</div>
            </div>
        </div>
    </div>
</div>
@endsection
