<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') | إدارة الجوامع</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&family=Amiri:ital,wght@0,400;0,700;1,400&family=Scheherazade+New:wght@400;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="min-h-screen font-sans p-4 relative overflow-hidden">
    {{-- الخلفية العميقة --}}
    <div class="absolute inset-0 gradient-sidebar"></div>

    {{-- زخرفة إسلامية --}}
    <div class="absolute inset-0 sidebar-pattern"></div>

    {{-- كرات ضوئية متحركة --}}
    <div aria-hidden="true" class="animate-blob absolute -top-24 start-[-10%] w-[30rem] h-[30rem] rounded-full bg-gold-400/15 blur-3xl"></div>
    <div aria-hidden="true" class="animate-blob absolute bottom-[-20%] end-[-8%] w-[32rem] h-[32rem] rounded-full bg-emerald-300/15 blur-3xl" style="animation-delay:-8s"></div>
    <div aria-hidden="true" class="animate-blob absolute top-1/3 start-1/3 w-80 h-80 rounded-full bg-teal-200/10 blur-3xl" style="animation-delay:-4s"></div>

    {{-- هالة أعلى المركز خلف البطاقة --}}
    <div aria-hidden="true" class="absolute top-[16%] start-1/2 -translate-x-1/2 w-[560px] h-[300px] rounded-full bg-gold-300/10 blur-[100px] pointer-events-none"></div>

    <div class="w-full max-w-md relative z-10 animate-fade-in-up">
        <div class="text-center mb-8 animate-fade-in-up" style="animation-delay:.1s">
            <div class="relative inline-flex items-center justify-center">
                <span class="absolute inset-0 rounded-3xl bg-gold-300/20 blur-xl animate-glow-ring"></span>
                <div class="relative w-20 h-20 rounded-3xl p-[2px] bg-gradient-to-br from-gold-200 via-gold-400 to-gold-700 shadow-2xl shadow-gold-950/40">
                    <div class="w-full h-full rounded-[22px] bg-pine-900/90 backdrop-blur grid place-items-center text-gold-300">
                        <x-icon name="mosque" class="w-10 h-10" />
                    </div>
                </div>
            </div>
            <h1 class="text-3xl sm:text-4xl font-black mt-5 text-white">
                إدارة <span class="gold-text">الجوامع</span>
            </h1>
            <p class="text-sm mt-2 text-emerald-100/70 font-medium">نظام إدارة المساجد وحلقات القرآن</p>
        </div>

        <div class="glass-card rounded-[28px] shadow-[0_30px_70px_-24px_rgba(0,0,0,0.55)] border border-white/50 p-8 sm:p-10 animate-fade-in-up" style="animation-delay:.22s">
            <div class="ornament-top"></div>
            <h2 class="text-xl font-extrabold text-pine-950 mb-7 text-center">@yield('title')</h2>

            @if($errors->any())
                <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-800 rounded-2xl px-4 py-3.5 mb-6 text-sm animate-scale-in">
                    <x-icon name="alert" class="w-5 h-5 shrink-0 mt-0.5 text-red-500" />
                    <ul class="list-disc pr-4 space-y-1 font-semibold">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </div>

        <p class="text-center text-emerald-100/50 text-xs mt-7 animate-fade-in-up" style="animation-delay:.34s">
            جميع الحقوق محفوظة © {{ date('Y') }} — نظام إدارة الجوامع
        </p>
    </div>
</body>
</html>
