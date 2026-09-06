@extends('layouts.app')

@section('title', 'التسميع')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-800">🗣️ التسميع — سجلاتي</h2>
            <p class="text-sm text-gray-500 mt-1">كل تسجيلات التسميع محفوظة تاريخياً ولا تُحذف</p>
        </div>
        <a href="{{ route('teacher.quran.tasmee.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">+ تسجيل تسميع</a>
    </div>

    <form method="GET" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 grid grid-cols-2 md:grid-cols-3 gap-3 items-end">
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">النوع</label>
            <select name="type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">الكل</option>
                @foreach($types as $type)
                    <option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">الطالب</label>
            <select name="student_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">الكل</option>
                @foreach($students as $student)
                    <option value="{{ $student->id }}" @selected(request('student_id') === $student->id)>{{ $student->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">تصفية</button>
    </form>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600">
                        <th class="px-4 py-3 text-right">التاريخ</th>
                        <th class="px-4 py-3 text-right">الطالب</th>
                        <th class="px-4 py-3 text-right">النوع</th>
                        <th class="px-4 py-3 text-right">المقدار</th>
                        <th class="px-4 py-3 text-right">المقروء</th>
                        <th class="px-4 py-3 text-right">النتيجة</th>
                        <th class="px-4 py-3 text-center">إجراء</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($sessions as $session)
                    <tr class="border-t">
                        <td class="px-4 py-3 whitespace-nowrap">{{ $session->date->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <a href="{{ route('teacher.quran.students.journey', $session->student) }}" class="text-emerald-700 font-bold hover:underline">{{ $session->student->name }}</a>
                        </td>
                        <td class="px-4 py-3">
                            <span @class([
                                'px-2 py-0.5 rounded-full text-xs font-bold',
                                'bg-emerald-100 text-emerald-800' => $session->type->value === 'new',
                                'bg-sky-100 text-sky-800' => $session->type->value === 'revision',
                            ])>{{ $session->type->label() }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $session->amount }}</td>
                        <td class="px-4 py-3">{{ $session->recited_portion ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if($session->result)
                                <span @class([
                                    'px-2 py-0.5 rounded-full text-xs font-bold',
                                    'bg-green-100 text-green-800' => $session->result->value === 'excellent',
                                    'bg-emerald-50 text-emerald-700' => $session->result->value === 'very_good',
                                    'bg-sky-100 text-sky-800' => $session->result->value === 'good',
                                    'bg-yellow-100 text-yellow-800' => $session->result->value === 'needs_review',
                                ])>{{ $session->result->label() }}</span>
                            @else <span class="text-gray-300">—</span> @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <a href="{{ route('teacher.quran.tasmee.edit', $session) }}" class="text-xs text-emerald-700 hover:underline">تعديل</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">لا توجد سجلات تسميع</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-100">{{ $sessions->links() }}</div>
    </div>
</div>
@endsection
