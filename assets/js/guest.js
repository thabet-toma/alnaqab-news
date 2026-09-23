/**
 * صفحة الضيف: زر واحد فعّال في كل لحظة، وكل تغيّر حالة يُعلن مرتين — نصاً في
 * منطقة aria-live يقرؤها قارئ الشاشة، ونغمة قصيرة يفهمها الضيف دون أن ينظر.
 *   دخول الهواء: نغمتان صاعدتان · خروج أو انقطاع: نغمة هابطة · كتم: نقرتان قصيرتان
 */
document.addEventListener('DOMContentLoaded', () => {
    if (typeof GUEST_CONFIG === 'undefined') return;

    const statusEl  = document.getElementById('guestStatus');
    const meter     = document.getElementById('guestMeter');
    const meterFill = document.getElementById('guestMeterFill');
    const mainBtn   = document.getElementById('guestBtn');
    const leaveBtn  = document.getElementById('guestLeave');
    const help      = document.getElementById('guestHelp');
    const helpText  = document.getElementById('guestHelpText');
    const copyBtn   = document.getElementById('guestCopy');

    const STATE_POLL_MS = 4000;
    let mode = 'start';       // start | ready | busy | on-air | done
    let muted = false;
    let pollTimer = null;
    let toneCtx = null;

    const broadcaster = new RadioBroadcaster({
        getCredentials: async () => ({ url: GUEST_CONFIG.wsUrl, password: GUEST_CONFIG.token }),
        title: 'مباشر مع ' + GUEST_CONFIG.name,
    });

    /* ===== الإعلان: نص + نغمة ===== */

    function say(text) {
        // تفريغ ثم كتابة يجبر قارئ الشاشة على إعادة القراءة حتى لو تكرّر النص
        statusEl.textContent = '';
        setTimeout(() => { statusEl.textContent = text; }, 50);
    }

    function tone(freqs, length = 0.15) {
        try {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return;
            toneCtx = toneCtx || new Ctx();
            let t = toneCtx.currentTime;
            freqs.forEach((f) => {
                const osc = toneCtx.createOscillator();
                const gain = toneCtx.createGain();
                osc.frequency.value = f;
                gain.gain.setValueAtTime(0.0001, t);
                gain.gain.exponentialRampToValueAtTime(0.25, t + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, t + length);
                osc.connect(gain).connect(toneCtx.destination);
                osc.start(t);
                osc.stop(t + length + 0.02);
                t += length + 0.06;
            });
        } catch (e) { /* النغمة تحسين لا شرط */ }
    }
    const TONE_ON    = () => tone([660, 880]);
    const TONE_OFF   = () => tone([520, 330], 0.25);
    const TONE_MUTED = () => tone([440, 440], 0.07);

    function showMainButton(label, disabled = false) {
        mainBtn.textContent = label;
        mainBtn.disabled = disabled;
        mainBtn.hidden = false;
    }

    function showHelp(text) {
        helpText.textContent = text;
        help.hidden = false;
    }

    /* ===== متابعة الكتم وصلاحية الرابط أثناء الهواء ===== */

    async function pollState() {
        try {
            const res = await fetch(GUEST_CONFIG.stateUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ token: GUEST_CONFIG.token }),
                cache: 'no-store',
            });
            const data = await res.json();
            if (!data.valid) { finish('انتهت مشاركتك على الهواء. شكراً لك'); return; }
            if (data.muted !== muted) {
                muted = data.muted;
                if (muted) { TONE_MUTED(); say('المذيع كتم صوتك مؤقتاً. انتظر'); }
                else { TONE_ON(); say('عاد صوتك على الهواء'); }
            }
        } catch (e) { /* الشبكة تلعثمت — المحاولة التالية تكفي */ }
    }

    function startPolling() {
        stopPolling();
        pollTimer = setInterval(pollState, STATE_POLL_MS);
    }
    function stopPolling() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    function finish(text) {
        mode = 'done';
        stopPolling();
        broadcaster.shutdown();
        TONE_OFF();
        say(text);
        mainBtn.hidden = true;
        leaveBtn.hidden = true;
        meter.hidden = true;
    }

    /* ===== أحداث البث ===== */

    const ERROR_TEXT = {
        'mic-denied': 'لم يُسمح باستعمال المايك. اضغط على القفل بجانب عنوان الصفحة واسمح بالمايك، ثم اضغط الزر مرة أخرى',
        'no-mic': 'لم نجد مايكاً على هذا الجهاز. وصّل سماعة بمايك ثم اضغط الزر',
        'mic-failed': 'توقف المايك. اضغط الزر لتشغيله من جديد',
    };

    broadcaster.addEventListener('level', (e) => {
        meterFill.style.transform = 'scaleX(' + e.detail.level.toFixed(2) + ')';
    });

    broadcaster.addEventListener('hidden', () => {
        TONE_OFF();
        say('الصفحة لم تعد ظاهرة. ارجع إليها حتى لا ينقطع صوتك');
    });

    broadcaster.addEventListener('state', (e) => {
        const { state, reason } = e.detail;
        if (mode === 'done') return;

        switch (state) {
            case 'mic-ready':
                mode = 'ready';
                meter.hidden = false;
                leaveBtn.hidden = true;
                stopPolling();
                showMainButton('ادخل على الهواء');
                break;
            case 'connecting':
                mode = 'busy';
                say('جارٍ الاتصال بالاستوديو');
                showMainButton('جارٍ الاتصال…', true);
                break;
            case 'reconnecting':
                if (mode === 'on-air') { TONE_OFF(); say('انقطع الاتصال. نعيد المحاولة تلقائياً، لا تغلق الصفحة'); }
                mode = 'busy';
                showMainButton('نعيد الاتصال…', true);
                break;
            case 'on-air':
                mode = 'on-air';
                muted = false;
                TONE_ON();
                say('أنت على الهواء الآن. تكلّم');
                mainBtn.hidden = true;
                leaveBtn.hidden = false;
                leaveBtn.focus();
                startPolling();
                break;
            case 'error':
                if (reason === 'rejected') { finish('الرابط لم يعد صالحاً. اطلب رابطاً جديداً من المذيع'); return; }
                if (reason === 'unsupported') {
                    mode = 'done';
                    say('هذا المتصفح لا يدعم البث');
                    mainBtn.hidden = true;
                    showHelp('افتح الرابط من متصفح Google Chrome. انسخه من الزر وألصقه هناك');
                    return;
                }
                mode = 'start';
                TONE_OFF();
                say(ERROR_TEXT[reason] || 'حدث خطأ. اضغط الزر للمحاولة مرة أخرى');
                showMainButton('حاول مرة أخرى');
                break;
        }
    });

    /* ===== الأزرار ===== */

    mainBtn.addEventListener('click', async () => {
        if (mode === 'start') {
            // النغمات تحتاج لمسة من المستخدم لتعمل، فنجهّزها هنا
            tone([1], 0.01);
            showMainButton('انتظر…', true);
            const ok = await broadcaster.openMic();
            if (ok) say('المايك يعمل. قل شيئاً لتراه يتحرك، ثم اضغط ادخل على الهواء');
        } else if (mode === 'ready') {
            broadcaster.start();
        }
    });

    leaveBtn.addEventListener('click', () => {
        stopPolling();
        broadcaster.stop();
        TONE_OFF();
        say('خرجت من الهواء. تستطيع الدخول مرة أخرى بنفس الزر');
    });

    copyBtn.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(location.href);
            say('نُسخ الرابط');
        } catch (e) {
            say('لم ينجح النسخ. انسخ الرابط من شريط العنوان');
        }
    });

    if (!RadioBroadcaster.isSupported()) {
        broadcaster.setState('error', 'unsupported');
    }
});
