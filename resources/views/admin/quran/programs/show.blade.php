@extends('layouts.app')

@section('title', $program->label())

@section('content')
    <div class="p-4 md:p-6 max-w-7xl mx-auto space-y-6">
        <a href="{{ route('admin.quran.programs.index', ['type' => $program->type->value, 'student_id' => $program->student_id]) }}"
            class="inline-block text-sm font-bold text-emerald-700 hover:text-emerald-800">← مركز برامج الاستماع</a>

        @php $actions['show'] = null; @endphp

        @include('quran.programs.cycle')
    </div>
@endsection
