// Lightweight image lightbox (no dependencies)
// Usage: add data-lightbox="property" to any <img>.
(function () {
    if (window.__reserveproLightboxInit) return;
    window.__reserveproLightboxInit = true;

    var state = {
        scale: 1,
        minScale: 1,
        maxScale: 5,
        tx: 0,
        ty: 0,
        dragging: false,
        dragStartX: 0,
        dragStartY: 0,
        dragStartTx: 0,
        dragStartTy: 0,
        items: [],
        index: 0,
    };

    function clamp(v, min, max) {
        return Math.max(min, Math.min(max, v));
    }

    function applyTransform() {
        var img = document.getElementById('rpLightboxImg');
        if (!img) return;
        img.style.transform = 'translate(' + state.tx + 'px,' + state.ty + 'px) scale(' + state.scale + ')';
        img.style.transformOrigin = '0 0';
        img.style.cursor = state.scale > 1 ? (state.dragging ? 'grabbing' : 'grab') : 'zoom-in';
    }

    function setScale(nextScale, anchorClientX, anchorClientY) {
        var body = document.getElementById('rpLightboxBody');
        var img = document.getElementById('rpLightboxImg');
        if (!body || !img) return;

        var prevScale = state.scale;
        nextScale = clamp(nextScale, state.minScale, state.maxScale);
        if (Math.abs(nextScale - prevScale) < 0.001) return;

        // Keep the zoom anchored around the cursor position (within body)
        var rect = body.getBoundingClientRect();
        var ax = typeof anchorClientX === 'number' ? anchorClientX : (rect.left + rect.width / 2);
        var ay = typeof anchorClientY === 'number' ? anchorClientY : (rect.top + rect.height / 2);

        var px = (ax - rect.left - state.tx);
        var py = (ay - rect.top - state.ty);
        var ratio = nextScale / prevScale;

        state.tx = state.tx - px * (ratio - 1);
        state.ty = state.ty - py * (ratio - 1);
        state.scale = nextScale;

        applyTransform();
    }

    function resetTransform() {
        state.scale = 1;
        state.tx = 0;
        state.ty = 0;
        state.dragging = false;
        applyTransform();
    }

    function fitStageToImage() {
        var body = document.getElementById('rpLightboxBody');
        var stage = document.getElementById('rpLightboxStage');
        var img = document.getElementById('rpLightboxImg');
        if (!body || !stage || !img) return;

        var rect = body.getBoundingClientRect();
        var vw = Math.max(1, rect.width - 28);  // account for padding-ish
        var vh = Math.max(1, rect.height - 28);
        var iw = img.naturalWidth || 0;
        var ih = img.naturalHeight || 0;

        // Make a scrollable canvas that's at least viewport size, but also fits the image
        var stageW = Math.max(vw, Math.min(4000, iw || vw));
        var stageH = Math.max(vh, Math.min(4000, ih || vh));
        stage.style.width = stageW + 'px';
        stage.style.height = stageH + 'px';

        // Center image in the stage at scale=1
        resetTransform();
        if (iw && ih) {
            state.tx = Math.round((stageW - iw) / 2);
            state.ty = Math.round((stageH - ih) / 2);
            applyTransform();
        }

        // Center scroll position so image is visible immediately
        body.scrollLeft = Math.max(0, (stageW - rect.width) / 2);
        body.scrollTop = Math.max(0, (stageH - rect.height) / 2);
    }

    function ensureModal() {
        var existing = document.getElementById('rpLightboxBackdrop');
        if (existing) return existing;

        var style = document.createElement('style');
        style.id = 'rpLightboxStyles';
        style.textContent = [
            '#rpLightboxBackdrop{position:fixed;inset:0;z-index:20000;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(0,0,0,.72);backdrop-filter:blur(4px)}',
            '#rpLightboxBackdrop.open{display:flex}',
            '#rpLightboxModal{width:min(1040px,96vw);max-height:92vh;border-radius:18px;overflow:hidden;background:rgba(17,24,39,.96);border:1px solid rgba(148,163,184,.18);box-shadow:0 30px 80px rgba(0,0,0,.6)}',
            '#rpLightboxHead{padding:12px 14px;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(148,163,184,.14);position:relative}',
            '#rpLightboxTitle{flex:1;min-width:0;text-align:center;font-size:13px;font-weight:900;color:#E2E8F0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}',
            '.rpLightboxBtn{border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.06);color:#E2E8F0;border-radius:10px;padding:7px 10px;cursor:pointer;font-weight:800;font-size:12px}',
            '.rpLightboxBtn:hover{background:rgba(255,255,255,.10)}',
            '.rpLightboxExitX{width:36px;height:36px;border-radius:12px;display:inline-grid;place-items:center;font-size:18px;line-height:1;font-weight:900;position:absolute;right:12px;top:10px}',
            '#rpLightboxBody{padding:14px;background:rgba(0,0,0,.22);overflow:auto;-webkit-overflow-scrolling:touch;cursor:default}',
            // Scrollable canvas (sized dynamically on open)
            '#rpLightboxStage{position:relative;width:100%;height:60vh;max-width:none;max-height:none;margin:0 auto}',
            '#rpLightboxImg{position:absolute;left:0;top:0;max-width:none;max-height:none;border-radius:14px;border:1px solid rgba(255,255,255,.10);will-change:transform;user-select:none;-webkit-user-drag:none}',
            '#rpLightboxNav{position:absolute;inset:0;pointer-events:none}',
            '.rpLightboxArrow{position:absolute;top:50%;transform:translateY(-50%);pointer-events:auto;display:inline-grid;place-items:center;width:44px;height:44px;border-radius:999px;border:1px solid rgba(255,255,255,.18);background:rgba(15,23,42,.35);color:#E2E8F0;font-weight:900;font-size:26px;line-height:1;cursor:pointer;backdrop-filter:blur(4px)}',
            '.rpLightboxArrow:hover{background:rgba(15,23,42,.55)}',
            '.rpLightboxArrow:disabled{opacity:.25;cursor:not-allowed}',
            '#rpLightboxPrev{left:14px}',
            '#rpLightboxNext{right:14px}',
            'img[data-lightbox]{cursor:zoom-in}'
        ].join('');
        document.head.appendChild(style);

        var backdrop = document.createElement('div');
        backdrop.id = 'rpLightboxBackdrop';
        backdrop.setAttribute('aria-hidden', 'true');
        backdrop.innerHTML = '' +
            '<div id="rpLightboxModal" role="dialog" aria-modal="true" aria-labelledby="rpLightboxTitle">' +
            '  <div id="rpLightboxHead">' +
            '    <button type="button" class="rpLightboxBtn" id="rpLightboxBack">Back</button>' +
            '    <button type="button" class="rpLightboxBtn" id="rpLightboxZoomOut" aria-label="Zoom out">−</button>' +
            '    <button type="button" class="rpLightboxBtn" id="rpLightboxZoomIn" aria-label="Zoom in">+</button>' +
            '    <button type="button" class="rpLightboxBtn" id="rpLightboxReset" aria-label="Reset zoom">Reset</button>' +
            '    <div id="rpLightboxTitle">Preview</div>' +
            '    <button type="button" class="rpLightboxBtn rpLightboxExitX" id="rpLightboxExitX" aria-label="Exit">&times;</button>' +
            '  </div>' +
            '  <div id="rpLightboxBody"><div id="rpLightboxStage"><img id="rpLightboxImg" alt="Preview"><div id="rpLightboxNav" aria-hidden="true"><button type="button" class="rpLightboxArrow" id="rpLightboxPrev" aria-label="Previous photo">‹</button><button type="button" class="rpLightboxArrow" id="rpLightboxNext" aria-label="Next photo">›</button></div></div></div>' +
            '</div>';
        document.body.appendChild(backdrop);

        var exitXBtn = document.getElementById('rpLightboxExitX');
        var backBtn = document.getElementById('rpLightboxBack');
        var zoomInBtn = document.getElementById('rpLightboxZoomIn');
        var zoomOutBtn = document.getElementById('rpLightboxZoomOut');
        var resetBtn = document.getElementById('rpLightboxReset');
        var prevBtn = document.getElementById('rpLightboxPrev');
        var nextBtn = document.getElementById('rpLightboxNext');
        var body = document.getElementById('rpLightboxBody');
        var img = document.getElementById('rpLightboxImg');
        var titleEl = document.getElementById('rpLightboxTitle');

        function setTitle() {
            if (!titleEl) return;
            var base = titleEl.getAttribute('data-base') || titleEl.textContent || 'Preview';
            if (state.items && state.items.length > 1) {
                titleEl.textContent = base + ' (' + (state.index + 1) + '/' + state.items.length + ')';
            } else {
                titleEl.textContent = base;
            }
        }

        function updateNav() {
            var n = state.items ? state.items.length : 0;
            if (!prevBtn || !nextBtn) return;
            var show = n > 1;
            prevBtn.style.display = show ? 'inline-grid' : 'none';
            nextBtn.style.display = show ? 'inline-grid' : 'none';
            prevBtn.disabled = !show;
            nextBtn.disabled = !show;
            setTitle();
        }

        function showAt(nextIndex) {
            var items = state.items || [];
            if (!items.length) return;
            state.index = ((nextIndex % items.length) + items.length) % items.length;
            if (img) {
                resetTransform();
                img.onload = function () { fitStageToImage(); updateNav(); };
                img.src = items[state.index];
            }
            updateNav();
        }

        function close() {
            backdrop.classList.remove('open');
            backdrop.setAttribute('aria-hidden', 'true');
            if (img) img.removeAttribute('src');
            resetTransform();
            state.items = [];
            state.index = 0;
        }
        if (exitXBtn) exitXBtn.addEventListener('click', close);
        if (backBtn) backBtn.addEventListener('click', close);
        if (zoomInBtn) zoomInBtn.addEventListener('click', function () { setScale(state.scale + 0.25); });
        if (zoomOutBtn) zoomOutBtn.addEventListener('click', function () { setScale(state.scale - 0.25); });
        if (resetBtn) resetBtn.addEventListener('click', function () { resetTransform(); });
        if (prevBtn) prevBtn.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); showAt(state.index - 1); });
        if (nextBtn) nextBtn.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); showAt(state.index + 1); });
        backdrop.addEventListener('click', function (e) { if (e.target === backdrop) close(); });
        document.addEventListener('keydown', function (e) {
            if (!backdrop.classList.contains('open')) return;
            if (e.key === 'Escape') close();
            if (e.key === 'ArrowLeft') { showAt(state.index - 1); e.preventDefault(); }
            if (e.key === 'ArrowRight') { showAt(state.index + 1); e.preventDefault(); }
        });

        if (body && img) {
            // Wheel zoom (Ctrl not required)
            body.addEventListener('wheel', function (e) {
                if (!backdrop.classList.contains('open')) return;
                // Allow normal scrolling when not zoomed
                if (state.scale === 1 && Math.abs(e.deltaY) < 2) return;
                e.preventDefault();
                var dir = e.deltaY > 0 ? -1 : 1;
                var step = 0.15;
                setScale(state.scale + dir * step, e.clientX, e.clientY);
            }, { passive: false });

            // Drag to pan (translate) while zoomed
            img.addEventListener('pointerdown', function (e) {
                if (!backdrop.classList.contains('open')) return;
                if (state.scale <= 1) return;
                state.dragging = true;
                state.dragStartX = e.clientX;
                state.dragStartY = e.clientY;
                state.dragStartTx = state.tx;
                state.dragStartTy = state.ty;
                img.setPointerCapture && img.setPointerCapture(e.pointerId);
                applyTransform();
                e.preventDefault();
            });
            img.addEventListener('pointermove', function (e) {
                if (!state.dragging) return;
                state.tx = state.dragStartTx + (e.clientX - state.dragStartX);
                state.ty = state.dragStartTy + (e.clientY - state.dragStartY);
                applyTransform();
            });
            function endDrag() {
                if (!state.dragging) return;
                state.dragging = false;
                applyTransform();
            }
            img.addEventListener('pointerup', endDrag);
            img.addEventListener('pointercancel', endDrag);

            // Double click toggles zoom
            img.addEventListener('dblclick', function (e) {
                if (!backdrop.classList.contains('open')) return;
                e.preventDefault();
                if (state.scale > 1) resetTransform();
                else setScale(2, e.clientX, e.clientY);
            });
        }

        backdrop.__close = close;
        backdrop.__showAt = showAt;
        backdrop.__updateNav = updateNav;
        return backdrop;
    }

    function open(src, title, items, index) {
        if (!src && !(items && items.length)) return;
        var backdrop = ensureModal();
        var img = document.getElementById('rpLightboxImg');
        var titleEl = document.getElementById('rpLightboxTitle');
        if (img) {
            // Start clean before load
            resetTransform();
            img.onload = function () { fitStageToImage(); };
        }
        if (titleEl) {
            titleEl.textContent = title || 'Preview';
            titleEl.setAttribute('data-base', title || 'Preview');
        }
        backdrop.classList.add('open');
        backdrop.setAttribute('aria-hidden', 'false');

        state.items = Array.isArray(items) ? items.slice() : (src ? [src] : []);
        state.index = typeof index === 'number' ? index : 0;

        if (backdrop.__updateNav) backdrop.__updateNav();
        if (backdrop.__showAt && state.items.length) backdrop.__showAt(state.index);
        else if (img && src) img.src = src;

        // If the image is cached and load doesn't fire, still fit on next tick
        setTimeout(function () {
            fitStageToImage();
            if (backdrop.__updateNav) backdrop.__updateNav();
        }, 0);
    }

    function uniq(arr) {
        var out = [];
        var seen = {};
        for (var i = 0; i < arr.length; i++) {
            var v = String(arr[i] || '');
            if (!v) continue;
            if (seen[v]) continue;
            seen[v] = 1;
            out.push(v);
        }
        return out;
    }

    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.closest) return;
        var img = t.closest('img[data-lightbox]');
        if (!img) return;

        // Avoid conflicts with buttons/links: only handle if target itself is the img (or inside it)
        if (img.tagName !== 'IMG') return;

        var src = img.getAttribute('data-lightbox-src') || img.currentSrc || img.getAttribute('src') || '';
        if (!src) return;

        // Prevent parent handlers (e.g., clicking a card opening another modal)
        e.preventDefault();
        e.stopPropagation();

        var title = img.getAttribute('data-lightbox-title') || img.getAttribute('alt') || 'Property photo';

        var group = img.getAttribute('data-lightbox') || '';
        var nodes = group ? document.querySelectorAll('img[data-lightbox="' + group.replace(/"/g, '\\"') + '"]') : [];
        var items = [];
        if (nodes && nodes.length) {
            for (var i = 0; i < nodes.length; i++) {
                var el = nodes[i];
                var s = el.getAttribute('data-lightbox-src') || el.currentSrc || el.getAttribute('src') || '';
                if (s) items.push(s);
            }
        }
        items = uniq(items);
        var index = 0;
        if (items.length) {
            var found = items.indexOf(src);
            index = found >= 0 ? found : 0;
        }

        open(src, title, items, index);
    }, true);
})();

