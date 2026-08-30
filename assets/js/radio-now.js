document.addEventListener('DOMContentLoaded', () => {
    const npTitle = document.getElementById('npTitle');
    const npPlaylist = document.getElementById('npPlaylist');
    const npPosition = document.getElementById('npPosition');
    const npRemaining = document.getElementById('npRemaining');

    if (!npTitle && !npPlaylist && !npPosition && !npRemaining) return;

    let remainingSeconds = null;
    let remainingTimer = null;

    function formatRemaining(seconds) {
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return `${m}:${s < 10 ? '0' : ''}${s}`;
    }

    function renderRemaining() {
        if (!npRemaining) return;
        if (remainingSeconds === null) {
            npRemaining.hidden = true;
            return;
        }
        npRemaining.textContent = formatRemaining(remainingSeconds);
        npRemaining.hidden = false;
    }

    function stopRemainingTimer() {
        if (remainingTimer) {
            clearInterval(remainingTimer);
            remainingTimer = null;
        }
    }

    // يضبط الزمن المتبقّي من قيمة السيرفر (تصحّح الانزياح) ثم يُنقص ثانية
    // كل ثانية بمؤقّت واحد لا يُعاد إنشاؤه في كل استجابة
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

    function applyState(data) {
        if (npTitle) npTitle.textContent = data.title || 'لا يوجد مقطع';

        if (npPlaylist) {
            if (data.playlist) {
                npPlaylist.textContent = data.playlist;
                npPlaylist.hidden = false;
            } else {
                npPlaylist.hidden = true;
            }
        }

        if (npPosition) {
            if (data.position != null && data.total != null) {
                npPosition.textContent = `${data.position}/${data.total}`;
                npPosition.hidden = false;
            } else {
                npPosition.hidden = true;
            }
        }

        applyRemaining(data.remaining);
    }

    async function poll() {
        try {
            const res = await fetch('../api/nowplaying.php', { cache: 'no-store' });
            if (!res.ok) return;
            const data = await res.json();
            // دخل مذيع على الهواء بعد تحميل الصفحة: الموقع والمتبقّي صارا بلا
            // معنى. نوقف العدّاد بدل تركه ينزل إلى الصفر ويكذب، وبقيّة الشريط
            // تتصحّح عند أول تحديث للصفحة (وضعه يُبنى في PHP عند التحميل).
            if (data.live) {
                applyRemaining(null);
                return;
            }
            applyState(data);
        } catch (e) {
            // انقطاع مؤقت — نترك القيم السابقة ونحاول لاحقاً
        }
    }

    poll();
    setInterval(poll, 15000);
});
