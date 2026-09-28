<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ trim($__env->yieldContent('title', config('site.name').' | '.config('site.tagline'))) }}</title>
    <meta name="description" content="{{ trim($__env->yieldContent('description', config('site.description'))) }}">
    <meta name="keywords" content="{{ trim($__env->yieldContent('keywords', implode('، ', config('site.keywords')))) }}">
    <meta name="robots" content="{{ trim($__env->yieldContent('robots', 'index, follow')) }}">
    <link rel="canonical" href="{{ trim($__env->yieldContent('canonical', url()->current())) }}">

    <meta property="og:type" content="{{ trim($__env->yieldContent('og_type', 'website')) }}">
    <meta property="og:site_name" content="{{ config('site.name') }}">
    <meta property="og:locale" content="ar_AR">
    <meta property="og:title" content="{{ trim($__env->yieldContent('og_title', config('site.name'))) }}">
    <meta property="og:description" content="{{ trim($__env->yieldContent('og_description', config('site.description'))) }}">
    <meta property="og:url" content="{{ trim($__env->yieldContent('canonical', url()->current())) }}">
    <meta property="og:image" content="{{ asset(config('site.og_image')) }}">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ trim($__env->yieldContent('og_title', config('site.name'))) }}">
    <meta name="twitter:description" content="{{ trim($__env->yieldContent('og_description', config('site.description'))) }}">
    <meta name="twitter:image" content="{{ asset(config('site.og_image')) }}">

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('images/logo-mark.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&family=Amiri:ital,wght@0,400;0,700;1,400&family=Scheherazade+New:wght@400;700&display=swap"
        rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
    @stack('schema')
</head>

<body class="bg-[#f4f6f4] min-h-screen font-sans antialiased text-pine-950 flex flex-col">
    @php
        $socials = collect(config('site.social'))->filter(fn ($url) => filled($url));
        $socialLabels = ['facebook' => 'فيسبوك', 'whatsapp' => 'واتساب', 'telegram' => 'تلغرام', 'youtube' => 'يوتيوب'];
    @endphp

    <header class="sticky top-0 z-50 bg-white/90 backdrop-blur border-b border-pine-100">
        <div class="bg-gradient-to-l from-gold-300 via-gold-400 to-gold-600 text-pine-950">
            <div class="max-w-6xl mx-auto px-4 py-1.5 flex items-center justify-center gap-2.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 text-[12px] sm:text-[13px] font-black">
                    <x-icon name="gift" class="w-4 h-4 shrink-0" />
                    شاركنا الخير — تبرع مادي أو عيني أو مساهمة معنوية
                </span>
                <button type="button" data-donation-open
                    class="inline-flex items-center gap-1.5 bg-pine-900 hover:bg-pine-950 text-gold-200 text-xs font-black px-3 py-1 rounded-full transition active:scale-95">
                    <x-icon name="heart" class="w-3.5 h-3.5 shrink-0" />
                    تبرع الآن
                </button>
            </div>
        </div>

        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-3">
            <a href="{{ route('site.home') }}" class="flex items-center gap-2.5 min-w-0">
                <span
                    class="w-11 h-11 rounded-2xl p-[1.5px] bg-gradient-to-br from-gold-200 via-gold-400 to-gold-700 shadow-lg shadow-gold-950/10 shrink-0">
                    <span class="w-full h-full rounded-[14px] bg-white grid place-items-center overflow-hidden">
                        <img src="{{ asset('images/logo-mark.png') }}" alt="شعار {{ config('site.name') }}"
                            class="w-7 h-7 object-contain">
                    </span>
                </span>
                <span class="min-w-0">
                    <span class="block font-black text-pine-950 leading-tight truncate">{{ config('site.name') }}</span>
                    <span class="block text-[11px] text-gold-700 font-bold">{{ config('site.tagline') }}</span>
                </span>
            </a>

            <nav class="hidden md:flex items-center gap-1 ms-auto text-sm font-bold text-pine-800">
                <a href="{{ route('site.home') }}"
                    class="px-3 py-2 rounded-xl hover:bg-pine-50 transition {{ request()->routeIs('site.home') ? 'text-emerald-700' : '' }}">الرئيسية</a>
                <a href="{{ route('site.mosques.index') }}"
                    class="px-3 py-2 rounded-xl hover:bg-pine-50 transition {{ request()->routeIs('site.mosques.*') ? 'text-emerald-700' : '' }}">الجوامع</a>
                <a href="{{ route('site.home') }}#programs"
                    class="px-3 py-2 rounded-xl hover:bg-pine-50 transition">البرامج</a>
                <a href="{{ route('site.home') }}#contact"
                    class="px-3 py-2 rounded-xl hover:bg-pine-50 transition">تواصل معنا</a>
            </nav>

            <a href="{{ route('login') }}"
                class="btn-shine ms-auto md:ms-0 bg-gradient-to-l from-pine-800 via-emerald-700 to-emerald-600 hover:from-pine-900 hover:to-emerald-700 text-white text-sm font-black px-4 py-2.5 rounded-xl shadow-lg shadow-emerald-900/20 hover:-translate-y-0.5 transition">
                دخول النظام
            </a>
        </div>

        <nav class="md:hidden border-t border-pine-100 bg-white/95">
            <div class="max-w-6xl mx-auto px-4 py-2 flex items-center gap-1 overflow-x-auto text-[13px] font-bold text-pine-800">
                <a href="{{ route('site.home') }}" class="px-3 py-1.5 rounded-lg hover:bg-pine-50 whitespace-nowrap">الرئيسية</a>
                <a href="{{ route('site.mosques.index') }}" class="px-3 py-1.5 rounded-lg hover:bg-pine-50 whitespace-nowrap">الجوامع</a>
                <a href="{{ route('site.home') }}#programs" class="px-3 py-1.5 rounded-lg hover:bg-pine-50 whitespace-nowrap">البرامج</a>
                <a href="{{ route('site.home') }}#contact" class="px-3 py-1.5 rounded-lg hover:bg-pine-50 whitespace-nowrap">تواصل معنا</a>
            </div>
        </nav>
    </header>

    <main class="flex-1">
        @yield('content')
    </main>

    @include('site.partials.donation-modal')

    @if (session('success'))
        <div data-flash class="flash-toast fixed bottom-5 inset-x-4 sm:inset-x-auto sm:right-6 sm:max-w-sm z-[80] bg-white border border-emerald-200 rounded-2xl shadow-2xl overflow-hidden">
            <div class="flex items-start gap-3 px-4 py-3.5">
                <span class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-700 grid place-items-center shrink-0 mt-0.5">
                    <x-icon name="check" class="w-5 h-5" />
                </span>
                <p class="text-sm font-bold text-pine-900 leading-relaxed flex-1">{{ session('success') }}</p>
                <button type="button" data-flash-close aria-label="إغلاق" class="text-gray-400 hover:text-gray-600 transition shrink-0">
                    <x-icon name="x" class="w-4 h-4" />
                </button>
            </div>
            <span data-flash-bar class="flash-toast-bar bg-gradient-to-l from-emerald-500 to-gold-400"></span>
        </div>
    @endif

    <footer class="gradient-sidebar text-white mt-16 relative overflow-hidden">
        <div aria-hidden="true" class="absolute inset-0 sidebar-pattern opacity-70"></div>

        <div class="relative max-w-6xl mx-auto px-4 py-12 grid gap-10 md:grid-cols-3">
            <div>
                <div class="flex items-center gap-2.5">
                    <span class="w-10 h-10 rounded-xl bg-white/10 border border-white/15 grid place-items-center overflow-hidden">
                        <img src="{{ asset('images/logo-mark.png') }}" alt="{{ config('site.name') }}"
                            class="w-6 h-6 object-contain">
                    </span>
                    <span class="font-black text-lg">{{ config('site.name') }}</span>
                </div>
                <p class="text-sm text-emerald-50/70 font-medium leading-relaxed mt-4">
                    {{ config('site.tagline') }} — {{ \Illuminate\Support\Str::limit(config('site.description'), 140) }}
                </p>
            </div>

            <div>
                <h3 class="font-black text-gold-200 mb-4">روابط سريعة</h3>
                <ul class="space-y-2.5 text-sm font-semibold text-emerald-50/85">
                    <li><a href="{{ route('site.home') }}" class="hover:text-gold-200 transition">الرئيسية</a></li>
                    <li><a href="{{ route('site.mosques.index') }}" class="hover:text-gold-200 transition">الجوامع</a></li>
                    <li><a href="{{ route('site.home') }}#programs" class="hover:text-gold-200 transition">البرامج</a></li>
                    <li><a href="{{ route('login') }}" class="hover:text-gold-200 transition">دخول النظام</a></li>
                </ul>
            </div>

            <div>
                <h3 class="font-black text-gold-200 mb-4">{{ config('site.contact_title') }}</h3>
                <ul class="space-y-2.5 text-sm font-semibold text-emerald-50/85">
                    @if (filled(config('site.phone')))
                        <li class="flex items-center gap-2">
                            <x-icon name="profile" class="w-4 h-4 text-gold-300 shrink-0" />
                            <a href="tel:{{ config('site.phone') }}" dir="ltr" class="hover:text-gold-200 transition">{{ config('site.phone') }}</a>
                        </li>
                    @endif
                    @if (filled(config('site.email')))
                        <li class="flex items-center gap-2">
                            <x-icon name="mail" class="w-4 h-4 text-gold-300 shrink-0" />
                            <a href="mailto:{{ config('site.email') }}" dir="ltr" class="hover:text-gold-200 transition">{{ config('site.email') }}</a>
                        </li>
                    @endif
                    @if (filled(config('site.address')))
                        <li class="flex items-start gap-2">
                            <x-icon name="mosque" class="w-4 h-4 text-gold-300 shrink-0 mt-0.5" />
                            <span>{{ config('site.address') }}</span>
                        </li>
                    @endif
                </ul>

                @if ($socials->isNotEmpty())
                    <div class="flex flex-wrap gap-2 mt-5">
                        @foreach ($socials as $key => $url)
                            <a href="{{ $url }}" target="_blank" rel="noopener"
                                class="bg-white/10 hover:bg-white/20 border border-white/15 rounded-xl px-3 py-1.5 text-xs font-bold transition">
                                {{ $socialLabels[$key] ?? $key }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="relative border-t border-white/10">
            <div class="max-w-6xl mx-auto px-4 py-5 text-center text-xs font-semibold text-emerald-100/60">
                جميع الحقوق محفوظة © {{ date('Y') }} — {{ config('site.name') }}
            </div>
        </div>
    </footer>
</body>

</html>
