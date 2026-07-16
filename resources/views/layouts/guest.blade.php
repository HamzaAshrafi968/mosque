<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') | إدارة الجوامع</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Scheherazade+New:wght@400;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="min-h-screen flex items-center justify-center font-sans p-4 relative overflow-hidden">
    <div class="absolute inset-0 gradient-sidebar"></div>
    <div class="absolute inset-0 opacity-10" style="background-image: url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><defs><pattern id=%22p%22 width=%22100%22 height=%22100%22 patternUnits=%22userSpaceOnUse%22><path d=%22M0 50 Q25 0 50 50 T100 50%22 fill=%22none%22 stroke=%22white%22 stroke-width=%220.4%22 opacity=%220.5%22/></pattern></defs><rect width=%22100%22 height=%22100%22 fill=%22url(%23p)%22/></svg>')"></div>

    <div class="w-full max-w-md relative z-10 animate-scale-in">
        <div class="text-center mb-8">
            <div class="w-20 h-20 bg-white/15 backdrop-blur-sm rounded-2xl flex items-center justify-center text-4xl mx-auto mb-4 shadow-lg border border-white/10">
                🕌
            </div>
            <h1 class="text-3xl font-bold text-white">إدارة الجوامع</h1>
            <p class="text-emerald-200/80 text-sm mt-1">نظام إدارة المساجد وحلقات القرآن</p>
        </div>

        <div class="bg-white/95 backdrop-blur-sm rounded-3xl shadow-2xl p-8 border border-white/20">
            <div class="ornament-top mb-1"></div>
            <h2 class="text-xl font-bold text-gray-800 mb-6 text-center">@yield('title')</h2>

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 mb-5 text-sm animate-scale-in">
                    <ul class="list-disc pr-5 space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </div>

        <p class="text-center text-emerald-200/60 text-xs mt-6">جميع الحقوق محفوظة © {{ date('Y') }}</p>
    </div>
</body>
</html>
