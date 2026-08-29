/**
 * Ensure Laravel Echo (Reverb) is ready for classroom realtime.
 * @returns {Promise<object|null>}
 */
export async function ensureClassroomEcho() {
    if (window.Echo) {
        return window.Echo;
    }

    if (typeof window.enableMedlearnRealtime === 'function') {
        try {
            return await window.enableMedlearnRealtime();
        } catch (err) {
            console.error('[Classroom] enableMedlearnRealtime', err);

            return null;
        }
    }

    try {
        await import('../echo');

        return window.Echo ?? null;
    } catch (err) {
        console.error('[Classroom] echo import', err);

        return null;
    }
}
