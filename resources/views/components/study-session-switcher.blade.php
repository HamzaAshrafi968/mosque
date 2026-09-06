@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\StudySession> $studySessions */
    $studySessions = $studySessions ?? \App\Models\StudySession::orderBy('name')->get();
    $currentStudySessionId = config('app.current_study_session_id');
    $currentStudySession = $studySessions->firstWhere('id', $currentStudySessionId);
@endphp

@if($studySessions->isNotEmpty())
    <div id="study-session-switcher" class="relative min-w-0">
        <button
            type="button"
            id="study-session-switcher-button"
            aria-haspopup="listbox"
            aria-expanded="false"
            class="w-full flex items-center gap-2 rounded-lg bg-white/10 hover:bg-white/20 border border-white/20 text-white text-xs sm:text-sm font-medium px-3 py-2 cursor-pointer transition focus:outline-none focus:ring-2 focus:ring-white/40"
        >
            <span class="shrink-0">🕑</span>
            <span class="flex-1 truncate text-right sm:text-left">{{ $currentStudySession?->name ?: 'كل الدوامات' }}</span>
            <span class="shrink-0 text-white/70 transition-transform duration-200" id="study-session-switcher-caret">▾</span>
        </button>

        <form method="POST" action="{{ route('admin.sessions.switch') }}" id="study-session-switcher-form" class="hidden">
            @csrf
            <input type="hidden" name="study_session_id" id="study-session-switcher-input" value="">
        </form>

        <div
            id="study-session-switcher-menu"
            class="hidden absolute top-full left-0 mt-1.5 w-64 sm:w-72 max-w-[calc(100vw-2rem)] bg-white text-gray-800 rounded-xl shadow-2xl border border-gray-200 overflow-hidden z-50"
            role="listbox"
        >
            <div class="px-3 py-2 text-[11px] text-gray-400 border-b border-gray-100 bg-gray-50">
                اختر الدوام لعرض بياناته فقط — أو كل الدوامات
            </div>
            <div class="max-h-72 overflow-y-auto py-1">
                <button
                    type="button"
                    role="option"
                    data-study-session-id=""
                    class="study-session-switch-row w-full flex items-center gap-2 px-3 py-2 text-sm hover:bg-emerald-50 transition"
                >
                    <span class="shrink-0">🕌</span>
                    <span class="flex-1 text-right font-medium {{ ! $currentStudySessionId ? 'text-emerald-700' : '' }}">كل الدوامات</span>
                    @if(! $currentStudySessionId)
                        <span class="text-emerald-600 text-xs">✓ الحالي</span>
                    @endif
                </button>

                <div class="mx-3 my-1 border-t border-gray-100"></div>

                @foreach($studySessions as $studySession)
                    <button
                        type="button"
                        role="option"
                        data-study-session-id="{{ $studySession->id }}"
                        class="study-session-switch-row w-full flex items-center gap-2 px-3 py-2 text-sm hover:bg-emerald-50 transition"
                    >
                        <span class="shrink-0 text-gray-400">🕑</span>
                        <span class="flex-1 text-right {{ $currentStudySessionId && (string) $studySession->id === (string) $currentStudySessionId ? 'text-emerald-700 font-bold' : 'text-gray-700' }}">
                            {{ $studySession->name }}
                        </span>
                        @if($currentStudySessionId && (string) $studySession->id === (string) $currentStudySessionId)
                            <span class="text-emerald-600 text-xs">✓ الحالي</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    <script>
        (function () {
            const root = document.getElementById('study-session-switcher');
            if (! root) {
                return;
            }

            const button = document.getElementById('study-session-switcher-button');
            const menu = document.getElementById('study-session-switcher-menu');
            const caret = document.getElementById('study-session-switcher-caret');
            const input = document.getElementById('study-session-switcher-input');
            const form = document.getElementById('study-session-switcher-form');
            const rows = root.querySelectorAll('.study-session-switch-row');
            const currentStudySessionId = '{{ $currentStudySessionId }}';

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
                    if (row.dataset.studySessionId === currentStudySessionId) {
                        closeMenu();
                        return;
                    }
                    input.value = row.dataset.studySessionId;
                    form.submit();
                });
            });

            document.addEventListener('click', function (event) {
                if (! event.target.closest('#study-session-switcher')) {
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
