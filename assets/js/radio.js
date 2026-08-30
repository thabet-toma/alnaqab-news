document.addEventListener('DOMContentLoaded', () => {
    // 1. Stars Background Generator
    const starsContainer = document.getElementById('stars');
    if (starsContainer) {
        for (let i = 0; i < 150; i++) {
            const star = document.createElement('div');
            star.className = 'star';
            const size = Math.random() * 3;
            star.style.width = `${size}px`;
            star.style.height = `${size}px`;
            star.style.left = `${Math.random() * 100}%`;
            star.style.top = `${Math.random() * 100}%`;
            star.style.animationDuration = `${Math.random() * 3 + 2}s`;
            star.style.animationDelay = `${Math.random() * 2}s`;
            starsContainer.appendChild(star);
        }
    }

    // 2. Audio Player Logic
    const audio = document.getElementById('radioAudio');
    const playBtn = document.getElementById('playBtn');
    const playIcon = playBtn ? playBtn.querySelector('i') : null;
    const statusText = document.getElementById('statusText');
    const equalizer = document.getElementById('equalizer');
    const volumeSlider = document.getElementById('volumeSlider');
    const muteIcon = document.getElementById('muteIcon');
    const onAirBadge = document.getElementById('onAirBadge');
    const onAirText = document.getElementById('onAirText');
    const unmuteOverlay = document.getElementById('unmuteOverlay');
    const unmuteBtn = document.getElementById('unmuteBtn');

    // مفتاح يتذكّر أن المستخدم وافق على الصوت سابقاً، فالزيارة الجاية تبدأ بصوت مباشرة
    const CONSENT_KEY = 'radio:audioConsent';

    let isPlaying = false;
    let isConnecting = false;
    let userStopped = false;   // أوقف المستخدم البث بنفسه؟ عندها لا نعيد الاتصال تلقائياً
    let retryCount = 0;
    let retryTimer = null;

    function hasConsent() {
        try { return localStorage.getItem(CONSENT_KEY) === '1'; } catch (e) { return false; }
    }
    function rememberConsent() {
        try { localStorage.setItem(CONSENT_KEY, '1'); } catch (e) { /* وضع التصفح الخاص */ }
    }

    // فصل الاتصال بالبث فعلياً. ملاحظة: audio.src = '' يجعل بعض المتصفحات
    // تطلب رابط الصفحة نفسها كملف صوت، لذلك نزيل الخاصية بالكامل.
    function detachStream() {
        if (!audio) return;
        audio.pause();
        audio.removeAttribute('src');
        audio.load();
    }

    // البث الحي لا يُخزَّن مؤقتاً، ولا نضيف query string لأن بعض mounts ترفضه
    function attachStream() {
        if (!audio) return;
        if (audio.getAttribute('src') !== RADIO_CONFIG.streamUrl) {
            audio.src = RADIO_CONFIG.streamUrl;
        }
    }

    async function startPlayback({ allowMuted = true } = {}) {
        if (!audio || isConnecting) return false;

        clearTimeout(retryTimer);
        retryTimer = null; // لولا التصفير لاعتبرته scheduleReconnect محاولةً قائمة ولن يعيد الوصل أبداً
        isConnecting = true;
        userStopped = false;
        if (statusText) statusText.textContent = 'جاري الاتصال...';
        if (playIcon) playIcon.className = 'fas fa-spinner fa-spin';

        attachStream();

        try {
            audio.muted = false;
            await audio.play();
            hideUnmuteOverlay();
            rememberConsent();
        } catch (err) {
            // المتصفح منع الصوت. التشغيل المكتوم مسموح دائماً، فنبدأ مكتومين
            // ونطلب من المستخدم ضغطة واحدة لفكّ الكتم.
            if (!allowMuted) {
                isConnecting = false;
                isPlaying = false;
                updateUIState(); // أولاً، ثم نستبدل الرسالة العامة برسالة الخطأ
                if (statusText) statusText.textContent = 'تعذّر تشغيل البث';
                showToast('تعذر تشغيل البث');
                return false;
            }
            try {
                audio.muted = true;
                await audio.play();
                showUnmuteOverlay();
            } catch (err2) {
                // بعض المتصفحات ترفض الوعد رغم أن التشغيل بدأ فعلاً (توفير الطاقة،
                // أو انقطاع مؤقت)، لذلك نتحقق من الحالة الفعلية قبل إعلان الفشل.
                if (!audio.paused) {
                    showUnmuteOverlay();
                } else {
                    console.error('Playback failed', err2);
                    isConnecting = false;
                    isPlaying = false;
                    updateUIState();
                    if (statusText) statusText.textContent = 'اضغط زر التشغيل';
                    return false;
                }
            }
        }

        isConnecting = false;
        isPlaying = true;
        retryCount = 0;
        updateUIState();
        setupMediaSession();
        return true;
    }

    function stopPlayback() {
        clearTimeout(retryTimer);
        retryTimer = null;
        retryCount = 0;
        userStopped = true;
        isConnecting = false;
        isPlaying = false;
        detachStream();
        hideUnmuteOverlay();
        updateUIState();
    }

    function togglePlay() {
        if (isConnecting) return;
        if (isPlaying) stopPlayback();
        else startPlayback({ allowMuted: false }); // ضغطة المستخدم تسمح بالصوت أصلاً
    }

    function updateUIState() {
        if (isPlaying) {
            if (playIcon) playIcon.className = 'fas fa-pause';
            if (statusText) statusText.textContent = audio && audio.muted ? 'البث مكتوم' : 'مباشر الآن';
            if (equalizer) equalizer.classList.add('playing');
            if (onAirText) onAirText.textContent = 'على الهواء';
            if (onAirBadge) onAirBadge.classList.remove('offline');
        } else {
            if (playIcon) playIcon.className = 'fas fa-play';
            if (statusText) statusText.textContent = 'جاهز للبث';
            if (equalizer) equalizer.classList.remove('playing');
            if (onAirText) onAirText.textContent = 'غير متصل';
            if (onAirBadge) onAirBadge.classList.add('offline');
        }
    }

    function showUnmuteOverlay() {
        if (unmuteOverlay) unmuteOverlay.hidden = false;
    }
    function hideUnmuteOverlay() {
        if (unmuteOverlay) unmuteOverlay.hidden = true;
    }

    // البث الحي ينقطع مع تذبذب الشبكة — نعيد الوصل تدريجياً بدل ترك الصفحة صامتة
    function scheduleReconnect() {
        if (userStopped || isConnecting || retryTimer) return;
        retryCount++;
        if (retryCount > 6) {
            isPlaying = false;
            updateUIState();
            if (statusText) statusText.textContent = 'انقطع الاتصال — اضغط للمحاولة';
            return;
        }
        const delay = Math.min(30000, 2000 * Math.pow(2, retryCount - 1));
        if (statusText) statusText.textContent = `انقطع البث — إعادة المحاولة (${retryCount})`;
        retryTimer = setTimeout(() => {
            retryTimer = null;
            detachStream();
            startPlayback({ allowMuted: true });
        }, delay);
    }

    if (audio && playBtn) {
        if (volumeSlider) audio.volume = volumeSlider.value;

        playBtn.addEventListener('click', togglePlay);

        if (unmuteBtn) {
            unmuteBtn.addEventListener('click', () => {
                audio.muted = false;
                if (audio.volume === 0) {
                    audio.volume = 1;
                    if (volumeSlider) volumeSlider.value = 1;
                    if (muteIcon) muteIcon.className = 'fas fa-volume-up';
                }
                rememberConsent();
                hideUnmuteOverlay();
                updateUIState();
            });
        }

        audio.addEventListener('error', () => { if (isPlaying) scheduleReconnect(); });
        audio.addEventListener('stalled', () => { if (isPlaying) scheduleReconnect(); });
        audio.addEventListener('ended', () => { if (isPlaying) scheduleReconnect(); });

        updateUIState();
        autoStart();
    }

    // التشغيل التلقائي عند فتح الصفحة.
    // كل المتصفحات تمنع الصوت التلقائي بلا تفاعل، لكن التشغيل المكتوم مسموح دائماً.
    function autoStart() {
        let policy = null;
        if (typeof navigator.getAutoplayPolicy === 'function') {
            try { policy = navigator.getAutoplayPolicy('mediaelement'); } catch (e) { /* غير مدعوم */ }
        }

        if (policy === 'disallowed' && !hasConsent()) {
            // حتى المكتوم ممنوع — لا نجرّب ونترك الزر للمستخدم
            if (statusText) statusText.textContent = 'اضغط زر التشغيل';
            return;
        }

        startPlayback({ allowMuted: true });
    }

    // Volume Control
    if (volumeSlider && muteIcon && audio) {
        volumeSlider.addEventListener('input', (e) => {
            const val = e.target.value;
            audio.volume = val;
            if (val == 0) {
                muteIcon.className = 'fas fa-volume-mute';
            } else if (val < 0.5) {
                muteIcon.className = 'fas fa-volume-down';
            } else {
                muteIcon.className = 'fas fa-volume-up';
            }
        });

        muteIcon.addEventListener('click', () => {
            if (audio.volume > 0) {
                audio.volume = 0;
                volumeSlider.value = 0;
                muteIcon.className = 'fas fa-volume-mute';
            } else {
                audio.volume = 1;
                volumeSlider.value = 1;
                muteIcon.className = 'fas fa-volume-up';
            }
        });
    }

    // 3. Slideshow Rotation
    const slides = document.querySelectorAll('.slide');
    if (slides.length > 1) {
        let currentSlide = 0;
        setInterval(() => {
            slides[currentSlide].classList.remove('active');
            currentSlide = (currentSlide + 1) % slides.length;
            slides[currentSlide].classList.add('active');
        }, 5000);
    }

    // 4. Ads Rotation
    const ads = document.querySelectorAll('.ad-slide');
    if (ads.length > 1) {
        let currentAd = 0;
        setInterval(() => {
            ads[currentAd].classList.remove('active');
            currentAd = (currentAd + 1) % ads.length;
            ads[currentAd].classList.add('active');
        }, 8000);
    }

    // 5. Sleep Timer
    const sleepTimerBtn = document.getElementById('sleepTimerBtn');
    const sleepModal = document.getElementById('sleepTimerModal');
    const closeSleepModal = document.getElementById('closeSleepModal');
    const sleepTimerDisplay = document.getElementById('sleepTimerDisplay');
    const sleepTimeRemaining = document.getElementById('sleepTimeRemaining');
    const cancelSleepTimer = document.getElementById('cancelSleepTimer');
    let sleepInterval;
    let sleepEndTime;

    if (sleepTimerBtn && sleepModal) {
        sleepTimerBtn.addEventListener('click', () => sleepModal.classList.add('show'));
        if(closeSleepModal) closeSleepModal.addEventListener('click', () => sleepModal.classList.remove('show'));
        
        document.querySelectorAll('.timer-options button').forEach(btn => {
            btn.addEventListener('click', () => {
                const mins = parseInt(btn.dataset.mins);
                startSleepTimer(mins);
                sleepModal.classList.remove('show');
            });
        });

        if(cancelSleepTimer) cancelSleepTimer.addEventListener('click', stopSleepTimer);
    }

    function startSleepTimer(minutes) {
        clearInterval(sleepInterval);
        sleepEndTime = Date.now() + minutes * 60000;
        if(sleepTimerDisplay) sleepTimerDisplay.style.display = 'inline-flex';
        if(sleepTimerBtn) sleepTimerBtn.classList.add('active');
        showToast(`تم تعيين المؤقت لـ ${minutes} دقيقة`);
        
        updateTimerDisplay();
        sleepInterval = setInterval(updateTimerDisplay, 1000);
    }

    function updateTimerDisplay() {
        const now = Date.now();
        const diff = sleepEndTime - now;
        
        if (diff <= 0) {
            stopSleepTimer();
            if (isPlaying) stopPlayback(); // يوقف البث ويمنع إعادة الاتصال التلقائي
            showToast('انتهى مؤقت النوم');
            return;
        }
        
        const m = Math.floor(diff / 60000);
        const s = Math.floor((diff % 60000) / 1000);
        if(sleepTimeRemaining) sleepTimeRemaining.textContent = `${m}:${s < 10 ? '0' : ''}${s}`;
    }

    function stopSleepTimer() {
        clearInterval(sleepInterval);
        if(sleepTimerDisplay) sleepTimerDisplay.style.display = 'none';
        if(sleepTimerBtn) sleepTimerBtn.classList.remove('active');
    }

    // 6. Share Functionality
    const shareBtn = document.getElementById('shareBtn');
    if (shareBtn) {
        shareBtn.addEventListener('click', async () => {
            const shareData = {
                title: RADIO_CONFIG.stationName,
                text: 'استمع إلى البث المباشر',
                url: window.location.href
            };
            
            if (navigator.share) {
                try {
                    await navigator.share(shareData);
                } catch (err) {
                    console.log('Share canceled');
                }
            } else {
                navigator.clipboard.writeText(window.location.href);
                showToast('تم نسخ الرابط');
            }
        });
    }

    // 7. Toast Notifications
    const toast = document.getElementById('toast');
    let toastTimeout;
    function showToast(msg) {
        if(!toast) return;
        clearTimeout(toastTimeout);
        toast.textContent = msg;
        toast.classList.add('show');
        toastTimeout = setTimeout(() => toast.classList.remove('show'), 3000);
    }

    // 8. Admin Panel Logic
    const adminToggle = document.getElementById('adminToggle');
    const adminPanel = document.getElementById('adminPanel');
    const adminOverlay = document.getElementById('adminOverlay');
    const closeAdmin = document.getElementById('closeAdmin');
    const adminForm = document.getElementById('radioSettingsForm');

    if (adminToggle && adminPanel && adminOverlay) {
        const openAdmin = () => { adminPanel.classList.add('open'); adminOverlay.classList.add('show'); };
        const closeAdminPanel = () => { adminPanel.classList.remove('open'); adminOverlay.classList.remove('show'); };
        
        adminToggle.addEventListener('click', openAdmin);
        if(closeAdmin) closeAdmin.addEventListener('click', closeAdminPanel);
        adminOverlay.addEventListener('click', closeAdminPanel);

        if (adminForm) {
            adminForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const data = {
                    station_name: document.getElementById('stationName').value,
                    tagline: document.getElementById('tagline').value,
                    stream_url: document.getElementById('streamUrl').value,
                    description: document.getElementById('description').value,
                    csrf_token: document.getElementById('csrfToken').value
                };
                
                const btn = adminForm.querySelector('button');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...';
                btn.disabled = true;

                try {
                    const res = await fetch(RADIO_CONFIG.apiUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                    const result = await res.json();
                    if (result.success) {
                        showToast('تم حفظ الإعدادات بنجاح');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showToast(result.error || 'حدث خطأ');
                    }
                } catch (err) {
                    showToast('خطأ في الاتصال');
                }
                
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
    }

    // 9. MediaSession API — يتحكم بالبث من شاشة القفل وسماعات البلوتوث
    let currentTrack = '';

    function setupMediaSession() {
        if (!('mediaSession' in navigator)) return;

        // نستخدم أول صورة من صور المحطة. لا نضع مساراً ثابتاً لملف قد لا يوجد،
        // لأن ذلك يسبب طلباً فاشلاً (404) مع كل تحديث للبيانات.
        const meta = {
            title: currentTrack || RADIO_CONFIG.stationName,
            artist: currentTrack ? RADIO_CONFIG.stationName : 'البث المباشر'
        };
        if (RADIO_CONFIG.artwork) {
            meta.artwork = [{ src: RADIO_CONFIG.artwork, sizes: '512x512' }];
        }
        navigator.mediaSession.metadata = new MediaMetadata(meta);

        navigator.mediaSession.setActionHandler('play', togglePlay);
        navigator.mediaSession.setActionHandler('pause', togglePlay);
    }

    // 10. "شو شغّال هلق" — عنوان المقطع، موقعه بالبلاي ليست، والزمن المتبقّي
    const nowPlayingBadge = document.getElementById('nowPlayingBadge');
    const nowPlayingText = document.getElementById('nowPlayingText');
    const nowPlayingPosition = document.getElementById('nowPlayingPosition');
    const nowPlayingRemaining = document.getElementById('nowPlayingRemaining');
    const nowPlayingLive = document.getElementById('nowPlayingLive');

    let remainingSeconds = null;
    let remainingTimer = null;

    function formatRemaining(seconds) {
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return `${m}:${s < 10 ? '0' : ''}${s}`;
    }

    function renderRemaining() {
        if (!nowPlayingRemaining) return;
        if (remainingSeconds === null) {
            nowPlayingRemaining.hidden = true;
            return;
        }
        nowPlayingRemaining.textContent = formatRemaining(remainingSeconds);
        nowPlayingRemaining.hidden = false;
    }

    function stopRemainingTimer() {
        if (remainingTimer) {
            clearInterval(remainingTimer);
            remainingTimer = null;
        }
    }

    // يضبط الزمن المتبقّي من قيمة السيرفر (تصحّح الانزياح) ثم يُنقص ثانية
    // كل ثانية أمام المستمع بين استطلاعين، بمؤقّت واحد لا يُعاد إنشاؤه في كل استجابة
    function applyRemaining(seconds) {
        if (typeof seconds !== 'number' || seconds < 0) {
            remainingSeconds = null;
            stopRemainingTimer();
            renderRemaining();
            return;
        }
        remainingSeconds = seconds;
        renderRemaining();
        if (!remainingTimer) {
            remainingTimer = setInterval(() => {
                if (remainingSeconds === null) return;
                remainingSeconds = Math.max(0, remainingSeconds - 1);
                renderRemaining();
            }, 1000);
        }
    }

    function applyNowPlaying(state) {
        const title = (state.title || '').trim();
        const titleChanged = title !== currentTrack;
        currentTrack = title;

        if (nowPlayingBadge && nowPlayingText) {
            nowPlayingText.textContent = title;
            nowPlayingBadge.hidden = title === '';
        }

        if (nowPlayingLive) nowPlayingLive.hidden = !state.live;

        if (state.live) {
            // البثّ المباشر يجعل الموقع داخل البلاي ليست بلا معنى
            if (nowPlayingPosition) nowPlayingPosition.hidden = true;
            applyRemaining(null);
        } else {
            if (nowPlayingPosition) {
                if (state.position != null && state.total != null) {
                    nowPlayingPosition.textContent = `${state.position}/${state.total}`;
                    nowPlayingPosition.hidden = false;
                } else {
                    nowPlayingPosition.hidden = true;
                }
            }
            applyRemaining(state.remaining);
        }

        if (titleChanged && isPlaying) setupMediaSession();
    }

    // مصدر البث الذاتي (Icecast على نفس السيرفر): نسأل نقطة PHP عندنا،
    // لأن Icecast مربوط على 127.0.0.1 ولا يمكن للمتصفح قراءته مباشرة.
    function initNowPlaying() {
        if (!RADIO_CONFIG.nowPlayingUrl) return;

        const poll = async () => {
            try {
                const res = await fetch(RADIO_CONFIG.nowPlayingUrl, { cache: 'no-store' });
                if (!res.ok) return;
                const data = await res.json();
                applyNowPlaying(data);
            } catch (e) {
                // انقطاع مؤقت — نترك الحالة السابقة ونحاول لاحقاً
            }
        };

        poll();
        setInterval(poll, 15000);
    }

    initNowPlaying();
});
