import {
    Room,
    RoomEvent,
    Track,
    VideoPresets,
    ScreenSharePresets,
} from 'livekit-client';

/**
 * Detect LiveKit Cloud / remote SFU (wss). Local Docker/native stays on ws://127.0.0.1.
 * @param {string} url
 */
function isRemoteLiveKit(url) {
    try {
        const u = new URL(url);
        return u.protocol === 'wss:'
            || u.hostname.endsWith('.livekit.cloud')
            || u.hostname.includes('livekit.cloud');
    } catch {
        return false;
    }
}

/**
 * Local/Docker-friendly publish profile.
 * Simulcast + 720p30 through Docker Desktop (often TCP) causes heavy lag.
 */
const LOCAL_PROFILE = {
    room: {
        adaptiveStream: false,
        dynacast: false,
        publishDefaults: {
            simulcast: false,
            videoCodec: /** @type {const} */ ('vp8'),
            videoEncoding: {
                maxBitrate: 600_000,
                maxFramerate: 20,
            },
            screenShareEncoding: {
                maxBitrate: 1_000_000,
                maxFramerate: 12,
            },
            dtx: true,
            red: true,
            forceStereo: false,
        },
        videoCaptureDefaults: {
            resolution: VideoPresets.h360.resolution,
        },
    },
    cameraCapture: {
        resolution: VideoPresets.h360.resolution,
    },
    cameraPublish: {
        simulcast: false,
        videoEncoding: {
            maxBitrate: 600_000,
            maxFramerate: 20,
        },
    },
    screenCapture: {
        resolution: ScreenSharePresets.h720fps15.resolution,
        contentHint: /** @type {const} */ ('detail'),
        // Tránh host chọn nhầm tab live → loop vô hạn.
        selfBrowserSurface: /** @type {const} */ ('exclude'),
    },
    screenPublish: {
        simulcast: false,
        videoCodec: /** @type {const} */ ('vp8'),
        videoEncoding: ScreenSharePresets.h720fps15.encoding,
    },
};

/** Cloud / production-quality profile (UDP edge network). */
const CLOUD_PROFILE = {
    room: {
        adaptiveStream: true,
        dynacast: true,
        publishDefaults: {
            simulcast: true,
            videoCodec: /** @type {const} */ ('vp8'),
            videoEncoding: VideoPresets.h720.encoding,
            screenShareEncoding: ScreenSharePresets.h1080fps15.encoding,
            dtx: true,
            red: true,
            forceStereo: false,
        },
        videoCaptureDefaults: {
            resolution: VideoPresets.h720.resolution,
        },
    },
    cameraCapture: {
        resolution: VideoPresets.h720.resolution,
    },
    cameraPublish: {
        simulcast: true,
        videoEncoding: VideoPresets.h720.encoding,
    },
    screenCapture: {
        resolution: ScreenSharePresets.h1080fps15.resolution,
        contentHint: /** @type {const} */ ('detail'),
        selfBrowserSurface: /** @type {const} */ ('exclude'),
    },
    screenPublish: {
        simulcast: true,
        videoCodec: /** @type {const} */ ('vp8'),
        videoEncoding: ScreenSharePresets.h1080fps15.encoding,
    },
};

/**
 * Vanilla LiveKit mount for Classroom live room.
 * Avoid Alpine — its destroy/init cycle causes CLIENT_REQUEST_LEAVE reconnect loops.
 *
 * @param {HTMLElement} root
 */
export function mountLivekitRoom(root) {
    if (! (root instanceof HTMLElement)) {
        return () => {};
    }

    if (root.dataset.lkMounted === '1') {
        return () => {};
    }
    root.dataset.lkMounted = '1';

    const url = root.dataset.lkUrl ?? '';
    const token = root.dataset.lkToken ?? '';
    const role = root.dataset.lkRole ?? 'subscriber';
    const roomName = root.dataset.lkRoom ?? '';
    const canPublish = role === 'publisher';
    const profile = isRemoteLiveKit(url) ? CLOUD_PROFILE : LOCAL_PROFILE;

    const statusEl = root.querySelector('[data-lk-status]');
    const errorEl = root.querySelector('[data-lk-error]');
    const countEl = root.querySelector('[data-lk-count]');
    const stageEl = root.querySelector('[data-lk-stage]');
    const waitingEl = root.querySelector('[data-lk-waiting]');
    const btnMic = root.querySelector('[data-lk-mic]');
    const btnCam = root.querySelector('[data-lk-cam]');
    const btnScreen = root.querySelector('[data-lk-screen]');
    const btnLeave = root.querySelector('[data-lk-leave]');
    const btnTeach = root.querySelector('[data-lk-teach]');
    const controlsEl = root.querySelector('[data-lk-controls]');
    const exitUrl = root.dataset.lkExitUrl ?? '/';

    /** @type {Room|null} */
    let room = null;
    let camOn = false;
    let micOn = false;
    let screenOn = false;
    let disposed = false;
    let hasRemoteVideo = false;

    const setStatus = (text, kind = 'connecting') => {
        if (statusEl) {
            statusEl.textContent = text;
            statusEl.dataset.kind = kind;
        }
        const dot = root.querySelector('[data-lk-dot]');
        if (dot) {
            dot.className = 'size-1.5 rounded-full '
                + (kind === 'connected' ? 'bg-green-400'
                    : kind === 'error' ? 'bg-red-400' : 'bg-amber-400');
        }
    };

    const setError = (msg) => {
        if (! errorEl) {
            return;
        }
        if (! msg) {
            errorEl.classList.add('hidden');
            errorEl.textContent = '';

            return;
        }
        errorEl.classList.remove('hidden');
        errorEl.textContent = msg;
    };

    const refreshCount = () => {
        if (! countEl || ! room) {
            return;
        }
        countEl.textContent = `${room.remoteParticipants.size + 1} người`;
    };

    const syncButtons = () => {
        if (btnMic) {
            btnMic.classList.toggle('text-red-300', ! micOn);
            btnMic.classList.toggle('text-white', micOn);
            const icon = btnMic.querySelector('.material-symbols-outlined');
            if (icon) {
                icon.textContent = micOn ? 'mic' : 'mic_off';
            }
            btnMic.setAttribute('aria-label', micOn ? 'Tắt micro' : 'Bật micro');
            btnMic.disabled = ! canPublish || ! room;
        }
        if (btnCam) {
            btnCam.classList.toggle('text-red-300', ! camOn);
            btnCam.classList.toggle('text-white', camOn);
            const icon = btnCam.querySelector('.material-symbols-outlined');
            if (icon) {
                icon.textContent = camOn ? 'videocam' : 'videocam_off';
            }
            btnCam.setAttribute('aria-label', camOn ? 'Tắt camera' : 'Bật camera');
            btnCam.disabled = ! canPublish || ! room;
        }
        if (btnScreen) {
            btnScreen.classList.toggle('text-teal-300', screenOn);
            btnScreen.disabled = ! canPublish || ! room;
        }
        if (controlsEl) {
            controlsEl.classList.toggle('hidden', ! canPublish);
        }
        if (waitingEl) {
            const showWait = ! canPublish && ! hasRemoteVideo;
            waitingEl.classList.toggle('hidden', ! showWait);
        }
        if (btnTeach) {
            let hasQuestions = false;
            try {
                hasQuestions = Boolean(JSON.parse(root.dataset.liveConfig ?? '{}').has_questions);
            } catch {
                // ignore
            }
            btnTeach.classList.toggle('hidden', ! canPublish || ! room || ! hasQuestions);
        }
    };

    const showScreenSharePlaceholder = () => {
        if (! stageEl) {
            return;
        }
        let main = stageEl.querySelector('[data-lk-main]');
        if (! main) {
            main = document.createElement('div');
            main.dataset.lkMain = '1';
            main.className = 'flex h-full w-full items-center justify-center';
            stageEl.prepend(main);
        }
        main.replaceChildren();
        const wrap = document.createElement('div');
        wrap.className = 'flex flex-col items-center gap-2 p-4 text-center text-sm text-white/70';
        wrap.innerHTML = `
            <span class="material-symbols-outlined text-4xl">screen_share</span>
            <p>Đang chia sẻ màn hình</p>
            <p class="max-w-xs text-xs text-white/50">Preview ẩn để tránh lặp vô hạn. Học viên vẫn thấy nội dung bạn share.</p>
        `;
        main.appendChild(wrap);
    };

    const isQuestionsTabVisible = () => Array.from(root.querySelectorAll('[data-live-question-panel]'))
        .some((panel) => panel instanceof HTMLElement && panel.offsetParent !== null);

    /**
     * @param {import('livekit-client').Track} track
     * @param {string} identity
     * @param {boolean} isLocal
     */
    const attachTrack = (track, identity, isLocal) => {
        if (! stageEl) {
            return;
        }

        if (track.kind === Track.Kind.Video && ! isLocal) {
            hasRemoteVideo = true;
        }

        if (track.kind === Track.Kind.Video) {
            const isScreen = track.source === Track.Source.ScreenShare
                || track.source === Track.Source.ScreenShareAudio;

            // Không gắn preview màn hình local — tránh loop khi share tab live.
            if (isLocal && isScreen) {
                showScreenSharePlaceholder();

                return;
            }

            // Local camera PiP vs screen/remote on main. Use distinct keys.
            const key = isLocal
                ? (isScreen ? 'local-screen' : 'local-cam')
                : identity;

            stageEl.querySelector(`[data-lk-video="${key}"]`)?.remove();

            const el = track.attach();
            el.dataset.lkVideo = key;
            el.playsInline = true;
            el.autoplay = true;
            el.muted = isLocal;

            if (isLocal && ! isScreen) {
                el.className = 'absolute bottom-3 right-3 z-10 h-28 w-40 rounded-lg border border-white/30 object-cover shadow-lg sm:h-36 sm:w-52';
                stageEl.appendChild(el);
            } else {
                el.className = 'h-full w-full object-contain';
                let main = stageEl.querySelector('[data-lk-main]');
                if (! main) {
                    main = document.createElement('div');
                    main.dataset.lkMain = '1';
                    main.className = 'flex h-full w-full items-center justify-center';
                    stageEl.prepend(main);
                }
                main.replaceChildren(el);
            }
        }

        if (track.kind === Track.Kind.Audio && ! isLocal) {
            stageEl.querySelector(`[data-lk-audio="${identity}"]`)?.remove();
            const el = track.attach();
            el.dataset.lkAudio = identity;
            stageEl.appendChild(el);
        }
    };

    const connect = async () => {
        if (disposed) {
            return;
        }

        if (! url || ! token) {
            setStatus('Thiếu cấu hình LiveKit', 'error');
            setError('Thiếu LIVEKIT_URL hoặc token.');

            return;
        }

        setStatus('Đang kết nối…', 'connecting');
        setError('');

        const next = new Room(profile.room);
        room = next;

        next
            .on(RoomEvent.Connected, () => {
                setStatus('Đã kết nối', 'connected');
                refreshCount();
                syncButtons();
            })
            .on(RoomEvent.Disconnected, () => {
                if (disposed) {
                    return;
                }
                setStatus('Đã ngắt', 'error');
                camOn = false;
                micOn = false;
                screenOn = false;
                syncButtons();
            })
            .on(RoomEvent.Reconnecting, () => setStatus('Đang kết nối lại…', 'connecting'))
            .on(RoomEvent.Reconnected, () => {
                setStatus('Đã kết nối', 'connected');
                syncButtons();
            })
            .on(RoomEvent.ParticipantConnected, () => {
                refreshCount();
                syncButtons();
            })
            .on(RoomEvent.ParticipantDisconnected, () => {
                refreshCount();
                syncButtons();
            })
            .on(RoomEvent.TrackSubscribed, (track, _pub, participant) => {
                attachTrack(track, participant.identity, false);
                syncButtons();
            })
            .on(RoomEvent.TrackUnsubscribed, (track) => {
                if (track.kind === Track.Kind.Video) {
                    hasRemoteVideo = stageEl?.querySelector('[data-lk-main] video') !== null;
                }
                track.detach().forEach((el) => el.remove());
                syncButtons();
            })
            .on(RoomEvent.LocalTrackPublished, (publication) => {
                if (publication.track) {
                    attachTrack(publication.track, 'local', true);
                }
            })
            .on(RoomEvent.LocalTrackUnpublished, (publication) => {
                if (publication.source === Track.Source.Camera) {
                    stageEl?.querySelector('[data-lk-video="local-cam"]')?.remove();
                }
                if (publication.source === Track.Source.ScreenShare) {
                    stageEl?.querySelector('[data-lk-main]')?.replaceChildren();
                }
                publication.track?.detach().forEach((el) => el.remove());
            });

        try {
            await next.connect(url, token, { peerConnectionTimeout: 45_000 });
            if (disposed) {
                next.disconnect();

                return;
            }

            setStatus('Đã kết nối', 'connected');
            refreshCount();

            for (const participant of next.remoteParticipants.values()) {
                for (const publication of participant.trackPublications.values()) {
                    if (publication.track) {
                        attachTrack(publication.track, participant.identity, false);
                    }
                }
            }

            if (canPublish) {
                try {
                    await next.localParticipant.setCameraEnabled(
                        true,
                        profile.cameraCapture,
                        profile.cameraPublish,
                    );
                    camOn = true;
                    const camPub = next.localParticipant.getTrackPublication(Track.Source.Camera);
                    if (camPub?.track) {
                        attachTrack(camPub.track, 'local', true);
                    }
                } catch (e) {
                    console.error('[LiveKit] camera', e);
                    setError(e instanceof Error ? e.message : 'Không bật được camera');
                }

                try {
                    await next.localParticipant.setMicrophoneEnabled(true);
                    micOn = true;
                } catch (e) {
                    console.error('[LiveKit] mic', e);
                    setError(e instanceof Error ? e.message : 'Không bật được micro');
                }
            }

            syncButtons();
        } catch (e) {
            console.error('[LiveKit] connect', e);
            setStatus('Lỗi kết nối', 'error');
            setError(e instanceof Error ? e.message : 'Không kết nối được LiveKit');
            room = null;
            syncButtons();
        }
    };

    const onMic = async () => {
        if (! room || ! canPublish) {
            return;
        }
        try {
            const next = ! micOn;
            await room.localParticipant.setMicrophoneEnabled(next);
            micOn = next;
            setError('');
            syncButtons();
        } catch (e) {
            console.error('[LiveKit] mic toggle', e);
            setError(e instanceof Error ? e.message : 'Lỗi micro');
        }
    };

    const onCam = async () => {
        if (! room || ! canPublish) {
            return;
        }
        try {
            const next = ! camOn;
            if (next) {
                await room.localParticipant.setCameraEnabled(
                    true,
                    profile.cameraCapture,
                    profile.cameraPublish,
                );
                // Re-enable thường chỉ unmute — LocalTrackPublished có thể không fire lại.
                const camPub = room.localParticipant.getTrackPublication(Track.Source.Camera);
                if (camPub?.track) {
                    attachTrack(camPub.track, 'local', true);
                }
            } else {
                const camPub = room.localParticipant.getTrackPublication(Track.Source.Camera);
                camPub?.track?.detach().forEach((el) => el.remove());
                stageEl?.querySelector('[data-lk-video="local-cam"]')?.remove();
                await room.localParticipant.setCameraEnabled(false);
            }
            camOn = next;
            setError('');
            syncButtons();
        } catch (e) {
            console.error('[LiveKit] cam toggle', e);
            setError(e instanceof Error ? e.message : 'Lỗi camera');
        }
    };

    const onScreen = async () => {
        if (! room || ! canPublish) {
            return;
        }
        try {
            const next = ! screenOn;
            if (next && isQuestionsTabVisible()) {
                setError('Tab Đề đang mở — học viên đã thấy đề đồng bộ. Chỉ share khi cần demo ngoài app (PDF, slide…).');
            }
            if (next) {
                await room.localParticipant.setScreenShareEnabled(
                    true,
                    profile.screenCapture,
                    profile.screenPublish,
                );
                showScreenSharePlaceholder();
            } else {
                await room.localParticipant.setScreenShareEnabled(false);
                stageEl?.querySelector('[data-lk-main]')?.replaceChildren();
            }
            screenOn = next;
            if (! next) {
                setError('');
            }
            syncButtons();
        } catch (e) {
            console.error('[LiveKit] screen', e);
            setError(e instanceof Error ? e.message : 'Lỗi chia sẻ màn hình');
            screenOn = false;
            syncButtons();
        }
    };

    btnMic?.addEventListener('click', onMic);
    btnCam?.addEventListener('click', onCam);
    btnScreen?.addEventListener('click', onScreen);

    const onLeave = () => {
        disposed = true;
        try {
            room?.disconnect();
        } catch {
            // ignore
        }
        window.location.href = exitUrl;
    };

    btnLeave?.addEventListener('click', onLeave);

    syncButtons();
    connect();

    const teardown = () => {
        if (disposed) {
            return;
        }
        disposed = true;
        btnMic?.removeEventListener('click', onMic);
        btnCam?.removeEventListener('click', onCam);
        btnScreen?.removeEventListener('click', onScreen);
        btnLeave?.removeEventListener('click', onLeave);
        try {
            room?.disconnect();
        } catch {
            // ignore
        }
        room = null;
        delete root.dataset.lkMounted;
    };

    window.addEventListener('pagehide', teardown, { once: true });

    return teardown;
}

export function bootLivekitRooms(root = document) {
    root.querySelectorAll('[data-lk-root]').forEach((el) => {
        if (el instanceof HTMLElement) {
            mountLivekitRoom(el);
        }
    });
}
