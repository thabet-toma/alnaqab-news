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
    let isPlaying = false;
    let isConnecting = false;

    if (audio && playBtn) {
        audio.src = RADIO_CONFIG.streamUrl;
        if(volumeSlider) audio.volume = volumeSlider.value;

        function togglePlay() {
            if (isConnecting) return;

            if (isPlaying) {
                audio.pause();
                audio.src = ''; // Force close connection
                isPlaying = false;
                updateUIState();
            } else {
                isConnecting = true;
                if(statusText) statusText.textContent = 'جاري الاتصال...';
                if(playIcon) playIcon.className = 'fas fa-spinner fa-spin';
                
                audio.src = RADIO_CONFIG.streamUrl + '?t=' + new Date().getTime(); // Prevent caching
                const playPromise = audio.play();
                
                if (playPromise !== undefined) {
                    playPromise.then(() => {
                        isPlaying = true;
                        isConnecting = false;
                        updateUIState();
                        setupMediaSession();
                    }).catch(error => {
                        console.error("Playback failed", error);
                        isConnecting = false;
                        isPlaying = false;
                        if(statusText) statusText.textContent = 'خطأ في الاتصال بالبث';
                        updateUIState();
                        showToast('تعذر تشغيل البث');
                    });
                }
            }
        }

        function updateUIState() {
            if (isPlaying) {
                if(playIcon) playIcon.className = 'fas fa-pause';
                if(statusText) statusText.textContent = 'مباشر الآن';
                if(equalizer) equalizer.classList.add('playing');
            } else {
                if(playIcon) playIcon.className = 'fas fa-play';
                if(statusText) statusText.textContent = 'جاهز للبث';
                if(equalizer) equalizer.classList.remove('playing');
            }
        }

        playBtn.addEventListener('click', togglePlay);
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
            if (isPlaying && audio) audio.pause();
            isPlaying = false;
            if(statusText) statusText.textContent = 'جاهز للبث';
            if(playIcon) playIcon.className = 'fas fa-play';
            if(equalizer) equalizer.classList.remove('playing');
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

    // 9. MediaSession API
    function setupMediaSession() {
        if ('mediaSession' in navigator) {
            navigator.mediaSession.metadata = new MediaMetadata({
                title: RADIO_CONFIG.stationName,
                artist: 'البث المباشر',
                artwork: [
                    { src: '/assets/images/logo.png', sizes: '512x512', type: 'image/png' }
                ]
            });

            navigator.mediaSession.setActionHandler('play', togglePlay);
            navigator.mediaSession.setActionHandler('pause', togglePlay);
        }
    }

    // Mock Listeners count
    const listenerCountEl = document.getElementById('listenerCount');
    if (listenerCountEl) {
        setInterval(() => {
            if (isPlaying) {
                const base = 120;
                const fluctuation = Math.floor(Math.random() * 15) - 7;
                listenerCountEl.textContent = base + fluctuation;
            }
        }, 10000);
        listenerCountEl.textContent = Math.floor(Math.random() * 20) + 100;
    }
});
