/**
 * غرفة التحكم: حالة المدخلين كل ثانيتين، الكتم والطرد، بثّ المذيع من
 * المتصفح، روابط الضيوف، العرض المرئي، والنسخ.
 */
document.addEventListener('DOMContentLoaded', () => {
    if (typeof STUDIO_CONFIG === 'undefined') return;

    const POLL_MS = 2000;
    const notice = document.getElementById('liveNotice');

    /* ===== أدوات ===== */

    async function api(action, extra = {}) {
        const res = await fetch(STUDIO_CONFIG.apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: STUDIO_CONFIG.csrf, action, ...extra }),
            cache: 'no-store',
        });
        let data;
        try { data = await res.json(); } catch (e) { data = { success: false, error: 'ردّ غير متوقع من السيرفر' }; }
        if (!data.success && data.error) showNotice(data.error);
        return data;
    }

    let noticeTimer = null;
    function showNotice(text) {
        notice.textContent = text;
        notice.hidden = false;
        clearTimeout(noticeTimer);
        noticeTimer = setTimeout(() => { notice.hidden = true; }, 8000);
    }

    async function copyFrom(selector, btn) {
        const field = document.querySelector(selector);
        if (!field) return;
        try {
            await navigator.clipboard.writeText(field.value);
        } catch (e) {
            field.select();
            document.execCommand('copy');
        }
        const old = btn.textContent;
        btn.textContent = 'نُسخ ✓';
        setTimeout(() => { btn.textContent = old; }, 1500);
    }

    document.querySelectorAll('[data-copy]').forEach((btn) => {
        btn.addEventListener('click', () => copyFrom(btn.dataset.copy, btn));
    });

    function formatTime(dt) {
        // "2026-09-23 21:40:00" ← "21:40"
        return String(dt || '').slice(11, 16);
    }

    /* ===== المدخلان ===== */

    const slotEls = {};
    document.querySelectorAll('.studio-slot').forEach((el) => {
        const slot = Number(el.dataset.slot);
        slotEls[slot] = {
            dot: el.querySelector('[data-role="dot"]'),
            badge: el.querySelector('[data-role="badge"]'),
            who: el.querySelector('[data-role="who"]'),
            buttHint: el.querySelector('[data-role="butt-hint"]'),
            mute: el.querySelector('[data-action="mute"]'),
            unmute: el.querySelector('[data-action="unmute"]'),
            kick: el.querySelector('[data-action="kick"]'),
        };
        el.querySelectorAll('[data-action]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const action = btn.dataset.action;
                if (action === 'kick' && !confirm('طرد من على هذا المدخل؟ رابطه يُلغى ولا يعود به.')) return;
                btn.disabled = true;
                await api(action, { slot });
                btn.disabled = false;
                refresh();
            });
        });
    });

    function renderSlot(s, engineUp) {
        const el = slotEls[s.slot];
        if (!el) return;
        const connected = engineUp && s.connected;
        el.dot.className = 'status-dot ' + (!engineUp ? 'down' : connected ? (s.muted ? 'muted' : 'live') : 'auto');
        el.badge.textContent = !engineUp ? 'المحرّك متوقف' : connected ? (s.muted ? 'مكتوم' : 'على الهواء') : 'غير متصل';
        el.badge.className = 'badge ' + (connected && !s.muted ? 'badge-on' : 'badge-off');

        if (!connected) el.who.textContent = '—';
        else if (s.source === 'butt') el.who.textContent = 'من برنامج BUTT';
        else el.who.textContent = (s.name || 'متصل') + ' — من المتصفح';

        el.buttHint.hidden = !(connected && s.source === 'butt');
        el.mute.hidden = s.muted;
        el.unmute.hidden = !s.muted;
        el.mute.disabled = !connected;
        el.unmute.disabled = !engineUp;
        el.kick.disabled = !connected;
    }

    /* ===== روابط الضيوف ===== */

    const guestList = document.getElementById('guestList');
    const guestForm = document.getElementById('guestForm');
    const guestNewLink = document.getElementById('guestNewLink');
    const guestLinkField = document.getElementById('guestLinkField');
    const guestWhatsapp = document.getElementById('guestWhatsapp');

    function renderGuests(guests) {
        guestList.replaceChildren();
        if (!guests.length) {
            const li = document.createElement('li');
            li.className = 'empty-note';
            li.textContent = 'لا يوجد';
            guestList.appendChild(li);
            return;
        }
        guests.forEach((g) => {
            const li = document.createElement('li');
            li.className = 'studio-guest';
            const text = document.createElement('span');
            text.textContent = g.name + ' · حتى ' + formatTime(g.expires_at) + (g.used ? ' · دخل' : '');
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-ghost btn-sm';
            btn.textContent = 'إلغاء';
            btn.addEventListener('click', async () => {
                if (!confirm('إلغاء رابط ' + g.name + '؟ إن كان على الهواء يُقطع فوراً.')) return;
                await api('guest_revoke', { id: g.id });
                refresh();
            });
            li.append(text, btn);
            guestList.appendChild(li);
        });
    }

    guestForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const name = document.getElementById('guestName').value.trim();
        const hours = Number(document.getElementById('guestHours').value);
        const data = await api('guest_create', { name, hours });
        if (!data.success) return;
        guestLinkField.value = data.link;
        const msg = 'أهلاً ' + name + '، هذا رابط دخولك على الهواء. افتحه من كروم والبس سماعة:\n' + data.link;
        guestWhatsapp.href = 'https://wa.me/?text=' + encodeURIComponent(msg);
        guestNewLink.hidden = false;
        guestForm.reset();
        refresh();
    });

    /* ===== العرض المرئي ===== */

    const visualNow = document.getElementById('visualNow');
    const visualNowTitle = document.getElementById('visualNowTitle');
    const delayInput = document.getElementById('visualDelay');
    let delayDirty = false;

    function renderVisual(visual, delay) {
        visualNow.hidden = !visual;
        if (visual) visualNowTitle.textContent = visual.title || (visual.type === 'video' ? 'فيديو' : 'صورة');
        document.querySelectorAll('.studio-visual').forEach((el) => {
            el.classList.toggle('is-showing', !!visual && Number(el.dataset.visualId) === visual.id);
        });
        if (!delayDirty && document.activeElement !== delayInput) delayInput.value = delay;
    }

    document.querySelectorAll('[data-show]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            btn.disabled = true;
            const data = await api('visual_show', { id: Number(btn.dataset.show) });
            btn.disabled = false;
            if (data.success && data.warning) showNotice(data.warning);
            refresh();
        });
    });

    document.getElementById('visualStop').addEventListener('click', async () => {
        await api('visual_stop');
        refresh();
    });

    delayInput.addEventListener('input', () => { delayDirty = true; });
    delayInput.addEventListener('change', async () => {
        const data = await api('visual_delay', { seconds: Number(delayInput.value) });
        if (data.success) delayInput.value = data.delay;
        delayDirty = false;
    });

    /* ===== الاستطلاع ===== */

    let polling = false;
    async function refresh() {
        if (polling) return;
        polling = true;
        try {
            const data = await api('state');
            if (data.success) {
                data.slots.forEach((s) => renderSlot(s, data.engine));
                renderGuests(data.guests);
                renderVisual(data.visual, data.delay);
            }
        } catch (e) {
            // انقطاع مؤقت — الاستطلاع التالي يصحّح
        } finally {
            polling = false;
        }
    }
    refresh();
    setInterval(() => { if (!document.hidden) refresh(); }, POLL_MS);

    /* ===== بثّ المذيع من المتصفح ===== */

    const deviceSel = document.getElementById('hostDevice');
    const rawBox = document.getElementById('hostRaw');
    const meterFill = document.getElementById('hostMeterFill');
    const hostStatus = document.getElementById('hostStatus');
    const micBtn = document.getElementById('hostMic');
    const onAirBtn = document.getElementById('hostOnAir');
    const offAirBtn = document.getElementById('hostOffAir');

    const host = new RadioBroadcaster({
        title: STUDIO_CONFIG.hostTitle,
        // رمز جديد مع كل اتصال — يلغي ما قبله على السيرفر
        getCredentials: async () => {
            const data = await api('host_token');
            return data.success ? { url: data.url, password: data.password } : null;
        },
    });

    const HOST_TEXT = {
        'idle': 'المايك مغلق',
        'mic-ready': 'المايك يعمل — لست على الهواء',
        'connecting': 'جارٍ الاتصال بالمحرّك…',
        'reconnecting': 'انقطع الاتصال — نعيد المحاولة تلقائياً',
        'on-air': '🔴 أنت على الهواء',
    };
    const HOST_ERRORS = {
        'unsupported': 'هذا المتصفح لا يدعم البث — استعمل Chrome أو Edge',
        'mic-denied': 'المتصفح منع المايك — اسمح به من القفل بجانب العنوان',
        'no-mic': 'لم يُعثر على الجهاز المختار',
        'mic-failed': 'توقف المايك — افتحه من جديد',
        'rejected': 'المحرّك رفض الاتصال — هل radio.liq المحدَّث منشور؟',
        'no-credentials': 'تعذّر بدء جلسة البث — حدّث الصفحة',
    };

    async function fillDevices() {
        const current = deviceSel.value;
        const devices = await host.listDevices();
        deviceSel.replaceChildren(new Option('الافتراضي', ''));
        devices.forEach((d, i) => {
            deviceSel.appendChild(new Option(d.label || ('مايك ' + (i + 1)), d.deviceId));
        });
        deviceSel.value = current;
        // اسم Voicemeeter في القائمة يعني غالباً أنه المقصود: نفعّل الوضع الخام
        const opt = deviceSel.selectedOptions[0];
        if (opt && /voicemeeter/i.test(opt.textContent)) rawBox.checked = true;
    }

    async function openMic() {
        if (host.state === 'on-air' || host.state === 'connecting' || host.state === 'reconnecting') return;
        await host.openMic({ deviceId: deviceSel.value, raw: rawBox.checked });
        fillDevices();
    }

    micBtn.addEventListener('click', openMic);
    deviceSel.addEventListener('change', () => {
        if (/voicemeeter/i.test(deviceSel.selectedOptions[0]?.textContent || '')) rawBox.checked = true;
        if (host.stream) openMic();
    });
    rawBox.addEventListener('change', () => { if (host.stream) openMic(); });
    onAirBtn.addEventListener('click', () => host.start());
    offAirBtn.addEventListener('click', () => host.stop());

    host.addEventListener('level', (e) => {
        meterFill.style.transform = 'scaleX(' + e.detail.level.toFixed(2) + ')';
    });

    host.addEventListener('hidden', () => {
        showNotice('الصفحة مخفية وأنت على الهواء — بعض المتصفحات تُضعف المايك في الخلفية');
    });

    host.addEventListener('state', (e) => {
        const { state, reason } = e.detail;
        hostStatus.textContent = state === 'error' ? (HOST_ERRORS[reason] || 'حدث خطأ') : HOST_TEXT[state];
        const live = state === 'on-air' || state === 'connecting' || state === 'reconnecting';
        onAirBtn.hidden = live;
        offAirBtn.hidden = !live;
        onAirBtn.disabled = state !== 'mic-ready';
        micBtn.disabled = live;
        deviceSel.disabled = live;
        rawBox.disabled = live;
        if (state === 'on-air' || state === 'idle' || state === 'mic-ready') refresh();
    });

    // تحذير قبل إغلاق الصفحة أثناء الهواء — الإغلاق يقطع البث
    window.addEventListener('beforeunload', (e) => {
        if (host.state === 'on-air' || host.state === 'reconnecting') {
            e.preventDefault();
            e.returnValue = '';
        }
    });
});
