@extends('layouts.app')

@section('title', $classroom ? 'تعديل صف' : 'إضافة صف')

@section('content')
<div class="bg-white rounded-xl shadow overflow-hidden p-6 max-w-2xl">
    <h1 class="text-lg font-bold text-gray-800 mb-4">{{ $classroom ? 'تعديل الصف: '.$classroom->name : 'إضافة صف جديد' }}</h1>
    <form method="POST" action="{{ $classroom ? route('admin.classrooms.update', $classroom) : route('admin.classrooms.store') }}" class="space-y-4">
        @csrf
        @if($classroom)@method('PATCH')@endif
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">اسم الصف <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name', $classroom?->name) }}" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الدوام</label>
            <select name="study_session_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <option value="">كل الدوامات (صف مشترك)</option>
                @foreach($sessions as $session)
                    <option value="{{ $session->id }}" @selected(old('study_session_id', $classroom?->study_session_id ?? config('app.current_study_session_id')) == $session->id)>{{ $session->name }}</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-400 mt-1">عند ربط الصف بدوام تتبع شعبه دوامه، وتنتقل شعبه وطلابه وجداوله معه عند التغيير.</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الوصف</label>
            <textarea name="description" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">{{ old('description', $classroom?->description) }}</textarea>
        </div>
        <div class="flex gap-3">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-5 py-2 rounded-lg">{{ $classroom ? 'حفظ التعديلات' : 'إنشاء الصف' }}</button>
            <a href="{{ $classroom ? route('admin.classrooms.show', $classroom) : route('admin.classrooms.index') }}" class="text-gray-500 hover:underline px-3 py-2 text-sm">إلغاء</a>
        </div>
    </form>
</div>
@endsection
