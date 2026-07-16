@extends('layouts.app')

@section('title', 'الحضور والغياب')

@section('content')
<div class="bg-white rounded-xl shadow overflow-hidden mb-6 p-4">
    <form method="GET" action="{{ route('admin.attendance.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">التاريخ</label>
            <input type="date" name="date" value="{{ $date }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">النوع</label>
            <select name="type" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="students" @selected($type === 'students')>الطلاب</option>
                <option value="teachers" @selected($type === 'teachers')>المعلمون</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الحالة</label>
            <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="">الكل</option>
                <option value="present" @selected(request('status') === 'present')>حاضر</option>
                <option value="absent" @selected(request('status') === 'absent')>غائب</option>
                <option value="late" @selected(request('status') === 'late')>متأخر</option>
            </select>
        </div>
        <div>
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg w-full">بحث</button>
        </div>
    </form>
</div>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <table class="w-full">
        <thead>
            <tr class="bg-gray-50 text-gray-600 text-sm">
                <th class="px-4 py-3 text-right">الاسم</th>
                <th class="px-4 py-3 text-right">التاريخ</th>
                <th class="px-4 py-3 text-right">الحالة</th>
                <th class="px-4 py-3 text-right">ملاحظات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($attendances as $attendance)
                <tr>
                    <td class="px-4 py-3 border-t">
                        @if($type === 'students')
                            {{ $attendance->student?->name }}
                            @if($attendance->student?->classroom?->name)
                                <span class="text-sm text-gray-500 mr-1">- {{ $attendance->student->classroom->name }}</span>
                            @endif
                        @else
                            {{ $attendance->teacher?->name }}
                        @endif
                    </td>
                    <td class="px-4 py-3 border-t">{{ $attendance->date->format('Y-m-d') }}</td>
                    <td class="px-4 py-3 border-t">
                        @if($attendance->status === 'present')
                            <span class="px-2 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800">حاضر</span>
                        @elseif($attendance->status === 'absent')
                            <span class="px-2 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800">غائب</span>
                        @elseif($attendance->status === 'late')
                            <span class="px-2 py-1 rounded-full text-xs font-bold bg-yellow-100 text-yellow-800">متأخر</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 border-t text-sm text-gray-500">{{ $attendance->notes }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">لا توجد سجلات حضور</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $attendances->links() }}
</div>
@endsection
