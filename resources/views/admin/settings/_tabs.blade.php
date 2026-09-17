@php
    $settingsUser = auth()->user();
    $settingsAuthorization = app(\App\Services\AuthorizationService::class);
    $canQuranSettings = $settingsAuthorization->can($settingsUser, 'quran_settings.view');
    $canUsers = $settingsAuthorization->can($settingsUser, 'users.view');

    $settingsTabs = [
        [
            'label' => 'نظرة عامة',
            'href' => route('admin.settings.index'),
            'active' => request()->routeIs('admin.settings.index'),
            'visible' => true,
        ],
        [
            'label' => 'برنامج القرآن',
            'href' => route('admin.settings.quran.edit'),
            'active' => request()->routeIs('admin.settings.quran.*'),
            'visible' => $canQuranSettings,
        ],
        [
            'label' => 'نقاط المكافآت',
            'href' => route('admin.settings.rewards.edit'),
            'active' => request()->routeIs('admin.settings.rewards.*'),
            'visible' => $canQuranSettings,
        ],
        [
            'label' => 'الحسابات والصلاحيات',
            'href' => route('admin.users.index'),
            'active' => request()->routeIs('admin.users.*'),
            'visible' => $canUsers,
        ],
    ];
@endphp

<nav class="flex flex-wrap gap-2 mb-6" aria-label="أقسام الإعدادات">
    @foreach ($settingsTabs as $tab)
        @continue(! $tab['visible'])
        <a href="{{ $tab['href'] }}"
            class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-bold border transition
                {{ $tab['active']
                    ? 'bg-emerald-700 border-emerald-700 text-white shadow-md shadow-emerald-900/20'
                    : 'bg-white border-gray-200 text-gray-600 hover:border-emerald-300 hover:text-emerald-800' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
