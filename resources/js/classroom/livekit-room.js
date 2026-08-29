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
            videoEncoding: VideoPresets.h540.encoding,
            screenShareEncoding: ScreenSharePresets.h720fps15.encoding,
            dtx: true,
            red: true,
            forceStereo: false,
        },
        videoCaptureDefaults: {
            resolution: VideoPresets.h540.resolution,
        },
    },
    cameraCapture: {
        resolution: VideoPresets.h540.resolution,
    },
    cameraPublish: {
        simulcast: true,
        videoEncoding: VideoPresets.h540.encoding,
    },
    screenCapture: {
        resolution: ScreenSharePresets.h720fps15.resolution,
        contentHint: /** @type {const} */ ('detail'),
        selfBrowserSurface: /** @type {const} */ ('exclude'),
    },
    screenPublish: {
        simulcast: true,
        videoCodec: /** @type {const} */ ('vp8'),
        videoEncoding: ScreenSharePresets.h720fps15.encoding,
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
    const studioMode = root.dataset.lkStudio === '1';
    let liveConfig = {};
    try {
        liveConfig = JSON.parse(root.dataset.liveConfig ?? '{}');
    } catch {
        liveConfig = {};
    }
    const legacyPublisher = role === 'publisher' && (studioMode || Boolean(liveConfig.can_moderate));
    const canPublishAudio = root.dataset.lkCanAudio === '1' || legacyPublisher;
    const canPublishVideo = root.dataset.lkCanVideo === '1' || legacyPublisher;
    const canPublishScreen = root.dataset.lkCanScreen === '1' || legacyPublisher;
    const canPublish = canPublishAudio || canPublishVideo || canPublishScreen;
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
    const participantListEls = root.querySelectorAll('[data-lk-participants]');
    const participantEmptyEls = root.querySelectorAll('[data-lk-participants-empty]');
    const participantCountEls = root.querySelectorAll('[data-lk-participant-count]');

    /** @type {Room|null} */
    let room = null;
    let camOn = false;
    let micOn = false;
    let screenOn = false;
    let disposed = false;
    let hasRemoteVideo = false;
    let mainVideoPriority = 0;
    let connectRetryTimer = null;
    let connecting = false;
    let leaving = false;

    const MAX_CONNECT_ATTEMPTS = 4;

    const isRoomUsable = () => ! disposed && room?.state === 'connected';

    const isClosedSocketError = (error) => String(error?.message ?? error ?? '')
        .toLowerCase()
        .includes('websocket is already in closing or closed state');

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        ?? String(liveConfig.csrf ?? '');

    const userIdFromIdentity = (identity) => {
        const match = String(identity).match(/^user-(\d+)$/);

        return match ? Number(match[1]) : null;
    };

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

    const leaveRoom = (urlTo = exitUrl) => {
        disposed = true;
        leaving = true;
        if (connectRetryTimer !== null) {
            window.clearTimeout(connectRetryTimer);
            connectRetryTimer = null;
        }
        // Do not request leave while the SDK is automatically reconnecting.
        if (room?.state === 'connected') {
            try {
                room.disconnect();
            } catch {
                // Navigation still proceeds.
            }
        }
        window.location.href = urlTo;
    };

    const renderParticipants = () => {
        if (! room || participantListEls.length === 0) {
            return;
        }

        const participants = Array.from(room.remoteParticipants.values())
            .map((participant) => ({
                identity: participant.identity,
                userId: userIdFromIdentity(participant.identity),
                name: participant.name || participant.identity,
            }))
            .filter((participant) => participant.userId !== null);

        participantCountEls.forEach((el) => {
            el.textContent = String(participants.length);
        });
        participantEmptyEls.forEach((el) => {
            el.classList.toggle('hidden', participants.length > 0);
        });

        participantListEls.forEach((list) => {
            list.innerHTML = '';
            participants.forEach((participant) => {
                const item = document.createElement('li');
                item.className = 'flex items-center justify-between gap-3 px-4 py-3 text-sm';

                const name = document.createElement('span');
                name.className = 'min-w-0 truncate text-on-surface';
                name.textContent = participant.name;

                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'shrink-0 rounded-lg border border-error px-2 py-1 text-xs font-semibold text-error hover:bg-error/5';
                button.textContent = 'Kick';
                button.addEventListener('click', async () => {
                    await kickParticipant(participant.userId, participant.identity, button);
                });

                item.append(name, button);
                list.appendChild(item);
            });
        });
    };

    const kickParticipant = async (userId, identity, button) => {
        const template = String(liveConfig.kick_member_url_template ?? liveConfig.ban_member_url_template ?? '');
        if (! template || ! userId || ! isRoomUsable()) {
            return;
        }

        button.disabled = true;
        button.textContent = 'Đang kick...';

        try {
            const res = await fetch(template.replace('__USER__', String(userId)), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                },
                credentials: 'same-origin',
            });

            if (! res.ok) {
                throw new Error('Không kick được học viên.');
            }

            const payload = JSON.stringify({
                type: 'kick',
                user_id: userId,
                redirect_url: String(liveConfig.exit_url ?? '/classes'),
            });
            if (isRoomUsable()) {
                await room.localParticipant.publishData(new TextEncoder().encode(payload), {
                    reliable: true,
                    topic: 'moderation',
                    destinationIdentities: [identity],
                });
            }

            button.textContent = 'Đã kick';
        } catch (e) {
            if (isClosedSocketError(e)) {
                return;
            }
            console.error('[LiveKit] kick participant', e);
            button.disabled = false;
            button.textContent = 'Kick';
            setError(e instanceof Error ? e.message : 'Không kick được học viên.');
        }
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
            btnMic.disabled = ! canPublishAudio || ! room;
            btnMic.classList.toggle('hidden', ! canPublishAudio);
        }
        if (btnCam) {
            btnCam.classList.toggle('text-red-300', ! camOn);
            btnCam.classList.toggle('text-white', camOn);
            const icon = btnCam.querySelector('.material-symbols-outlined');
            if (icon) {
                icon.textContent = camOn ? 'videocam' : 'videocam_off';
            }
            btnCam.setAttribute('aria-label', camOn ? 'Tắt camera' : 'Bật camera');
            btnCam.disabled = ! canPublishVideo || ! room;
            btnCam.classList.toggle('hidden', ! canPublishVideo);
        }
        if (btnScreen) {
            btnScreen.classList.toggle('text-teal-300', screenOn);
            btnScreen.disabled = ! canPublishScreen || ! room;
            btnScreen.classList.toggle('hidden', ! canPublishScreen);
        }
        if (controlsEl) {
            controlsEl.classList.toggle('hidden', ! canPublish);
            controlsEl.classList.toggle('flex', canPublish);
        }
        if (waitingEl) {
            const showWait = ! canPublishVideo && ! hasRemoteVideo && ! isStageTeach();
            waitingEl.classList.toggle('hidden', ! showWait);
        }
        if (btnTeach) {
            let hasQuestions = false;
            try {
                hasQuestions = Boolean(JSON.parse(root.dataset.liveConfig ?? '{}').has_questions);
            } catch {
                // ignore
            }
            btnTeach.classList.toggle('hidden', ! Boolean(liveConfig.can_moderate) || ! room || ! hasQuestions);
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

    const isStageTeach = () => root.dataset.lkStageTeach === '1';

    const pipClassName = () => 'absolute bottom-3 right-3 z-20 h-28 w-40 rounded-lg border border-white/30 object-cover shadow-lg sm:h-36 sm:w-52';

    /**
     * @param {boolean} isLocal
     * @param {boolean} isScreen
     */
    const shouldPipCamera = (isLocal, isScreen) => {
        if (isScreen) {
            return false;
        }
        if (isStageTeach()) {
            return true;
        }

        return isLocal && ! studioMode;
    };

    const relayoutPublishedVideos = () => {
        if (! room || ! stageEl) {
            return;
        }

        const camPub = room.localParticipant.getTrackPublication(Track.Source.Camera);
        if (camPub?.track) {
            attachTrack(camPub.track, 'local', true);
        }

        for (const participant of room.remoteParticipants.values()) {
            for (const publication of participant.trackPublications.values()) {
                if (publication.track && publication.track.kind === Track.Kind.Video) {
                    attachTrack(publication.track, participant.identity, false);
                }
            }
        }

        if (isStageTeach() && ! screenOn) {
            const main = stageEl.querySelector('[data-lk-main]');
            if (main instanceof HTMLElement) {
                // Keep shell; clear fullscreen camera so đề overlay is the focus.
                const hasScreenPlaceholder = main.querySelector('.material-symbols-outlined')?.textContent === 'screen_share';
                if (! hasScreenPlaceholder) {
                    main.replaceChildren();
                }
            }
        }
    };

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
            const videoPriority = isScreen ? 2 : 1;

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

            if (! isLocal && ! isStageTeach() && videoPriority < mainVideoPriority) {
                return;
            }

            const el = track.attach();
            el.dataset.lkVideo = key;
            el.playsInline = true;
            el.autoplay = true;
            el.muted = isLocal;

            if (shouldPipCamera(isLocal, isScreen)) {
                el.className = pipClassName();
                stageEl.appendChild(el);
            } else {
                mainVideoPriority = videoPriority;
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

    const ensureLocalMedia = async (targetRoom) => {
        if (disposed || targetRoom?.state !== 'connected') {
            return;
        }

        if (canPublishVideo) {
            try {
                await targetRoom.localParticipant.setCameraEnabled(
                    true,
                    profile.cameraCapture,
                    profile.cameraPublish,
                );
                camOn = true;
                const camPub = targetRoom.localParticipant.getTrackPublication(Track.Source.Camera);
                if (camPub?.track) {
                    attachTrack(camPub.track, 'local', true);
                }
            } catch (e) {
                if (! isClosedSocketError(e) && ! disposed) {
                    console.error('[LiveKit] camera', e);
                    setError(e instanceof Error ? e.message : 'Không bật được camera');
                }
            }
        }

        if (canPublishAudio && (canPublishVideo || studioMode || Boolean(liveConfig.can_moderate))) {
            try {
                await targetRoom.localParticipant.setMicrophoneEnabled(true);
                micOn = true;
            } catch (e) {
                if (! isClosedSocketError(e) && ! disposed) {
                    console.error('[LiveKit] mic', e);
                    setError(e instanceof Error ? e.message : 'Không bật được micro');
                }
            }
        }

        syncButtons();
    };

    const connect = async (attempt = 1) => {
        if (disposed || connecting) {
            return;
        }

        const currentState = String(room?.state ?? 'disconnected');
        if (currentState === 'connected' || currentState === 'connecting' || currentState === 'reconnecting') {
            return;
        }

        if (! url || ! token) {
            setStatus('Thiếu cấu hình LiveKit', 'error');
            setError('Thiếu LIVEKIT_URL hoặc token.');

            return;
        }

        connecting = true;
        setStatus(attempt > 1 ? `Đang kết nối lại (${attempt}/${MAX_CONNECT_ATTEMPTS})…` : 'Đang kết nối…', 'connecting');
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
                if (disposed || leaving) {
                    return;
                }
                setStatus('Đã ngắt', 'error');
                camOn = false;
                micOn = false;
                screenOn = false;
                syncButtons();

                if (room === next && connectRetryTimer === null) {
                    room = null;
                    connectRetryTimer = window.setTimeout(() => {
                        connectRetryTimer = null;
                        connect(1);
                    }, 1_000);
                }
            })
            .on(RoomEvent.Reconnecting, () => setStatus('Đang kết nối lại…', 'connecting'))
            .on(RoomEvent.Reconnected, () => {
                setStatus('Đã kết nối', 'connected');
                syncButtons();
                void ensureLocalMedia(next);
            })
            .on(RoomEvent.ParticipantConnected, () => {
                refreshCount();
                renderParticipants();
                syncButtons();
            })
            .on(RoomEvent.ParticipantDisconnected, () => {
                refreshCount();
                renderParticipants();
                syncButtons();
            })
            .on(RoomEvent.DataReceived, (payload, _participant, _kind, topic) => {
                if (topic !== 'moderation') {
                    return;
                }

                try {
                    const message = JSON.parse(new TextDecoder().decode(payload));
                    if (message.type === 'kick' && Number(message.user_id) === Number(liveConfig.user_id)) {
                        leaveRoom(String(message.redirect_url ?? liveConfig.exit_url ?? '/classes'));
                    }
                } catch {
                    // ignore malformed data messages
                }
            })
            .on(RoomEvent.TrackSubscribed, (track, _pub, participant) => {
                attachTrack(track, participant.identity, false);
                syncButtons();
            })
            .on(RoomEvent.TrackUnsubscribed, (track) => {
                track.detach().forEach((el) => el.remove());
                if (track.kind === Track.Kind.Video) {
                    hasRemoteVideo = stageEl?.querySelector('[data-lk-main] video') !== null;
                    if (! hasRemoteVideo) {
                        mainVideoPriority = 0;
                    }
                }
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
                if (next.state === 'connected') {
                    next.disconnect();
                }

                return;
            }

            setStatus('Đã kết nối', 'connected');
            refreshCount();
            renderParticipants();

            for (const participant of next.remoteParticipants.values()) {
                for (const publication of participant.trackPublications.values()) {
                    if (publication.track) {
                        attachTrack(publication.track, participant.identity, false);
                    }
                }
            }

            await ensureLocalMedia(next);
        } catch (e) {
            console.error('[LiveKit] connect', e);
            // The SDK may have entered its own reconnect cycle. Calling
            // disconnect here sends a leave request to an already-closing socket.
            if (room === next && String(next.state) === 'disconnected') {
                room = null;
            }

            if (! disposed && String(next.state) === 'disconnected' && attempt < MAX_CONNECT_ATTEMPTS) {
                const delay = Math.min(1_000 * (2 ** (attempt - 1)), 5_000);
                const detail = e instanceof Error ? e.message : String(e ?? '');
                const hint = /failed to fetch|connection refused|err_connection|websocket/i.test(detail)
                    ? ' Máy chủ LiveKit có thể chưa chạy (docker compose up -d livekit).'
                    : '';
                setStatus('LiveKit đang khởi động…', 'connecting');
                setError(`Chưa kết nối được phòng live. Hệ thống sẽ thử lại sau ${delay / 1_000} giây.${hint}`);
                connectRetryTimer = window.setTimeout(() => {
                    connectRetryTimer = null;
                    connect(attempt + 1);
                }, delay);
            } else if (! disposed) {
                setStatus('Lỗi kết nối', 'error');
                setError('Không kết nối được máy chủ phòng live. Kiểm tra LiveKit đang chạy rồi tải lại trang.');
            }
            syncButtons();
        } finally {
            connecting = false;
        }
    };

    const onMic = async () => {
        if (! isRoomUsable() || ! canPublishAudio) {
            return;
        }
        try {
            const next = ! micOn;
            await room.localParticipant.setMicrophoneEnabled(next);
            micOn = next;
            setError('');
            syncButtons();
        } catch (e) {
            if (isClosedSocketError(e)) {
                return;
            }
            console.error('[LiveKit] mic toggle', e);
            setError(e instanceof Error ? e.message : 'Lỗi micro');
        }
    };

    const onCam = async () => {
        if (! isRoomUsable() || ! canPublishVideo) {
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
            if (isClosedSocketError(e)) {
                return;
            }
            console.error('[LiveKit] cam toggle', e);
            setError(e instanceof Error ? e.message : 'Lỗi camera');
        }
    };

    const onScreen = async () => {
        if (! isRoomUsable() || ! canPublishScreen) {
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
                if (studioMode) {
                    const camPub = room.localParticipant.getTrackPublication(Track.Source.Camera);
                    if (camPub?.track) {
                        attachTrack(camPub.track, 'local', true);
                    }
                }
            }
            screenOn = next;
            if (! next) {
                setError('');
            }
            syncButtons();
        } catch (e) {
            if (isClosedSocketError(e)) {
                return;
            }
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
        leaveRoom(exitUrl);
    };

    btnLeave?.addEventListener('click', onLeave);

    const onStageTeach = () => {
        relayoutPublishedVideos();
        syncButtons();
    };
    root.addEventListener('live:stage-teach', onStageTeach);

    // Bootstrap may have set stage teach before LiveKit mounted.
    if (isStageTeach()) {
        onStageTeach();
    }

    syncButtons();
    connect();

    const teardown = () => {
        if (disposed) {
            return;
        }
        disposed = true;
        leaving = true;
        if (connectRetryTimer !== null) {
            window.clearTimeout(connectRetryTimer);
            connectRetryTimer = null;
        }
        root.removeEventListener('live:stage-teach', onStageTeach);
        btnMic?.removeEventListener('click', onMic);
        btnCam?.removeEventListener('click', onCam);
        btnScreen?.removeEventListener('click', onScreen);
        btnLeave?.removeEventListener('click', onLeave);
        if (room?.state === 'connected') {
            try {
                room.disconnect();
            } catch {
                // Ignore an already-closing transport.
            }
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
