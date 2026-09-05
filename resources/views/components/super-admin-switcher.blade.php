@php
    $currentUser = auth()->user();
    $currentMosqueId = $currentUser?->isSuperAdmin() ? session('super_admin_mosque_id') : null;
    $mosques = $currentUser?->isSuperAdmin() ? App\Models\Tenant::orderBy('name')->get() : collect();
    $currentMosque = $mosques->firstWhere('id', $currentMosqueId);
@endphp

@if($currentUser?->isSuperAdmin())
    <div class="gradient-sidebar text-white shadow-lg sticky top-0 z-40">
        <div class="max-w-screen-2xl mx-auto px-3 sm:px-6 py-2 flex items-center gap-2 sm:gap-3">
            <button
                type="button"
                id="sidebar-toggle"
                class="lg:hidden p-2 -me-1 rounded-lg bg-white/10 hover:bg-white/20 transition"
                aria-label="القائمة"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            <div class="flex items-center gap-2 font-bold shrink-0 min-w-0">
                <span class="text-lg leading-none">🕌</span>
                <span class="text-xs sm:text-sm leading-tight">إدارة الجوامع</span>
            </div>

            @if($currentMosque)
                <span class="hidden sm:inline-flex bg-white/15 rounded-full px-3 py-1 text-[11px] font-medium whitespace-nowrap">✔ صلاحيات مدير الجامع الكاملة</span>
            @else
                <span class="hidden sm:inline-flex bg-white/10 text-emerald-100/90 rounded-full px-3 py-1 text-[11px] whitespace-nowrap">لوحة مجمّعة لكل الجوامع</span>
            @endif

            <div id="mosque-switcher" class="ms-auto relative min-w-0">
                <button
                    type="button"
                    id="mosque-switcher-button"
                    aria-haspopup="listbox"
                    aria-expanded="false"
                    class="w-full flex items-center gap-2 rounded-lg bg-white/10 hover:bg-white/20 border border-white/20 text-white text-xs sm:text-sm font-medium px-3 py-2 cursor-pointer transition focus:outline-none focus:ring-2 focus:ring-white/40"
                >
                    <span class="shrink-0">{{ $currentMosque ? '🏛️' : '🕌' }}</span>
                    <span class="flex-1 truncate text-right sm:text-left">{{ $currentMosque?->name ?: 'كل الجوامع — تبديل الجامع' }}</span>
                    <span class="shrink-0 text-white/70 transition-transform duration-200" id="mosque-switcher-caret">▾</span>
                </button>

                <form method="POST" action="{{ route('super-admin.switch-mosque') }}" id="mosque-switcher-form" class="hidden">
                    @csrf
                    <input type="hidden" name="mosque_id" id="mosque-switcher-input" value="">
                </form>

                <div
                    id="mosque-switcher-menu"
                    class="hidden absolute top-full left-0 mt-1.5 w-64 sm:w-72 max-w-[calc(100vw-2rem)] bg-white text-gray-800 rounded-xl shadow-2xl border border-gray-200 overflow-hidden z-50"
                    role="listbox"
                >
                    <div class="px-3 py-2 text-[11px] text-gray-400 border-b border-gray-100 bg-gray-50">
                        اختر جامعاً لفتح لوحة إدارته الكاملة — أو عد للوضع المركزي
                    </div>
                    <div class="max-h-72 overflow-y-auto py-1">
                        <button
                            type="button"
                            role="option"
                            data-mosque-id=""
                            class="mosque-switch-row w-full flex items-center gap-2 px-3 py-2 text-sm hover:bg-emerald-50 transition"
                        >
                            <span class="shrink-0">🕌</span>
                            <span class="flex-1 text-right font-medium {{ ! $currentMosqueId ? 'text-emerald-700' : '' }}">كل الجوامع — إدارة الجوامع</span>
                            @if(! $currentMosqueId)
                                <span class="text-emerald-600 text-xs">✓ الحالي</span>
                            @endif
                        </button>

                        <div class="mx-3 my-1 border-t border-gray-100"></div>

                        @foreach($mosques as $mosque)
                            <button
                                type="button"
                                role="option"
                                data-mosque-id="{{ $mosque->id }}"
                                class="mosque-switch-row w-full flex items-center gap-2 px-3 py-2 text-sm hover:bg-emerald-50 transition"
                            >
                                <span class="shrink-0 text-gray-400">🏛️</span>
                                <span class="flex-1 text-right {{ $currentMosque && (string) $mosque->id === (string) $currentMosque->id ? 'text-emerald-700 font-bold' : 'text-gray-700' }}">
                                    {{ $mosque->name }}
                                </span>
                                @if($currentMosque && (string) $mosque->id === (string) $currentMosque->id)
                                    <span class="text-emerald-600 text-xs">✓ الحالي</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const root = document.getElementById('mosque-switcher');
            if (! root) {
                return;
            }

            const button = document.getElementById('mosque-switcher-button');
            const menu = document.getElementById('mosque-switcher-menu');
            const caret = document.getElementById('mosque-switcher-caret');
            const input = document.getElementById('mosque-switcher-input');
            const form = document.getElementById('mosque-switcher-form');
            const rows = root.querySelectorAll('.mosque-switch-row');
            const currentMosqueId = '{{ $currentMosqueId }}';

            function closeMenu() {
                menu.classList.add('hidden');
                button.setAttribute('aria-expanded', 'false');
                caret.style.transform = 'rotate(0deg)';
            }

            button.addEventListener('click', function (event) {
                event.stopPropagation();
                if (menu.classList.contains('hidden')) {
                    menu.classList.remove('hidden');
                    button.setAttribute('aria-expanded', 'true');
                    caret.style.transform = 'rotate(180deg)';
                } else {
                    closeMenu();
                }
            });

            rows.forEach(function (row) {
                row.addEventListener('click', function () {
                    if (row.dataset.mosqueId === currentMosqueId) {
                        closeMenu();
                        return;
                    }
                    input.value = row.dataset.mosqueId;
                    form.submit();
                });
            });

            document.addEventListener('click', function (event) {
                if (! event.target.closest('#mosque-switcher')) {
                    closeMenu();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeMenu();
                }
            });
        })();
    </script>
@endif
