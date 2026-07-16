<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') - إدارة الجوامع</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-emerald-950 min-h-screen flex items-center justify-center font-sans p-4">
<div class="w-full max-w-md">
    <div class="text-center text-white mb-6">
        <div class="text-4xl mb-2">🕌</div>
        <h1 class="text-2xl font-bold">إدارة الجوامع</h1>
    </div>
    <div class="bg-white rounded-2xl shadow-xl p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">@yield('title')</h2>

        @if($errors->any())
            <div class="bg-red-100 border border-red-300 text-red-800 rounded-lg px-4 py-3 mb-4 text-sm">
                <ul class="list-disc pr-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </div>
</div>
</body>
</html>
