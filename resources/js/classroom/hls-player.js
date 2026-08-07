/**
 * Minimal HLS player for ended live sessions.
 * @param {ParentNode} root
 */
export function mountHlsPlayers(root = document) {
    root.querySelectorAll('[data-hls-root]').forEach(async (container) => {
        if (! (container instanceof HTMLElement) || container.dataset.hlsMounted === '1') {
            return;
        }
        const url = container.dataset.hlsUrl;
        if (! url) {
            return;
        }

        container.dataset.hlsMounted = '1';
        const video = document.createElement('video');
        video.controls = true;
        video.playsInline = true;
        video.className = 'h-full w-full';
        container.appendChild(video);
        container.classList.remove('hidden');

        const placeholder = root.querySelector('[data-vod-placeholder]');
        placeholder?.classList.add('hidden');

        try {
            const Hls = (await import('hls.js')).default;
            if (Hls.isSupported()) {
                const hls = new Hls();
                hls.loadSource(url);
                hls.attachMedia(video);
            } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                video.src = url;
            }
        } catch {
            video.src = url;
        }
    });
}
