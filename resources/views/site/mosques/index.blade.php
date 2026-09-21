@extends('site.layouts.app')

@section('title', 'الجوامع والمساجد | '.config('site.name'))
@section('description', 'قائمة جوامع ومساجد '.config('site.name').' مع العناوين وبيانات التواصل، ولمحة عن البرامج في كل جامع.')
@section('canonical', route('site.mosques.index'))

@section('content')
    <section class="gradient-sidebar text-white relative overflow-hidden">
        <div aria-hidden="true" class="absolute inset-0 sidebar-pattern"></div>
        <div class="relative max-w-6xl mx-auto px-4 py-12 text-center">
            <h1 class="text-2xl sm:text-4xl font-black">{{ config('site.mosques_title') }}</h1>
            <p class="text-emerald-50/80 text-sm font-medium mt-3 max-w-2xl mx-auto leading-relaxed">
                جوامع {{ config('site.name') }} وحلقاتها القرآنية — اختر الجامع لعرض بياناته وبرامجه.
            </p>
        </div>
    </section>

    <section class="max-w-6xl mx-auto px-4 py-12">
        @if ($mosques->isEmpty())
            <div class="bg-white border border-pine-100 rounded-3xl p-10 text-center">
                <span class="w-14 h-14 mx-auto rounded-2xl bg-pine-50 text-pine-700 grid place-items-center">
                    <x-icon name="mosque" class="w-7 h-7" />
                </span>
                <p class="text-gray-500 font-semibold mt-4">لا توجد جوامع منشورة حالياً.</p>
                <a href="{{ route('site.home') }}" class="inline-block mt-5 text-emerald-700 font-black hover:underline">
                    العودة إلى الرئيسية
                </a>
            </div>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($mosques as $mosque)
                    @include('site.partials.mosque-card', ['mosque' => $mosque])
                @endforeach
            </div>
        @endif
    </section>
@endsection
