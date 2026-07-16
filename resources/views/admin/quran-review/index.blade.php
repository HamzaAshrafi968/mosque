@extends('layouts.app')

@section('title', 'مراجعات القرآن الكريم')

@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-center">
        <div></div>
        <a href="{{ route('admin.quran-review.statistics') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-sm">
            لوحة الإحصائيات
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <form method="GET" action="{{ route('admin.quran-review.index') }}" class="grid grid-cols-2 md:grid-cols-5 gap-3">
            <select name="teacher_id" class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">كل المعلمين</option>
                @foreach($teachers as $t)
                    <option value="{{ $t->id }}" {{ request('teacher_id') == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                @endforeach
            </select>
            <select name="student_id" class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">كل الطلاب</option>
                @foreach($students as $s)
                    <option value="{{ $s->id }}" {{ request('student_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                @endforeach
            </select>
            <select name="surah_id" class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">كل السور</option>
                @foreach($surahs as $s)
                    <option value="{{ $s->id }}" {{ request('surah_id') == $s->id ? 'selected' : '' }}>{{ $s->name_arabic }}</option>
                @endforeach
            </select>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full rounded-lg border-gray-300 text-sm" placeholder="من تاريخ">
            <div class="flex gap-2">
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full rounded-lg border-gray-300 text-sm" placeholder="إلى تاريخ">
                <button type="submit" class="bg-emerald-700 text-white px-4 py-2 rounded-lg hover:bg-emerald-800 text-sm">تصفية</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        @if($sessions->isEmpty())
            <div class="p-12 text-center text-gray-400">
                <div class="text-4xl mb-3">📖</div>
                <p class="text-lg">لا توجد مراجعات</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-right">الطالب</th>
                            <th class="px-4 py-3 text-right">المعلم</th>
                            <th class="px-4 py-3 text-right">السورة</th>
                            <th class="px-4 py-3 text-right">الآيات</th>
                            <th class="px-4 py-3 text-right">نسبة الإتقان</th>
                            <th class="px-4 py-3 text-right">التاريخ</th>
                            <th class="px-4 py-3 text-center">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($sessions as $session)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">{{ $session->student->name }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $session->teacher->name }}</td>
                                <td class="px-4 py-3">{{ $session->surah->name_arabic }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $session->from_ayah }} - {{ $session->to_ayah }}</td>
                                <td class="px-4 py-3">
                                    <span class="font-bold {{ $session->mastery_percentage >= 90 ? 'text-green-600' : ($session->mastery_percentage >= 70 ? 'text-yellow-600' : 'text-red-600') }}">
                                        {{ $session->mastery_percentage }}%
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-500">{{ $session->date->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex justify-center gap-2">
                                        <a href="{{ route('admin.quran-review.show', $session->id) }}" class="text-emerald-600 hover:text-emerald-800 text-sm">عرض</a>
                                        <a href="{{ route('admin.quran-review.student-report', $session->student_id) }}" class="text-blue-600 hover:text-blue-800 text-sm">تقرير</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4">
                {{ $sessions->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
