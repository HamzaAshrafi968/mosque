@extends('layouts.app')

@section('title', 'البرامج القرآنية')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-800">البرامج القرآنية</h2>
            <p class="text-sm text-gray-500 mt-1">رحلة الطالب: تسميع ← إتمام الحفظ ← حافظ ← البرنامج التأهيلي ← برنامج الإجازة</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('admin.quran.tasmee.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">+ تسجيل تسميع</a>
            <a href="{{ route('admin.quran.completions.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">+ تسجيل إتمام حفظ</a>
            <a href="{{ route('admin.faith-meetings.create') }}" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-bold px-4 py-2 rounded-lg">+ لقاء إيماني</a>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <a href="{{ route('admin.quran.hafiz.index') }}" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 hover:shadow-md transition">
            <div class="text-3xl font-extrabold text-emerald-700">{{ $stats['hafiz'] }}</div>
            <div class="text-xs text-gray-500 mt-1 font-bold">عدد الحفاظ</div>
        </a>
        <a href="{{ route('admin.quran.qualifying.index') }}" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 hover:shadow-md transition">
            <div class="text-3xl font-extrabold text-teal-700">{{ $stats['qualifying'] }}</div>
            <div class="text-xs text-gray-500 mt-1 font-bold">بالبرنامج التأهيلي</div>
        </a>
        <a href="{{ route('admin.quran.ijazah.index') }}" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 hover:shadow-md transition">
            <div class="text-3xl font-extrabold text-amber-700">{{ $stats['ijazah'] }}</div>
            <div class="text-xs text-gray-500 mt-1 font-bold">ببرنامج الإجازة</div>
        </a>
        <a href="{{ route('admin.quran.completions.index', ['status' => 'pending']) }}" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 hover:shadow-md transition">
            <div class="text-3xl font-extrabold text-orange-600">{{ $stats['pendingCompletions'] }}</div>
            <div class="text-xs text-gray-500 mt-1 font-bold">بانتظار التأكيد</div>
        </a>
        <a href="{{ route('admin.quran.exams.index', ['month' => $currentMonth]) }}" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 hover:shadow-md transition">
            <div class="text-3xl font-extrabold text-sky-700">{{ $stats['untestedMonth'] }}</div>
            <div class="text-xs text-gray-500 mt-1 font-bold">لم يختبروا هذا الشهر</div>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b bg-gray-50 flex items-center justify-between">
                <span class="font-bold text-gray-800">🎓 بانتظار تأكيد إتمام الحفظ</span>
                <a href="{{ route('admin.quran.completions.index', ['status' => 'pending']) }}" class="text-xs text-emerald-700 hover:underline">الكل</a>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($pendingCompletions as $completion)
                    <div class="px-5 py-3 flex items-center justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <a href="{{ route('admin.quran.journey', $completion->student) }}" class="font-bold text-gray-800 hover:text-emerald-700">{{ $completion->student->name }}</a>
                            <div class="text-xs text-gray-500">{{ $completion->completed_at?->format('Y-m-d') }} · {{ $completion->notes ? mb_substr($completion->notes, 0, 60) : 'بدون ملاحظات' }}</div>
                        </div>
                        <form method="POST" action="{{ route('admin.quran.completions.confirm', $completion) }}">
                            @csrf
                            <button class="text-xs bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-3 py-1.5 rounded-lg">تأكيد → حافظ</button>
                        </form>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-gray-400 text-sm">لا توجد طلبات بانتظار التأكيد 🎉</div>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b bg-gray-50 font-bold text-gray-800">⚠️ آخر نتيجة «يحتاج مراجعة»</div>
            <div class="divide-y divide-gray-50">
                @forelse($needsReview as $student)
                    <a href="{{ route('admin.quran.journey', $student) }}" class="px-5 py-3 flex items-center justify-between hover:bg-gray-50">
                        <span class="font-bold text-gray-800 text-sm">{{ $student->name }}</span>
                        <span class="text-xs text-gray-500">{{ $student->classroom?->name }}</span>
                    </a>
                @empty
                    <div class="px-5 py-8 text-center text-gray-400 text-sm">لا يوجد طلاب بحاجة لمراجعة</div>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b bg-gray-50 font-bold text-gray-800">🔥 مقتربون من إتمام الحفظ</div>
            <div class="divide-y divide-gray-50">
                @forelse($closeToCompletion as $student)
                    <a href="{{ route('admin.quran.journey', $student) }}" class="px-5 py-3 flex items-center justify-between hover:bg-gray-50">
                        <span class="font-bold text-gray-800 text-sm">{{ $student->name }}</span>
                        <span class="text-xs text-gray-500">{{ $student->classroom?->name }}</span>
                    </a>
                @empty
                    <div class="px-5 py-8 text-center text-gray-400 text-sm">لا يوجد بيانات كافية بعد</div>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b bg-gray-50 flex items-center justify-between">
                <span class="font-bold text-gray-800">📋 البرنامج التأهيلي (النشط)</span>
                <a href="{{ route('admin.quran.qualifying.index') }}" class="text-xs text-emerald-700 hover:underline">إدارة</a>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($qualifyingStudents as $student)
                    <a href="{{ route('admin.quran.journey', $student) }}" class="px-5 py-3 flex items-center justify-between hover:bg-gray-50">
                        <span class="font-bold text-gray-800 text-sm">{{ $student->name }}</span>
                        <span class="text-xs text-gray-500">أسبوعي</span>
                    </a>
                @empty
                    <div class="px-5 py-8 text-center text-gray-400 text-sm">لا يوجد طلاب في البرنامج حالياً</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
