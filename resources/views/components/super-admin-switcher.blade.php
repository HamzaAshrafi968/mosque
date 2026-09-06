@php
    $currentUser = auth()->user();
    $currentMosqueId = $currentUser?->isSuperAdmin() ? session('super_admin_mosque_id') : null;
    $mosques = $currentUser?->isSuperAdmin() ? App\Models\Tenant::orderBy('name')->get() : collect();
    $currentMosque = $mosques->firstWhere('id', $currentMosqueId);
@endphp

@if($currentUser?->isSuperAdmin())
    <div class="gradient-sidebar relative text-white shadow-[0_14px_34px_-16px_rgba(5,32,25,0.65)] sticky top-0 z-40 overflow-hidden">
        <div aria-hidden="true" class="topbar-sheen pointer-events-none absolute inset-0 opacity-20"></div>
        <div class="relative max-w-screen-2xl mx-auto px-3 sm:px-6 py-2.5 flex items-center gap-2.5 sm:gap-3">
            <button
                type="button"
                id="sidebar-toggle"
                class="lg:hidden p-2 -me-1 rounded-xl bg-white/10 hover:bg-white/20 active:scale-95 transition"
                aria-label="القائمة"
            >
                <x-icon name="menu" class="w-5 h-5" />
            </button>

            <div class="flex items-center gap-2.5 font-bold shrink-0 min-w-0">
                <span class="w-10 h-10 rounded-xl p-[1.5px] bg-gradient-to-br from-gold-200 via-gold-400 to-gold-600 shadow-lg shadow-gold-950/20 shrink-0">
                    <span class="w-full h-full rounded-[10px] bg-pine-900/90 grid place-items-center text-gold-300">
                        <x-icon name="globe" class="w-5 h-5" />
                    </span>
                </span>
                <span class="text-sm sm:text-base font-extrabold leading-tight">مدير الجوامع — الإدارة المركزية</span>
            </div>

            @if($currentMosque)
                <span class="hidden md:inline-flex items-center gap-1.5 rounded-full border border-gold-300/30 bg-gold-400/15 px-3 py-1 text-[11px] font-bold text-gold-100 whitespace-nowrap animate-scale-in">
                    <x-icon name="check" class="w-3.5 h-3.5 text-gold-300" />
                    صلاحيات مدير الجامع الكاملة
                </span>
            @else
                <span class="hidden md:inline-flex items-center gap-1.5 rounded-full bg-white/[0.07] border border-white/10 px-3 py-1 text-[11px] font-semibold text-emerald-100/90 whitespace-nowrap">
                    لوحة مجمّعة لكل الجوامع
                </span>
            @endif

            <div class="ms-auto flex items-center gap-2 min-w-0">
                @if($currentMosque)
                    <x-study-session-switcher />
                @endif
                <div id="mosque-switcher" class="relative min-w-0">
                    <button
                        type="button"
                        id="mosque-switcher-button"
                        aria-haspopup="listbox"
                        aria-expanded="false"
                        class="w-full flex items-center gap-2 rounded-xl bg-white/10 hover:bg-white/20 border border-white/15 text-white text-xs sm:text-sm font-semibold px-3 py-2 cursor-pointer transition focus:outline-none focus:ring-2 focus:ring-gold-300/60"
                    >
                        <span class="shrink-0 text-gold-300">
                            <x-icon :name="$currentMosque ? 'building' : 'mosque'" class="w-4 h-4" />
                        </span>
                        <span class="flex-1 truncate text-right sm:text-left">{{ $currentMosque?->name ?: 'كل الجوامع — تبديل الجامع' }}</span>
                        <span class="shrink-0 text-gold-200/80 transition-transform duration-200" id="mosque-switcher-caret"><x-icon name="chevron" class="w-4 h-4" /></span>
                    </button>

                    <form method="POST" action="{{ route('super-admin.switch-mosque') }}" id="mosque-switcher-form" class="hidden">
                        @csrf
                        <input type="hidden" name="mosque_id" id="mosque-switcher-input" value="">
                    </form>

                    <div
                        id="mosque-switcher-menu"
                        class="hidden absolute top-full left-0 mt-2 w-64 sm:w-72 max-w-[calc(100vw-2rem)] bg-white/95 backdrop-blur-xl text-gray-800 rounded-2xl shadow-2xl shadow-pine-950/40 border border-gold-200/50 overflow-hidden z-50 animate-scale-in"
                        role="listbox"
                    >
                        <div class="px-4 py-2.5 text-[11px] text-gray-500 border-b border-gray-100 bg-gradient-to-l from-gold-50 to-white font-semibold">
                            اختر جامعاً لفتح لوحة إدارته الكاملة — أو عد للوضع المركزي
                        </div>
                        <div class="max-h-72 overflow-y-auto py-1.5">
                            <button
                                type="button"
                                role="option"
                                data-mosque-id=""
                                class="mosque-switch-row w-full flex items-center gap-2.5 px-3 py-2.5 text-sm hover:bg-gold-50 transition rounded-lg"
                            >
                                <span class="shrink-0 text-gold-600"><x-icon name="mosque" class="w-4 h-4" /></span>
                                <span class="flex-1 text-right font-semibold {{ ! $currentMosqueId ? 'text-pine-800' : 'text-gray-700' }}">كل الجوامع — إدارة الجوامع</span>
                                @if(! $currentMosqueId)
                                    <span class="text-emerald-600 text-[11px] font-bold">✓ الحالي</span>
                                @endif
                            </button>

                            <div class="mx-3 my-1.5 border-t border-gray-100"></div>

                            @foreach($mosques as $mosque)
                                <button
                                    type="button"
                                    role="option"
                                    data-mosque-id="{{ $mosque->id }}"
                                    class="mosque-switch-row w-full flex items-center gap-2.5 px-3 py-2.5 text-sm hover:bg-gold-50 transition rounded-lg"
                                >
                                    <span class="shrink-0 text-gray-400"><x-icon name="building" class="w-4 h-4" /></span>
                                    <span class="flex-1 text-right {{ $currentMosque && (string) $mosque->id === (string) $currentMosque->id ? 'text-pine-800 font-bold' : 'text-gray-700' }}">
                                        {{ $mosque->name }}
                                    </span>
                                    @if($currentMosque && (string) $mosque->id === (string) $currentMosque->id)
                                        <span class="text-emerald-600 text-[11px] font-bold">✓ الحالي</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="absolute inset-x-0 bottom-0 gold-hairline"></div>
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
