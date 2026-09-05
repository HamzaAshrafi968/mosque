@extends('layouts.app')

@section('title', 'واجبات '.$child->name)

@section('content')
@include('guardian.children.partials.header')

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    @forelse($homeworks as $row)
        @php $homework = $row['homework']; $submission = $row['submission']; @endphp
        <div class="bg-white rounded-2xl shadow p-5 flex flex-col">
            <div class="flex items-center justify-between mb-2">
                <h3 class="font-bold text-gray-800">{{ $homework->title }}</h3>
                <span class="text-xs px-2.5 py-1 rounded-lg {{ $homework->subject?->name ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                    {{ $homework->subject?->name ?? 'عام' }}
                </span>
            </div>
            <p class="text-sm text-gray-600 whitespace-pre-wrap mb-3">{{ Str::limit($homework->description ?? '', 180) }}</p>
            <div class="mt-auto text-sm text-gray-500 border-t border-gray-50 pt-3 flex justify-between items-center">
                <span>الاستحقاق: {{ $homework->due_date->format('Y-m-d') }}</span>
                @if($submission)
                    @if($submission->status === 'graded')
                        <span class="text-emerald-600 font-bold text-xs">تم التصحيح — الدرجة {{ $submission->grade }}</span>
                    @elseif($submission->submitted_at)
                        <span class="text-blue-600 font-bold text-xs">تم إرساله {{ $submission->submitted_at->format('Y-m-d') }}</span>
                    @else
                        <span class="text-amber-600 font-bold text-xs">قيد الإنجاز</span>
                    @endif
                @else
                    <span class="text-gray-400 text-xs">—</span>
                @endif
            </div>
        </div>
    @empty
        <div class="col-span-full bg-white rounded-2xl shadow p-8 text-center text-gray-400">
            لا توجد واجبات حالياً
        </div>
    @endforelse
</div>
@endsection
