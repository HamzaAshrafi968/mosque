<div data-listening-player data-csrf="{{ csrf_token() }}" hidden
    class="sticky top-2 z-30 rounded-2xl border border-emerald-200 bg-white/95 backdrop-blur shadow-lg p-4 space-y-3">
    <div class="flex flex-wrap items-center gap-2">
        <span class="w-9 h-9 grid place-items-center rounded-xl bg-emerald-700 text-white shrink-0">
            <x-icon name="quran" class="w-5 h-5" />
        </span>
        <div class="flex-1 min-w-48">
            <audio data-player-audio controls preload="none" class="w-full"></audio>
        </div>
        @if(($reciters ?? []) !== [])
            <select data-player-reciter class="border border-gray-300 rounded-lg px-2 py-2 text-xs font-bold text-gray-700">
                @foreach($reciters as $code => $label)
                    <option value="{{ $code }}">{{ $label }}</option>
                @endforeach
            </select>
        @endif
        <button type="button" data-player-prev
            class="px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-bold">السابق</button>
        <button type="button" data-player-next
            class="px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-bold">التالي</button>
        <button type="button" data-player-close
            class="px-3 py-2 rounded-lg bg-red-50 hover:bg-red-100 text-red-700 text-sm font-bold">إغلاق</button>
    </div>
    <div data-player-meta class="text-xs text-gray-500 font-semibold"></div>
    <div data-player-text class="quran-font text-xl leading-[2.4] text-pine-950 text-justify min-h-14"></div>
</div>

@once
    @push('scripts')
        <script>
            (function () {
                const player = document.querySelector('[data-listening-player]');

                if (!player) {
                    return;
                }

                const audio = player.querySelector('[data-player-audio]');
                const meta = player.querySelector('[data-player-meta]');
                const text = player.querySelector('[data-player-text]');
                const reciter = player.querySelector('[data-player-reciter]');
                const csrf = player.dataset.csrf;
                let tracks = [];
                let index = 0;
                let progressUrl = null;
                let itemLabel = '';

                function render() {
                    const track = tracks[index];

                    if (!track) {
                        return;
                    }

                    audio.src = track.url;
                    meta.textContent = [
                        itemLabel,
                        track.surah_name + ' — آية ' + track.ayah,
                        'صفحة ' + track.page,
                        (index + 1) + ' / ' + tracks.length,
                    ].filter(Boolean).join(' • ');
                    text.textContent = track.text;
                }

                function play() {
                    audio.play().catch(function () {
                        meta.textContent = 'تعذّر تشغيل التلاوة — تحقق من الاتصال بالإنترنت';
                    });
                }

                function next() {
                    if (index < tracks.length - 1) {
                        index++;
                        render();
                        play();
                    }
                }

                function prev() {
                    if (index > 0) {
                        index--;
                        render();
                        play();
                    }
                }

                function reportProgress() {
                    if (!progressUrl) {
                        return;
                    }

                    const seconds = Math.round(audio.duration);

                    if (!isFinite(seconds) || seconds <= 0) {
                        return;
                    }

                    fetch(progressUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({ seconds: seconds }),
                    }).catch(function () {});
                }

                audio.addEventListener('ended', function () {
                    reportProgress();
                    next();
                });

                player.querySelector('[data-player-next]').addEventListener('click', next);
                player.querySelector('[data-player-prev]').addEventListener('click', prev);
                player.querySelector('[data-player-close]').addEventListener('click', function () {
                    audio.pause();
                    player.hidden = true;
                });

                document.querySelectorAll('[data-player-start]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        document.querySelectorAll('[data-player-start][data-active]').forEach(function (active) {
                            active.removeAttribute('data-active');
                        });
                        button.setAttribute('data-active', '1');
                        progressUrl = button.dataset.progressUrl || null;
                        itemLabel = button.dataset.itemLabel || '';
                        player.hidden = false;
                        meta.textContent = 'جارٍ تحميل قائمة التشغيل…';
                        text.textContent = '';

                        let audioUrl = button.dataset.audioUrl;

                        if (reciter && reciter.value) {
                            audioUrl += (audioUrl.indexOf('?') === -1 ? '?' : '&') + 'reciter=' + encodeURIComponent(reciter.value);
                        }

                        fetch(audioUrl, { headers: { 'Accept': 'application/json' } })
                            .then(function (response) { return response.json(); })
                            .then(function (data) {
                                tracks = data.tracks || [];
                                index = 0;

                                if (!tracks.length) {
                                    meta.textContent = 'لا توجد آيات في هذا النطاق — تأكد من تهيئة صفحات المصحف';
                                    return;
                                }

                                render();
                                play();
                            })
                            .catch(function () {
                                meta.textContent = 'تعذّر تحميل قائمة التشغيل';
                            });
                    });
                });
            })();
        </script>
    @endpush
@endonce
