const learnerImageSelector = '[data-learner-image-viewer] img';

function bootLearnerImageViewer() {
    if (window.__medlearnLearnerImageViewer) return;
    window.__medlearnLearnerImageViewer = true;

    const viewer = document.createElement('div');
    viewer.className = 'learner-image-viewer';
    viewer.innerHTML = `
        <div class="learner-image-viewer__backdrop" data-viewer-close></div>
        <div class="learner-image-viewer__toolbar" role="toolbar" aria-label="Điều khiển ảnh">
            <button type="button" data-viewer-zoom-out aria-label="Thu nhỏ ảnh">−</button>
            <span data-viewer-scale>100%</span>
            <button type="button" data-viewer-zoom-in aria-label="Phóng to ảnh">+</button>
            <button type="button" data-viewer-reset>Đặt lại</button>
        </div>
        <div class="learner-image-viewer__viewport">
            <img class="learner-image-viewer__image" alt="Ảnh phóng to" draggable="false">
        </div>`;
    document.body.appendChild(viewer);

    const image = viewer.querySelector('.learner-image-viewer__image');
    const scaleLabel = viewer.querySelector('[data-viewer-scale]');
    let scale = 1;
    let offsetX = 0;
    let offsetY = 0;
    let dragging = false;
    let startX = 0;
    let startY = 0;

    const render = () => {
        image.style.transform = `translate(${offsetX}px, ${offsetY}px) scale(${scale})`;
        scaleLabel.textContent = `${Math.round(scale * 100)}%`;
        image.classList.toggle('is-zoomed', scale > 1);
    };
    const reset = () => { scale = 1; offsetX = 0; offsetY = 0; render(); };
    const close = () => {
        viewer.classList.remove('is-open');
        document.body.classList.remove('learner-image-viewer-open');
        image.removeAttribute('src');
        reset();
    };
    const open = (source) => {
        image.src = source.currentSrc || source.src;
        image.alt = source.alt || 'Ảnh phóng to';
        viewer.classList.add('is-open');
        document.body.classList.add('learner-image-viewer-open');
        reset();
    };

    document.addEventListener('click', (event) => {
        const source = event.target.closest?.(learnerImageSelector);
        if (!source || viewer.contains(source)) return;
        event.preventDefault();
        open(source);
    });
    viewer.addEventListener('click', (event) => {
        if (event.target.closest('[data-viewer-close]')) {
            close();
            return;
        }

        // Close when clicking anywhere outside the image itself, including
        // the empty area around a zoomed image.
        if (!event.target.closest('.learner-image-viewer__image, .learner-image-viewer__toolbar')) {
            close();
        }
    });
    viewer.querySelector('[data-viewer-zoom-in]').addEventListener('click', () => { scale = Math.min(5, scale + 0.25); render(); });
    viewer.querySelector('[data-viewer-zoom-out]').addEventListener('click', () => { scale = Math.max(0.5, scale - 0.25); if (scale === 1) { offsetX = 0; offsetY = 0; } render(); });
    viewer.querySelector('[data-viewer-reset]').addEventListener('click', reset);
    viewer.querySelector('.learner-image-viewer__viewport').addEventListener('wheel', (event) => {
        event.preventDefault();
        scale = Math.min(5, Math.max(0.5, scale + (event.deltaY < 0 ? 0.15 : -0.15)));
        if (scale === 1) { offsetX = 0; offsetY = 0; }
        render();
    }, { passive: false });
    image.addEventListener('pointerdown', (event) => {
        if (scale <= 1) return;
        dragging = true; startX = event.clientX - offsetX; startY = event.clientY - offsetY;
        image.setPointerCapture(event.pointerId);
    });
    image.addEventListener('pointermove', (event) => {
        if (!dragging) return;
        offsetX = event.clientX - startX; offsetY = event.clientY - startY; render();
    });
    image.addEventListener('pointerup', () => { dragging = false; });
    image.addEventListener('pointercancel', () => { dragging = false; });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && viewer.classList.contains('is-open')) close();
    });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bootLearnerImageViewer, { once: true });
else bootLearnerImageViewer();
