/**
 * Presence ping: max 2 requests per page view (start + leave). No interval, no dwell timing.
 */
export function bootActivityHeartbeat() {
    const endpoint = document.querySelector('meta[name="activity-heartbeat-url"]')?.content;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    if (! endpoint || ! csrfToken) {
        return;
    }

    const sessionId = crypto.randomUUID();
    const area = window.location.pathname;
    let closed = false;

    const post = (event) => {
        fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                session_id: sessionId,
                area,
                event,
            }),
        }).catch(() => null);
    };

    post('start');

    const leave = () => {
        if (closed) {
            return;
        }
        closed = true;
        post('leave');
    };

    window.addEventListener('pagehide', leave);
}
