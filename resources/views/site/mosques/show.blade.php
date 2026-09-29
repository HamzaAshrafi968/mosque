@extends('site.layouts.app')

@php
    $logoUrl = $mosque->logoUrl();
    $mapUrl = filled($mosque->map_url)
        ? $mosque->map_url
        : (filled($mosque->address)
            ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($mosque->name.' '.$mosque->address)
            : null);
@endphp

@section('title', $mosque->name.' | '.config('site.name'))
@section('description', \Illuminate\Support\Str::limit(strip_tags($mosque->description ?: config('site.description')), 160))
@section('canonical', route('site.mosques.show', $mosque))
@section('og_type', 'place')
@section('og_title', $mosque->name)
@section('og_description', \Illuminate\Support\Str::limit(strip_tags($mosque->description ?: config('site.description')), 160))

@section('content')
    <section class="gradient-sidebar text-white relative overflow-hidden">
        <div aria-hidden="true" class="absolute inset-0 sidebar-pattern"></div>
        <div class="relative max-w-6xl mx-auto px-4 py-12">
            <nav class="text-xs font-bold text-emerald-100/70 flex items-center gap-2 flex-wrap" aria-label="مسار التنقل">
                <a href="{{ route('site.home') }}" class="hover:text-gold-200 transition">الرئيسية</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('site.mosques.index') }}" class="hover:text-gold-200 transition">المساجد</a>
                <span aria-hidden="true">/</span>
                <span class="text-gold-200">{{ $mosque->name }}</span>
            </nav>

            <div class="flex flex-col sm:flex-row sm:items-center gap-5 mt-6">
                <span
                    class="w-20 h-20 rounded-3xl p-[2px] bg-gradient-to-br from-gold-200 via-gold-400 to-gold-700 shadow-2xl shadow-gold-950/30 shrink-0">
                    <span class="w-full h-full rounded-[22px] bg-white grid place-items-center overflow-hidden">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="شعار {{ $mosque->name }}" class="w-12 h-12 object-contain">
                        @else
                            <img src="{{ asset('images/logo-mark.png') }}" alt="{{ config('site.name') }}"
                                class="w-12 h-12 object-contain">
                        @endif
                    </span>
                </span>

                <div class="min-w-0">
                    <h1 class="text-2xl sm:text-4xl font-black leading-tight">{{ $mosque->name }}</h1>
                    @if (filled($mosque->address))
                        <p class="text-emerald-50/80 text-sm font-semibold mt-2 flex items-center gap-2">
                            <x-icon name="mosque" class="w-4 h-4 text-gold-300 shrink-0" />
                            {{ $mosque->address }}
                        </p>
                    @endif
                </div>
            </div>

            @if (filled($mosque->phone) || filled($mosque->email) || $mapUrl)
                <div class="flex flex-wrap gap-2.5 mt-7">
                    @if (filled($mosque->phone))
                        <a href="tel:{{ $mosque->phone }}"
                            class="bg-white/10 hover:bg-white/20 border border-white/20 rounded-xl px-4 py-2 text-xs font-bold transition inline-flex items-center gap-2">
                            <x-icon name="profile" class="w-4 h-4 text-gold-300" />
                            <span dir="ltr">{{ $mosque->phone }}</span>
                        </a>
                    @endif
                    @if (filled($mosque->email))
                        <a href="mailto:{{ $mosque->email }}"
                            class="bg-white/10 hover:bg-white/20 border border-white/20 rounded-xl px-4 py-2 text-xs font-bold transition inline-flex items-center gap-2">
                            <x-icon name="mail" class="w-4 h-4 text-gold-300" />
                            <span dir="ltr">{{ $mosque->email }}</span>
                        </a>
                    @endif
                    @if ($mapUrl)
                        <a href="{{ $mapUrl }}" target="_blank" rel="noopener"
                            class="bg-gold-400 hover:bg-gold-300 text-pine-950 rounded-xl px-4 py-2 text-xs font-black transition inline-flex items-center gap-2">
                            <x-icon name="target" class="w-4 h-4" />
                            الموقع على الخريطة
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </section>

    <section class="max-w-6xl mx-auto px-4 py-12 grid gap-8 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-8">
            <div class="bg-white border border-pine-100 rounded-3xl p-7">
                <h2 class="text-xl font-black text-pine-950">نبذة عن الجامع</h2>
                <div class="ornament-top mt-3 !mx-0" style="text-align: start"></div>

                @if (filled($mosque->description))
                    <div class="text-gray-600 font-medium leading-relaxed mt-5 space-y-3">
                        @foreach (preg_split('/\r\n|\r|\n/', trim($mosque->description)) as $paragraph)
                            @if (filled($paragraph))
                                <p>{{ $paragraph }}</p>
                            @endif
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500 font-semibold mt-5">
                        {{ $mosque->name }} أحد مساجد {{ config('site.name') }}، يضم حلقات تحفيظ القرآن الكريم والبرامج
                        التعليمية التابعة للمؤسسة.
                    </p>
                @endif
            </div>

            <div class="bg-white border border-pine-100 rounded-3xl p-7">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <div>
                        <h2 class="text-xl font-black text-pine-950">تبرعات ومساهمات أهل الخير</h2>
                        <div class="ornament-top mt-3 !mx-0" style="text-align: start"></div>
                    </div>
                    <button type="button" data-donation-open
                        class="inline-flex items-center gap-2 bg-gradient-to-l from-pine-800 via-emerald-700 to-emerald-600 hover:from-pine-900 hover:to-emerald-700 text-white text-sm font-black px-4 py-2.5 rounded-xl transition">
                        <x-icon name="gift" class="w-4 h-4" />
                        شاركنا الخير
                    </button>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 mt-6">
                    @forelse ($donations as $donation)
                        <article class="rounded-2xl border border-pine-100 bg-[#f8faf9] p-4 flex flex-col gap-2.5">
                            <div class="flex items-center gap-2 flex-wrap">
                                @php
                                    $tone = match ($donation->type->value) {
                                        'financial' => 'bg-emerald-100 text-emerald-700',
                                        'in_kind' => 'bg-sky-100 text-sky-700',
                                        'moral' => 'bg-violet-100 text-violet-700',
                                        default => 'bg-gold-100 text-gold-700',
                                    };
                                @endphp
                                <span class="text-[11px] font-black px-2.5 py-1 rounded-full {{ $tone }}">{{ $donation->typeLabel() }}</span>
                                @if ($donation->amountLabel())
                                    <span class="text-sm font-black text-pine-950" dir="ltr">{{ $donation->amountLabel() }}</span>
                                @endif
                            </div>
                            <h3 class="font-black text-sm text-pine-950">{{ $donation->displayTitle() }}</h3>
                            @if (filled($donation->description))
                                <p class="text-xs text-gray-600 font-medium leading-relaxed">{{ \Illuminate\Support\Str::limit($donation->description, 160) }}</p>
                            @endif
                            <div class="mt-auto flex items-center gap-2 text-[11px] font-bold text-gray-400">
                                <x-icon name="heart" class="w-3.5 h-3.5 text-gold-500" />
                                {{ $donation->displayDonorName() }}
                                @if ($donation->delivery_date)
                                    <span aria-hidden="true">•</span>
                                    <span>التسليم: {{ $donation->delivery_date->translatedFormat('j F Y H:i') }}</span>
                                @endif
                                <span aria-hidden="true">•</span>
                                <time datetime="{{ $donation->created_at->toDateString() }}">{{ $donation->created_at->translatedFormat('j F Y') }}</time>
                            </div>
                        </article>
                    @empty
                        <div class="sm:col-span-2 text-center rounded-2xl border border-dashed border-pine-200 py-8 px-4">
                            <span class="inline-grid place-items-center w-12 h-12 rounded-2xl bg-gold-100 text-gold-600 mx-auto">
                                <x-icon name="gift" class="w-6 h-6" />
                            </span>
                            <p class="text-sm font-bold text-gray-500 mt-3">كن أول من يساهم — ماديًا أو عينيًا أو بوقتك ومهاراتك</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="bg-white border border-pine-100 rounded-3xl p-7">
                <h2 class="text-xl font-black text-pine-950">البرامج والأنشطة</h2>
                <div class="ornament-top mt-3 !mx-0" style="text-align: start"></div>

                <div class="grid gap-4 sm:grid-cols-2 mt-6">
                    @foreach (config('site.programs') as $program)
                        <div class="bg-[#f8faf9] border border-pine-100 rounded-2xl p-4 flex items-start gap-3">
                            <span
                                class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-600 to-pine-800 text-white grid place-items-center shrink-0">
                                <x-icon :name="$program['icon']" class="w-5 h-5" />
                            </span>
                            <div>
                                <h3 class="font-black text-sm text-pine-950">{{ $program['title'] }}</h3>
                                <p class="text-xs text-gray-600 font-medium mt-1 leading-relaxed">{{ $program['description'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <aside class="space-y-5">
            <div class="bg-white border border-pine-100 rounded-3xl p-6">
                <h2 class="font-black text-pine-950">بيانات التواصل</h2>

                <ul class="mt-4 space-y-3 text-sm font-semibold text-gray-600">
                    @if (filled($mosque->address))
                        <li class="flex items-start gap-2">
                            <x-icon name="mosque" class="w-4 h-4 text-gold-600 shrink-0 mt-0.5" />
                            <span>{{ $mosque->address }}</span>
                        </li>
                    @endif
                    @if (filled($mosque->phone))
                        <li class="flex items-center gap-2">
                            <x-icon name="profile" class="w-4 h-4 text-gold-600 shrink-0" />
                            <a href="tel:{{ $mosque->phone }}" dir="ltr" class="hover:text-emerald-700 transition">{{ $mosque->phone }}</a>
                        </li>
                    @endif
                    @if (filled($mosque->email))
                        <li class="flex items-center gap-2">
                            <x-icon name="mail" class="w-4 h-4 text-gold-600 shrink-0" />
                            <a href="mailto:{{ $mosque->email }}" dir="ltr" class="hover:text-emerald-700 transition">{{ $mosque->email }}</a>
                        </li>
                    @endif
                    @if (blank($mosque->address) && blank($mosque->phone) && blank($mosque->email))
                        <li class="text-gray-400">بيانات التواصل قيد التحديث.</li>
                    @endif
                </ul>

                @if ($mapUrl)
                    <a href="{{ $mapUrl }}" target="_blank" rel="noopener"
                        class="mt-5 flex items-center justify-center gap-2 bg-pine-800 hover:bg-pine-900 text-white font-black px-4 py-2.5 rounded-xl transition text-sm">
                        <x-icon name="target" class="w-4 h-4" />
                        افتح على الخريطة
                    </a>
                @endif
            </div>

            <div class="bg-white border border-pine-100 rounded-3xl p-6">
                <h2 class="font-black text-pine-950">لطلاب الجامع وأولياء الأمور</h2>
                <p class="text-sm text-gray-600 font-medium mt-2 leading-relaxed">
                    تابع الحضور والدرجات والواجبات والبرامج القرآنية من بوابة الطالب أو ولي الأمر.
                </p>
                <a href="{{ route('login') }}"
                    class="mt-4 flex items-center justify-center gap-2 bg-gradient-to-l from-pine-800 via-emerald-700 to-emerald-600 text-white font-black px-4 py-2.5 rounded-xl transition text-sm">
                    دخول النظام
                </a>
            </div>

            <a href="{{ route('site.mosques.index') }}"
                class="block text-center text-emerald-700 font-black text-sm hover:underline">
                ← كل المساجد
            </a>
        </aside>
    </section>

    @push('schema')
        @php
            $mosqueSchema = array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Mosque',
                'name' => $mosque->name,
                'url' => route('site.mosques.show', $mosque),
                'description' => $mosque->description ?: null,
                'telephone' => $mosque->phone ?: null,
                'email' => $mosque->email ?: null,
                'image' => $logoUrl,
                'address' => filled($mosque->address) ? [
                    '@type' => 'PostalAddress',
                    'streetAddress' => $mosque->address,
                ] : null,
                'hasMap' => $mosque->map_url ?: null,
                'parentOrganization' => [
                    '@type' => 'EducationalOrganization',
                    'name' => config('site.name'),
                    'url' => route('site.home'),
                ],
            ], fn ($value) => filled($value));
        @endphp
        <script
            type="application/ld+json">{!! json_encode($mosqueSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endpush
@endsection
