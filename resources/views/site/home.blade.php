@extends('site.layouts.app')

@section('title', config('site.name').' | '.config('site.tagline'))
@section('description', config('site.description'))
@section('canonical', route('site.home'))

@section('content')
    @php
        $mosqueCount = $mosques->count();
        $mosqueCountLabel = match (true) {
            $mosqueCount === 0 => 'لا توجد جوامع منشورة بعد',
            $mosqueCount === 1 => 'جامع واحد',
            $mosqueCount === 2 => 'جامعان',
            $mosqueCount >= 3 && $mosqueCount <= 10 => $mosqueCount.' جوامع',
            default => $mosqueCount.' جامعاً',
        };
    @endphp

    {{-- الواجهة الرئيسية --}}
    <section class="gradient-sidebar text-white relative overflow-hidden">
        <div aria-hidden="true" class="absolute inset-0 sidebar-pattern"></div>
        <div aria-hidden="true" class="absolute -top-24 -start-24 w-96 h-96 rounded-full bg-gold-400/10 blur-3xl"></div>
        <div aria-hidden="true" class="absolute -bottom-32 -end-20 w-[28rem] h-[28rem] rounded-full bg-emerald-300/10 blur-3xl"></div>

        <div class="relative max-w-6xl mx-auto px-4 py-16 sm:py-24 text-center">
            <span
                class="inline-flex items-center gap-2 bg-white/10 border border-white/15 rounded-full px-4 py-1.5 text-xs font-bold text-gold-200">
                <x-icon name="mosque" class="w-4 h-4" />
                {{ config('site.tagline') }}
            </span>

            <h1 class="text-3xl sm:text-5xl font-black mt-6 leading-tight">
                {{ config('site.name') }}
            </h1>

            <p class="max-w-2xl mx-auto text-emerald-50/85 mt-5 text-sm sm:text-base font-medium leading-relaxed">
                {{ config('site.description') }}
            </p>

            <div class="flex flex-wrap items-center justify-center gap-3 mt-9">
                <a href="{{ route('site.mosques.index') }}"
                    class="btn-shine bg-gold-400 hover:bg-gold-300 text-pine-950 font-black px-6 py-3 rounded-2xl shadow-xl shadow-gold-950/20 transition hover:-translate-y-0.5">
                    تصفّح الجوامع
                </a>
                <a href="{{ route('login') }}"
                    class="bg-white/10 hover:bg-white/20 border border-white/20 text-white font-black px-6 py-3 rounded-2xl transition">
                    دخول النظام
                </a>
            </div>

            <p class="mt-9 text-xs font-bold text-emerald-100/70">{{ $mosqueCountLabel }} داخل المؤسسة</p>
        </div>
    </section>

    {{-- من نحن --}}
    <section id="about" class="max-w-6xl mx-auto px-4 py-14">
        <div class="text-center">
            <h2 class="text-2xl sm:text-3xl font-black text-pine-950">{{ config('site.about_title') }}</h2>
            <div class="ornament-top mt-3"></div>
        </div>

        <div class="max-w-3xl mx-auto mt-6 space-y-4 text-center text-gray-600 font-medium leading-relaxed">
            @foreach (config('site.about') as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </div>
    </section>

    {{-- البرامج --}}
    <section id="programs" class="bg-white border-y border-pine-100/70">
        <div class="max-w-6xl mx-auto px-4 py-14">
            <div class="text-center">
                <h2 class="text-2xl sm:text-3xl font-black text-pine-950">{{ config('site.programs_title') }}</h2>
                <div class="ornament-top mt-3"></div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 mt-9 animate-stagger">
                @foreach (config('site.programs') as $program)
                    <article class="card-hover bg-[#f8faf9] border border-pine-100 rounded-3xl p-6">
                        <span
                            class="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-600 to-pine-800 text-white grid place-items-center shadow-lg shadow-emerald-900/15">
                            <x-icon :name="$program['icon']" class="w-6 h-6" />
                        </span>
                        <h3 class="font-black text-lg text-pine-950 mt-4">{{ $program['title'] }}</h3>
                        <p class="text-sm text-gray-600 font-medium mt-2 leading-relaxed">{{ $program['description'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- الجوامع --}}
    <section id="mosques" class="max-w-6xl mx-auto px-4 py-14">
        <div class="text-center">
            <h2 class="text-2xl sm:text-3xl font-black text-pine-950">{{ config('site.mosques_title') }}</h2>
            <div class="ornament-top mt-3"></div>
        </div>

        @if ($mosques->isEmpty())
            <p class="text-center text-gray-500 font-semibold mt-8">لا توجد جوامع منشورة حالياً.</p>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 mt-9">
                @foreach ($mosques->take(6) as $mosque)
                    @include('site.partials.mosque-card', ['mosque' => $mosque])
                @endforeach
            </div>

            @if ($mosques->count() > 6)
                <div class="text-center mt-9">
                    <a href="{{ route('site.mosques.index') }}"
                        class="inline-flex items-center gap-2 bg-pine-800 hover:bg-pine-900 text-white font-black px-6 py-3 rounded-2xl transition">
                        عرض كل الجوامع
                        <x-icon name="chevrons-left" class="w-4 h-4" />
                    </a>
                </div>
            @endif
        @endif
    </section>

    {{-- تواصل معنا --}}
    <section id="contact" class="bg-white border-t border-pine-100/70">
        <div class="max-w-6xl mx-auto px-4 py-14">
            <div class="text-center">
                <h2 class="text-2xl sm:text-3xl font-black text-pine-950">{{ config('site.contact_title') }}</h2>
                <div class="ornament-top mt-3"></div>
            </div>

            @php
                $hasContact = filled(config('site.phone')) || filled(config('site.email')) || filled(config('site.address'));
            @endphp

            @if ($hasContact)
                <div class="grid gap-5 sm:grid-cols-3 max-w-4xl mx-auto mt-9">
                    @if (filled(config('site.phone')))
                        <a href="tel:{{ config('site.phone') }}"
                            class="card-hover bg-[#f8faf9] border border-pine-100 rounded-3xl p-6 text-center">
                            <span class="w-12 h-12 mx-auto rounded-2xl bg-pine-50 text-pine-700 grid place-items-center">
                                <x-icon name="profile" class="w-6 h-6" />
                            </span>
                            <h3 class="font-black text-pine-950 mt-3">الهاتف</h3>
                            <p dir="ltr" class="text-sm text-gray-600 font-bold mt-1">{{ config('site.phone') }}</p>
                        </a>
                    @endif

                    @if (filled(config('site.email')))
                        <a href="mailto:{{ config('site.email') }}"
                            class="card-hover bg-[#f8faf9] border border-pine-100 rounded-3xl p-6 text-center">
                            <span class="w-12 h-12 mx-auto rounded-2xl bg-pine-50 text-pine-700 grid place-items-center">
                                <x-icon name="mail" class="w-6 h-6" />
                            </span>
                            <h3 class="font-black text-pine-950 mt-3">البريد الإلكتروني</h3>
                            <p dir="ltr" class="text-sm text-gray-600 font-bold mt-1">{{ config('site.email') }}</p>
                        </a>
                    @endif

                    @if (filled(config('site.address')))
                        <div class="card-hover bg-[#f8faf9] border border-pine-100 rounded-3xl p-6 text-center">
                            <span class="w-12 h-12 mx-auto rounded-2xl bg-pine-50 text-pine-700 grid place-items-center">
                                <x-icon name="mosque" class="w-6 h-6" />
                            </span>
                            <h3 class="font-black text-pine-950 mt-3">العنوان</h3>
                            <p class="text-sm text-gray-600 font-bold mt-1">{{ config('site.address') }}</p>
                        </div>
                    @endif
                </div>
            @else
                <p class="text-center text-gray-500 font-semibold mt-8">بيانات التواصل قيد التحديث.</p>
            @endif
        </div>
    </section>

    @push('schema')
        @php
            $organizationSchema = array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'EducationalOrganization',
                'name' => config('site.name'),
                'alternateName' => config('site.short_name'),
                'url' => route('site.home'),
                'logo' => asset('images/logo-mark.png'),
                'description' => config('site.description'),
                'telephone' => config('site.phone') ?: null,
                'email' => config('site.email') ?: null,
                'address' => filled(config('site.address')) ? [
                    '@type' => 'PostalAddress',
                    'streetAddress' => config('site.address'),
                ] : null,
            ], fn ($value) => filled($value));
        @endphp
        <script
            type="application/ld+json">{!! json_encode($organizationSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endpush
@endsection
