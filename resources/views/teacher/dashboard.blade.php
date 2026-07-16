@extends('layouts.app')

@section('title', 'الصفحة الرئيسية')

@section('content')
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow p-6">
        <div class="text-4xl font-bold text-emerald-700">{{ $todaySchedule->count() }}</div>
        <div class="text-gray-600 mt-1">عدد حصص اليوم</div>
    </div>
    <div class="bg-white rounded-xl shadow p-6">
        <div class="text-4xl font-bold text-emerald-700">{{ $pendingSubmissions }}</div>
        <div class="text-gray-600 mt-1">واجبات بانتظار التصحيح</div>
    </div>
</div>

<div class="bg-white rounded-xl shadow overflow-hidden mb-6">
    <h2 class="text-lg font-bold text-gray-800 p-4 border-b">جدول اليوم</h2>
    @if($todaySchedule->isEmpty())
        <p class="text-gray-500 p-6 text-center">لا توجد حصص اليوم</p>
    @else
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50 text-gray-600 text-sm">
                    <th class="px-4 py-3 text-right">الوقت</th>
                    <th class="px-4 py-3 text-right">المادة</th>
                    <th class="px-4 py-3 text-right">الصف</th>
                    <th class="px-4 py-3 text-right">الشعبة</th>
                </tr>
            </thead>
            <tbody>
                @foreach($todaySchedule as $schedule)
                    <tr>
                        <td class="px-4 py-3 border-t">{{ $schedule->starts_at }} - {{ $schedule->ends_at }}</td>
                        <td class="px-4 py-3 border-t">{{ $schedule->subject?->name }}</td>
                        <td class="px-4 py-3 border-t">{{ $schedule->classroom?->name }}</td>
                        <td class="px-4 py-3 border-t">{{ $schedule->section?->name ?? 'كل الشعب' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <h2 class="text-lg font-bold text-gray-800 p-4 border-b">آخر الإعلانات</h2>
    @if($announcements->isEmpty())
        <p class="text-gray-500 p-6 text-center">لا توجد إعلانات</p>
    @else
        <div class="divide-y divide-gray-200">
            @foreach($announcements as $announcement)
                <div class="p-4">
                    <div class="flex justify-between items-start">
                        <h3 class="font-bold text-gray-800">{{ $announcement->title }}</h3>
                        <span class="text-sm text-gray-500">{{ $announcement->published_at?->format('Y-m-d') }}</span>
                    </div>
                    <p class="text-gray-600 mt-2 text-sm">{{ $announcement->body }}</p>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
