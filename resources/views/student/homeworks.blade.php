@extends('layouts.app')

@section('title', 'الواجبات')

@section('content')
<h1 class="text-2xl font-bold text-gray-800 mb-6">الواجبات</h1>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    @forelse($homeworks as $row)
        @php $homework = $row['homework']; $submission = $row['submission']; @endphp
        <div class="bg-white rounded-2xl shadow p-5 flex flex-col">
            <div class="flex items-center justify-between mb-2">
                <h3 class="font-bold text-gray-800">{{ $homework->title }}</h3>
                <span class="text-xs px-2.5 py-1 rounded-lg bg-emerald-100 text-emerald-700">{{ $homework->subject?->name ?? 'عام' }}</span>
            </div>
            <p class="text-sm text-gray-600 whitespace-pre-wrap mb-3">{{ $homework->description ?? '—' }}</p>
            <div class="text-sm text-gray-500 mb-3">
                الاستحقاق: <span class="font-bold {{ $homework->due_date->isPast() ? 'text-red-500' : 'text-gray-800' }}">{{ $homework->due_date->format('Y-m-d') }}</span>
            </div>

            @if($submission)
                @if($submission->status === 'graded')
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl p-3 text-sm mb-3">
                        ✅ تم تصحيح الواجب
                        @if($submission->grade !== null)
                            — الدرجة: <b>{{ $submission->grade }}</b>
                        @endif
                        @if($submission->feedback)
                            <div class="mt-1 text-emerald-700">{{ $submission->feedback }}</div>
                        @endif
                    </div>
                @elseif($submission->submitted_at)
                    <div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-xl p-3 text-sm mb-3">
                        📤 أُرسل في {{ $submission->submitted_at->format('Y-m-d H:i') }} — بانتظار التصحيح
                    </div>
                @endif

                @if($submission->status !== 'graded')
                    <form method="POST" action="{{ route('student.homeworks.submit', $homework) }}" class="mt-auto">
                        @csrf
                        <textarea name="content" rows="3" required
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                                  placeholder="اكتب إجابة الواجب هنا ثم أرسلها">{{ old('content', $submission->content) }}</textarea>
                        @error('homework')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                        <button type="submit" class="mt-2 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">
                            {{ $submission->submitted_at ? 'تحديث الإرسال' : 'إرسال الواجب' }}
                        </button>
                    </form>
                @endif
            @else
                <p class="text-xs text-gray-400 mt-auto">لم يُنشأ ملف تسليم لهذا الواجب</p>
            @endif
        </div>
    @empty
        <div class="col-span-full bg-white rounded-2xl shadow p-8 text-center text-gray-400">
            لا توجد واجبات حالياً
        </div>
    @endforelse
</div>
@endsection
