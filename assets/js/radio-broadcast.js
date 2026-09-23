/**
 * البث من المتصفح إلى محرّك الراديو — مشتركة بين غرفة التحكم (المذيع) وصفحة
 * الضيف. لا تعرض أي نص: تطلق أحداثاً والصفحة تقرّر ماذا تقول.
 *
 * البروتوكول هو webcast (نفسه الذي يستعمله Web DJ في AzuraCast): WebSocket
 * بالبروتوكول الفرعي "webcast"، أول إطار JSON نوعه hello يحمل كلمة السر (هنا
 * الرمز المؤقت)، ثم إطارات ثنائية من MediaRecorder يفكّها المحرّك بـ ffmpeg.
 *
 * الأحداث:
 *   state  → detail: { state, reason }
 *            state ∈ idle | mic-ready | connecting | on-air | reconnecting | error
 *            reason (عند error) ∈ unsupported | mic-denied | no-mic | mic-failed
 *                                | rejected | no-credentials
 *   level  → detail: { level }  ‏(0..1، حوالي عشر مرات في الثانية)
 *   hidden → الصفحة اختفت أثناء البث (قفل الشاشة يقطع المايك في الموبايل)
 */
(function (global) {
    'use strict';

    // المحرّك يعرف "audio/webm" و"audio/ogg" (فحصناهما على السيرفر). Opus في
    // الحالتين؛ كروم وإيدج وسفاري الحديث يعطون webm، وفايرفوكس ogg.
    const FORMATS = [
        { recorder: 'audio/webm;codecs=opus', hello: 'audio/webm' },
        { recorder: 'audio/ogg;codecs=opus',  hello: 'audio/ogg' },
    ];

    const CHUNK_MS = 250;
    const BACKOFF = [1000, 2000, 4000, 8000, 15000, 30000];
    // اتصال يُغلق قبل هذه المدة من فتحه لم يُقبل أصلاً: المحرّك رفض الرمز
    const REJECT_WINDOW_MS = 2500;

    function pickFormat() {
        if (typeof MediaRecorder === 'undefined' || !MediaRecorder.isTypeSupported) return null;
        return FORMATS.find((f) => MediaRecorder.isTypeSupported(f.recorder)) || null;
    }

    class RadioBroadcaster extends EventTarget {
        /**
         * @param {{ getCredentials: () => Promise<{url: string, password: string}>, title?: string }} opts
         *   getCredentials تُستدعى قبل كل اتصال (والمذيع يأخذ رمزاً جديداً كل مرة)
         */
        constructor(opts) {
            super();
            this.getCredentials = opts.getCredentials;
            this.title = opts.title || '';
            this.format = pickFormat();
            this.state = 'idle';
            this.stream = null;
            this.ctx = null;
            this.analyser = null;
            this.levelTimer = null;
            this.ws = null;
            this.recorder = null;
            this.wantOnAir = false;
            this.attempt = 0;
            this.retryTimer = null;
            this.openedAt = 0;
            this.rejections = 0;
            this.wakeLock = null;
            this.onVisibility = this.onVisibility.bind(this);
        }

        static isSupported() {
            return !!(global.navigator && navigator.mediaDevices && navigator.mediaDevices.getUserMedia
                && global.WebSocket && pickFormat());
        }

        setState(state, reason) {
            this.state = state;
            this.dispatchEvent(new CustomEvent('state', { detail: { state, reason: reason || null } }));
        }

        fail(reason) {
            this.wantOnAir = false;
            this.teardownConnection();
            this.releaseWakeLock();
            this.setState('error', reason);
        }

        /** أجهزة الإدخال الصوتي. الأسماء تظهر فقط بعد أول إذن بالمايك */
        async listDevices() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) return [];
            const all = await navigator.mediaDevices.enumerateDevices();
            return all.filter((d) => d.kind === 'audioinput');
        }

        /**
         * يفتح المايك ويبدأ مؤشر المستوى.
         * raw = true لمصدر خارجي (Voicemeeter): نطفئ إلغاء الصدى وكبت الضجيج
         * وضبط المستوى التلقائي، لأنها تشوّه صوت مكالمة أو موسيقى مخلوطة مسبقاً.
         */
        async openMic({ deviceId = '', raw = false } = {}) {
            if (!RadioBroadcaster.isSupported()) { this.setState('error', 'unsupported'); return false; }
            this.closeMic();
            const audio = {
                echoCancellation: !raw,
                noiseSuppression: !raw,
                autoGainControl: !raw,
                channelCount: 1,
            };
            if (deviceId) audio.deviceId = { exact: deviceId };
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ audio });
            } catch (err) {
                const name = err && err.name;
                if (name === 'NotAllowedError' || name === 'SecurityError') this.setState('error', 'mic-denied');
                else if (name === 'NotFoundError' || name === 'OverconstrainedError') this.setState('error', 'no-mic');
                else this.setState('error', 'mic-failed');
                return false;
            }
            this.startMeter();
            // إن انقطع الجهاز (سماعة فُصلت مثلاً) نعلن الخطأ بدل بثّ صمت
            this.stream.getAudioTracks().forEach((t) => {
                t.addEventListener('ended', () => {
                    if (this.stream && this.stream.getAudioTracks().includes(t)) this.fail('mic-failed');
                });
            });
            this.setState('mic-ready');
            return true;
        }

        startMeter() {
            const Ctx = global.AudioContext || global.webkitAudioContext;
            if (!Ctx) return;
            this.ctx = new Ctx();
            const source = this.ctx.createMediaStreamSource(this.stream);
            this.analyser = this.ctx.createAnalyser();
            this.analyser.fftSize = 1024;
            source.connect(this.analyser);
            const buf = new Float32Array(this.analyser.fftSize);
            this.levelTimer = setInterval(() => {
                if (!this.analyser) return;
                this.analyser.getFloatTimeDomainData(buf);
                let sum = 0;
                for (let i = 0; i < buf.length; i++) sum += buf[i] * buf[i];
                // RMS بمقياس تقريبي للأذن: الكلام العادي يملأ نصف المؤشر تقريباً
                const level = Math.min(1, Math.sqrt(sum / buf.length) * 4);
                this.dispatchEvent(new CustomEvent('level', { detail: { level } }));
            }, 100);
        }

        closeMic() {
            if (this.levelTimer) { clearInterval(this.levelTimer); this.levelTimer = null; }
            if (this.ctx) { this.ctx.close().catch(() => {}); this.ctx = null; }
            this.analyser = null;
            if (this.stream) { this.stream.getTracks().forEach((t) => t.stop()); this.stream = null; }
        }

        /** يدخل على الهواء. المايك يجب أن يكون مفتوحاً */
        async start() {
            if (!this.stream) { this.setState('error', 'mic-failed'); return; }
            this.wantOnAir = true;
            this.attempt = 0;
            this.rejections = 0;
            document.addEventListener('visibilitychange', this.onVisibility);
            this.requestWakeLock();
            await this.connect();
        }

        async connect() {
            if (!this.wantOnAir) return;
            this.setState(this.attempt === 0 ? 'connecting' : 'reconnecting');

            let creds;
            try {
                creds = await this.getCredentials();
            } catch (e) {
                creds = null;
            }
            if (!this.wantOnAir) return;
            if (!creds || !creds.url || !creds.password) {
                // انقطاع شبكة أثناء طلب الرمز يشبه أي انقطاع آخر — نعيد المحاولة
                if (this.attempt > 0) { this.scheduleRetry(); return; }
                this.fail('no-credentials');
                return;
            }

            let ws;
            try {
                ws = new WebSocket(creds.url, 'webcast');
            } catch (e) {
                this.scheduleRetry();
                return;
            }
            this.ws = ws;
            ws.binaryType = 'arraybuffer';

            ws.onopen = () => {
                if (ws !== this.ws) return;
                this.openedAt = Date.now();
                ws.send(JSON.stringify({
                    type: 'hello',
                    data: {
                        mime: this.format.hello,
                        user: 'source',
                        password: creds.password,
                        audio: { channels: 1 },
                    },
                }));
                if (this.title) {
                    ws.send(JSON.stringify({ type: 'metadata', data: { title: this.title } }));
                }
                this.startRecorder();
            };

            ws.onclose = () => {
                if (ws !== this.ws) return;
                const quick = this.openedAt && (Date.now() - this.openedAt) < REJECT_WINDOW_MS;
                this.teardownConnection();
                if (!this.wantOnAir) return;
                if (quick) {
                    // رفض واحد قد يكون رمزاً انتهى للتوّ، والمذيع يأخذ رمزاً جديداً
                    // مع المحاولة التالية. رفضان متتاليان = الرابط لم يعد صالحاً.
                    this.rejections += 1;
                    if (this.rejections >= 2) { this.fail('rejected'); return; }
                }
                this.scheduleRetry();
            };

            // الخطأ يتبعه دائماً close، فالمعالجة هناك
            ws.onerror = () => {};
        }

        startRecorder() {
            // كل اتصال يبدأ مسجّلاً جديداً: رأس ملف webm يُرسل مرة واحدة في
            // البداية، والمحرّك يحتاجه مع كل اتصال جديد ليعرف كيف يفكّ ما بعده.
            try {
                this.recorder = new MediaRecorder(this.stream, {
                    mimeType: this.format.recorder,
                    audioBitsPerSecond: 128000,
                });
            } catch (e) {
                this.fail('unsupported');
                return;
            }
            const ws = this.ws;
            this.recorder.ondataavailable = (e) => {
                if (!e.data || e.data.size === 0 || !ws || ws.readyState !== WebSocket.OPEN) return;
                ws.send(e.data);
            };
            this.recorder.start(CHUNK_MS);

            // المحرّك لا يرسل «قُبلت»؛ يغلق الاتصال فوراً إن رفض الرمز. فلا نعلن
            // «على الهواء» إلا بعد أن يصمد الاتصال مدة نافذة الرفض.
            setTimeout(() => {
                if (ws !== this.ws || ws.readyState !== WebSocket.OPEN) return;
                this.attempt = 0;
                this.rejections = 0;
                this.setState('on-air');
            }, REJECT_WINDOW_MS);
        }

        scheduleRetry() {
            if (!this.wantOnAir) return;
            const delay = BACKOFF[Math.min(this.attempt, BACKOFF.length - 1)];
            this.attempt += 1;
            this.setState('reconnecting');
            this.retryTimer = setTimeout(() => { this.retryTimer = null; this.connect(); }, delay);
        }

        teardownConnection() {
            if (this.retryTimer) { clearTimeout(this.retryTimer); this.retryTimer = null; }
            if (this.recorder) {
                this.recorder.ondataavailable = null;
                if (this.recorder.state !== 'inactive') {
                    try { this.recorder.stop(); } catch (e) { /* مغلق أصلاً */ }
                }
                this.recorder = null;
            }
            if (this.ws) {
                const ws = this.ws;
                this.ws = null;
                ws.onopen = ws.onclose = ws.onerror = null;
                if (ws.readyState === WebSocket.OPEN || ws.readyState === WebSocket.CONNECTING) {
                    try { ws.close(1000); } catch (e) { /* مغلق أصلاً */ }
                }
            }
            this.openedAt = 0;
        }

        /** يخرج من الهواء ويُبقي المايك مفتوحاً للدخول مجدداً */
        stop() {
            this.wantOnAir = false;
            this.teardownConnection();
            this.releaseWakeLock();
            document.removeEventListener('visibilitychange', this.onVisibility);
            this.setState(this.stream ? 'mic-ready' : 'idle');
        }

        /** إغلاق كامل: الهواء والمايك */
        shutdown() {
            this.stop();
            this.closeMic();
            this.setState('idle');
        }

        async requestWakeLock() {
            if (!('wakeLock' in navigator)) return;
            try {
                this.wakeLock = await navigator.wakeLock.request('screen');
            } catch (e) {
                this.wakeLock = null; // البطارية منخفضة أو الصفحة في الخلفية — نحاول لاحقاً
            }
        }

        releaseWakeLock() {
            if (this.wakeLock) { this.wakeLock.release().catch(() => {}); this.wakeLock = null; }
        }

        onVisibility() {
            if (!this.wantOnAir) return;
            if (document.visibilityState === 'visible') {
                // المتصفح يحرّر قفل الشاشة تلقائياً عند الإخفاء، فنعيده
                this.requestWakeLock();
            } else {
                this.dispatchEvent(new CustomEvent('hidden'));
            }
        }
    }

    global.RadioBroadcaster = RadioBroadcaster;
})(window);
