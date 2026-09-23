/**
 * العرض المرئي للجمهور — مشترك بين صفحة الراديو وشاشة الاستوديو.
 *
 * يستطلع api/live-state.php ويعرض الصورة أو الفيديو داخل حاوية، متأخراً بمقدار
 * delay عن لحظة الضغط في غرفة التحكم: صوت البث يصل المستمع متأخراً بهذا
 * القدر تقريباً، فتتطابق الصورة مع ما يُسمع. الفيديو يعمل صامتاً دائماً — صوته
 * الحقيقي يأتي من البث نفسه.
 *
 * الوقت يُحسب بساعة السيرفر (now في كل ردّ) لا بساعة الجهاز، لأن ساعات
 * الأجهزة تنحرف ثواني كثيرة، فيبدأ الزائر الذي دخل متأخراً الفيديو من موضعه
 * الصحيح.
 *
 * LiveVisual.attach(container, { stateUrl, pollMs, onState(state) })
 *   onState يُستدعى مع كل ردّ ناجح (لأسماء من على الهواء مثلاً).
 *   الحاوية تأخذ الصنف is-showing أثناء العرض.
 */
(function (global) {
    'use strict';

    function attach(container, opts) {
        const pollMs = opts.pollMs || 4000;
        let offset = 0;            // ساعة السيرفر − ساعة الجهاز (ثوانٍ)
        let currentKey = null;     // ما هو معروض الآن
        let pendingKey = null;     // ما ينتظر موعده
        let pendingTimer = null;

        const serverNow = () => Date.now() / 1000 + offset;

        function clear() {
            container.replaceChildren();
            container.classList.remove('is-showing');
            currentKey = null;
        }

        function render(v, key, delay) {
            clear();
            let el;
            if (v.type === 'video') {
                el = document.createElement('video');
                el.muted = true;
                el.playsInline = true;
                el.autoplay = true;
                el.setAttribute('playsinline', '');
                el.addEventListener('loadedmetadata', () => {
                    const pos = serverNow() - (v.started_at + delay);
                    if (pos > 0 && pos < el.duration) el.currentTime = pos;
                    el.play().catch(() => {});
                }, { once: true });
                el.addEventListener('ended', () => { if (currentKey === key) clear(); });
            } else {
                el = document.createElement('img');
                el.alt = v.title || '';
            }
            el.src = v.url;
            el.className = 'live-visual-media';
            container.appendChild(el);
            container.classList.add('is-showing');
            currentKey = key;
        }

        function schedule(key, delayMs, fn) {
            if (pendingKey === key) return;
            if (pendingTimer) clearTimeout(pendingTimer);
            pendingKey = key;
            pendingTimer = setTimeout(() => {
                pendingTimer = null;
                pendingKey = null;
                fn();
            }, Math.max(0, delayMs));
        }

        function apply(state) {
            const v = state.visual;
            const delay = Number(state.delay) || 0;

            if (!v) {
                // الإيقاف يُسمع متأخراً أيضاً، فنُخفي بعد نفس التأخير
                if (currentKey !== null) schedule('none', delay * 1000, clear);
                else if (pendingKey && pendingKey !== 'none') { clearTimeout(pendingTimer); pendingTimer = null; pendingKey = null; }
                return;
            }

            const key = v.url + '@' + v.started_at;
            if (key === currentKey) return;

            const showAt = v.started_at + delay;
            if (v.type === 'video' && v.duration && serverNow() > showAt + v.duration) return; // انتهى أصلاً
            schedule(key, (showAt - serverNow()) * 1000, () => render(v, key, delay));
        }

        async function poll() {
            try {
                const res = await fetch(opts.stateUrl, { cache: 'no-store' });
                const state = await res.json();
                if (typeof state.now === 'number') offset = state.now - Date.now() / 1000;
                apply(state);
                if (opts.onState) opts.onState(state);
            } catch (e) { /* الاستطلاع التالي يكفي */ }
        }

        poll();
        setInterval(poll, pollMs);
    }

    global.LiveVisual = { attach };
})(window);
