export function bootActivityHeartbeat() {
    const endpoint = document.querySelector('meta[name="activity-heartbeat-url"]')?.content;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    if (!endpoint || !csrfToken) return;

    const intervalSeconds = Math.max(60, Number(document.querySelector('meta[name="activity-heartbeat-seconds"]')?.content ?? 120));
    // One id per page visit: multiple heartbeats are aggregated, while a later
    // visit to the same area remains a separate activity session.
    const sessionId = crypto.randomUUID();

    const heartbeat = () => {
        if (document.visibilityState !== 'visible') return;

        fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ session_id: sessionId, area: window.location.pathname }),
        }).catch(() => null);
    };

    heartbeat();
    window.setInterval(heartbeat, intervalSeconds * 1000);
    document.addEventListener('visibilitychange', heartbeat);
}
