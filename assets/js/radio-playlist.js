/**
 * إعادة ترتيب مقاطع البلاي ليست بالسحب — Pointer Events (فأرة ولمس معاً).
 * أزرار «أعلى/أسفل» في الصفحة تبقى تعمل دائماً كبديل وصولية إلزامي (WCAG 2.5.7)
 * ولا تعتمد على هذا الملف إطلاقاً.
 */
document.addEventListener('DOMContentLoaded', function () {
    const list = document.getElementById('playlistList');
    if (!list) return;

    const saveBar     = document.getElementById('playlistSaveBar');
    const saveBtn      = document.getElementById('savePlaylistOrderBtn');
    const liveRegion   = document.getElementById('playlistLiveRegion');
    const playlistId   = list.dataset.playlistId;
    const csrfToken    = list.dataset.csrf;

    const TOUCH_DELAY     = 250; // مللي ثانية قبل بدء السحب باللمس (لمسة طويلة)
    const TOUCH_TOLERANCE = 8;   // بكسل — تجاوزها قبل انتهاء المهلة يُلغي اللمسة الطويلة
    const MOUSE_TOLERANCE = 5;   // بكسل — الفأرة بلا تأخير زمني

    let drag = null;

    function announce(msg) {
        if (liveRegion) liveRegion.textContent = msg;
    }

    function showSaveBar() {
        if (saveBar) saveBar.hidden = false;
    }

    function hideSaveBar() {
        if (saveBar) saveBar.hidden = true;
    }

    function renumber() {
        const items = Array.prototype.slice.call(list.querySelectorAll('.playlist-item'));
        items.forEach(function (li, i) {
            const numEl = li.querySelector('.playlist-item-num');
            if (numEl) numEl.textContent = String(i + 1);

            const upBtn = li.querySelector('.move-up-btn');
            const downBtn = li.querySelector('.move-down-btn');
            if (upBtn) upBtn.disabled = (i === 0);
            if (downBtn) downBtn.disabled = (i === items.length - 1);
        });
    }

    function cleanupListeners(handle) {
        handle.removeEventListener('pointermove', onPointerMove);
        handle.removeEventListener('pointerup', onPointerUp);
        handle.removeEventListener('pointercancel', onPointerCancel);
    }

    function moveGhost(clientY) {
        if (!drag || !drag.ghost) return;
        drag.ghost.style.top = (clientY - drag.offsetY) + 'px';
    }

    function reorder(clientY) {
        const siblings = Array.prototype.slice
            .call(list.querySelectorAll('.playlist-item'))
            .filter(function (el) { return el !== drag.li; });

        for (let i = 0; i < siblings.length; i++) {
            const rect = siblings[i].getBoundingClientRect();
            const mid = rect.top + rect.height / 2;
            if (clientY < mid) {
                list.insertBefore(drag.li, siblings[i]);
                return;
            }
        }
        list.appendChild(drag.li);
    }

    function startDrag(clientY) {
        drag.dragging = true;
        clearTimeout(drag.timer);

        const rect = drag.li.getBoundingClientRect();
        drag.offsetY = drag.startY - rect.top;

        const ghost = drag.li.cloneNode(true);
        ghost.classList.add('playlist-ghost');
        // موضع الشبح ديناميكي بالكامل ويتغيّر كل إطار، فلا مفرّ من ضبطه هنا
        ghost.style.width = rect.width + 'px';
        ghost.style.left = rect.left + 'px';
        ghost.style.top = rect.top + 'px';
        document.body.appendChild(ghost);
        drag.ghost = ghost;

        drag.li.classList.add('is-dragging');
        document.body.classList.add('is-reordering');

        moveGhost(clientY);
    }

    function endDrag(commit) {
        const d = drag;
        clearTimeout(d.timer);
        cleanupListeners(d.handle);
        try { d.handle.releasePointerCapture(d.pointerId); } catch (err) { /* لا شيء */ }

        if (d.ghost) d.ghost.remove();
        d.li.classList.remove('is-dragging');
        document.body.classList.remove('is-reordering');

        if (commit) {
            renumber();
            const items = Array.prototype.slice.call(list.querySelectorAll('.playlist-item'));
            const pos = items.indexOf(d.li) + 1;
            const titleEl = d.li.querySelector('.playlist-item-title');
            const title = titleEl ? titleEl.textContent.trim() : '';
            announce('تم نقل «' + title + '» إلى الموضع ' + pos + ' من ' + items.length + '. اضغط زرّ احفظ الترتيب لتثبيت التغيير.');
            showSaveBar();
        } else if (d.dragging) {
            // pointercancel أثناء سحب فعلي — نعيد الصفّ لمكانه الأصلي بهدوء بلا رسالة
            if (d.originalNext && d.originalNext.parentElement === d.originalParent) {
                d.originalParent.insertBefore(d.li, d.originalNext);
            } else {
                d.originalParent.appendChild(d.li);
            }
        }

        drag = null;
    }

    function cancelBeforeDrag() {
        clearTimeout(drag.timer);
        cleanupListeners(drag.handle);
        drag = null;
    }

    function onPointerDown(e) {
        if (e.pointerType === 'mouse' && e.button !== 0) return;
        if (drag) return;

        const handle = e.currentTarget;
        const li = handle.closest('.playlist-item');
        if (!li) return;

        drag = {
            pointerId: e.pointerId,
            handle: handle,
            li: li,
            pointerType: e.pointerType,
            startX: e.clientX,
            startY: e.clientY,
            offsetY: 0,
            dragging: false,
            timer: null,
            ghost: null,
            originalParent: li.parentElement,
            originalNext: li.nextElementSibling,
        };

        try { handle.setPointerCapture(e.pointerId); } catch (err) { /* لا شيء */ }

        handle.addEventListener('pointermove', onPointerMove);
        handle.addEventListener('pointerup', onPointerUp);
        handle.addEventListener('pointercancel', onPointerCancel);

        if (e.pointerType !== 'mouse') {
            const startClientY = e.clientY;
            drag.timer = setTimeout(function () {
                if (drag && !drag.dragging) startDrag(startClientY);
            }, TOUCH_DELAY);
        }
    }

    function onPointerMove(e) {
        if (!drag || e.pointerId !== drag.pointerId) return;

        const dx = e.clientX - drag.startX;
        const dy = e.clientY - drag.startY;
        const dist = Math.sqrt(dx * dx + dy * dy);

        if (!drag.dragging) {
            if (drag.pointerType === 'mouse') {
                if (dist >= MOUSE_TOLERANCE) startDrag(e.clientY);
            } else if (dist >= TOUCH_TOLERANCE) {
                // تحرّك الإصبع أكثر من المسموح قبل اكتمال اللمسة الطويلة — نيّة
                // تمرير للصفحة لا سحب، نلغي بهدوء
                cancelBeforeDrag();
            }
            return;
        }

        e.preventDefault();
        moveGhost(e.clientY);
        reorder(e.clientY);
    }

    function onPointerUp(e) {
        if (!drag || e.pointerId !== drag.pointerId) return;
        endDrag(drag.dragging);
    }

    function onPointerCancel(e) {
        if (!drag || e.pointerId !== drag.pointerId) return;
        endDrag(false);
    }

    list.querySelectorAll('.drag-handle').forEach(function (handle) {
        handle.addEventListener('pointerdown', onPointerDown);
    });

    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            const itemIds = Array.prototype.slice
                .call(list.querySelectorAll('.playlist-item'))
                .map(function (li) { return parseInt(li.dataset.itemId, 10); });

            saveBtn.disabled = true;

            fetch('ajax/save-playlist-order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    playlist_id: parseInt(playlistId, 10),
                    item_ids: itemIds,
                    csrf_token: csrfToken,
                }),
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    saveBtn.disabled = false;
                    if (data.success) {
                        hideSaveBar();
                        announce('تم حفظ الترتيب');
                        if (typeof showToast === 'function') showToast('تم حفظ الترتيب', 'success');
                    } else if (typeof showToast === 'function') {
                        showToast(data.error || 'تعذّر حفظ الترتيب', 'error');
                    }
                })
                .catch(function () {
                    saveBtn.disabled = false;
                    if (typeof showToast === 'function') showToast('حدث خطأ في الاتصال', 'error');
                });
        });
    }
});
