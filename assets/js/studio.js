/**
 * شاشة الاستوديو: الصوت، الشارة، السطر السفلي (من يتكلم أو ما يُشغَّل)،
 * تدوير صور المحطة، والعرض المرئي عبر LiveVisual.
 */
document.addEventListener('DOMContentLoaded', () => {
    const audio = document.getElementById('studioAudio');
    const unmute = document.getElementById('studioUnmute');
    const badge = document.getElementById('studioBadge');
    const badgeText = document.getElementById('studioBadgeText');
    const lowerLabel = document.getElementById('studioLowerLabel');
    const lowerText = document.getElementById('studioLowerText');

    /* ===== الصوت ===== */
    // OBS يسمح بالتشغيل التلقائي. في متصفح عادي (للتجربة) قد يُمنع فنعرض زراً.
    function tryPlay() {
        audio.play().then(() => { unmute.hidden = true; }).catch(() => { unmute.hidden = false; });
    }
    unmute.addEventListener('click', tryPlay);
    // انقطاع البث (إعادة تشغيل المحرّك مثلاً) ⇒ نعيد الاتصال بعد لحظات
    audio.addEventListener('error', () => setTimeout(() => { audio.load(); tryPlay(); }, 3000));
    audio.addEventListener('ended', () => setTimeout(() => { audio.load(); tryPlay(); }, 3000));
    tryPlay();

    /* ===== تدوير صور المحطة ===== */
    const slides = document.querySelectorAll('.studio-slide');
    if (slides.length > 1) {
        let i = 0;
        setInterval(() => {
            slides[i].classList.remove('is-active');
            i = (i + 1) % slides.length;
            slides[i].classList.add('is-active');
        }, 12000);
    }

    /* ===== السطر السفلي ===== */
    let trackTitle = '';
    let onAirNames = null; // null = لا أحد على الهواء

    function renderLower() {
        const live = onAirNames !== null;
        badge.classList.toggle('is-live', live);
        badgeText.textContent = live ? 'مباشر' : 'راديو';
        if (live) {
            lowerLabel.textContent = 'على الهواء';
            lowerText.textContent = onAirNames.length ? onAirNames.join(' · ') : 'بث مباشر';
        } else {
            lowerLabel.textContent = 'الآن';
            lowerText.textContent = trackTitle || STUDIO_SCREEN.tagline || '';
        }
    }

    async function pollNowPlaying() {
        try {
            const res = await fetch(STUDIO_SCREEN.nowPlayingUrl, { cache: 'no-store' });
            const data = await res.json();
            trackTitle = data.live ? '' : (data.title || '');
            renderLower();
        } catch (e) { /* المحاولة التالية */ }
    }
    pollNowPlaying();
    setInterval(pollNowPlaying, 15000);

    /* ===== العرض المرئي + من على الهواء ===== */
    let lastNamesKey = '';
    LiveVisual.attach(document.getElementById('studioVisual'), {
        stateUrl: STUDIO_SCREEN.stateUrl,
        pollMs: 2000,
        onState: (state) => {
            const list = Array.isArray(state.on_air) ? state.on_air : [];
            const names = list.length ? list.map((s) => s.name).filter(Boolean) : null;
            const key = JSON.stringify(names);
            if (key === lastNamesKey) return;
            lastNamesKey = key;
            // الأسماء تتبع الصوت: تتغيّر حين يُسمع التغيير لا قبله
            setTimeout(() => { onAirNames = names; renderLower(); }, (Number(state.delay) || 0) * 1000);
        },
    });

    renderLower();
});
