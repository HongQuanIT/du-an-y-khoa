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
        audioCaptureDefaults: {
            echoCancellation: true,
            noiseSuppression: true,
            autoGainControl: true,
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
        audioCaptureDefaults: {
            echoCancellation: true,
            noiseSuppression: true,
            autoGainControl: true,
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

const MAX_LEARNER_SPEAKERS = 2;

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
    const btnMics = root.querySelectorAll('[data-lk-mic]');
    const btnCams = root.querySelectorAll('[data-lk-cam]');
    const btnScreens = root.querySelectorAll('[data-lk-screen]');
    const btnTeach = root.querySelector('[data-lk-teach]');
    const controlsEls = root.querySelectorAll('[data-lk-controls]');
    const exitUrl = root.dataset.lkExitUrl ?? '/';
    const participantListEls = root.querySelectorAll('[data-lk-participants]');
    const participantEmptyEls = root.querySelectorAll('[data-lk-participants-empty]');
    const participantCountEls = root.querySelectorAll('[data-lk-participant-count]');

    /** @type {Room|null} */
    let room = null;
    let camOn = false;
    let micOn = false;
    let screenOn = false;
    let micLockedByHost = false;
    let disposed = false;
    let hasRemoteVideo = false;
    let mainVideoPriority = 0;
    let connectRetryTimer = null;
    let connecting = false;
    let leaving = false;
    /** @type {Set<number>} */
    const remoteMutedByHost = new Set();

    const MAX_CONNECT_ATTEMPTS = 4;
    const isLocalHost = Boolean(liveConfig.can_moderate) || role === 'publisher';
    const currentUserId = liveConfig.user_id != null ? Number(liveConfig.user_id) : null;

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

    const parseParticipantMeta = (participant) => {
        try {
            const raw = participant?.metadata;
            if (! raw) {
                return {};
            }

            return JSON.parse(raw);
        } catch {
            return {};
        }
    };

    const isHostParticipant = (participant) => {
        const meta = parseParticipantMeta(participant);
        if (meta.is_host === true) {
            return true;
        }
        // Fallback: host thường publish camera/screen.
        const cam = participant.getTrackPublication?.(Track.Source.Camera);
        const screen = participant.getTrackPublication?.(Track.Source.ScreenShare);

        return Boolean(cam?.track || screen?.track);
    };

    const participantMicActive = (participant) => {
        const pub = participant.getTrackPublication?.(Track.Source.Microphone);
        if (! pub) {
            return false;
        }

        return ! pub.isMuted && (pub.track != null || pub.isSubscribed);
    };

    /** @returns {list<{userId: number, identity: string, since: number}>} */
    const listActiveLearnerMics = () => {
        if (! room) {
            return [];
        }
        /** @type {Array<{userId: number, identity: string}>} */
        const out = [];
        if (micOn && ! isLocalHost && currentUserId) {
            out.push({ userId: currentUserId, identity: `user-${currentUserId}` });
        }
        for (const participant of room.remoteParticipants.values()) {
            if (isHostParticipant(participant)) {
                continue;
            }
            const userId = userIdFromIdentity(participant.identity);
            if (userId === null || ! participantMicActive(participant)) {
                continue;
            }
            out.push({ userId, identity: participant.identity });
        }

        return out;
    };

    const showMicTip = (msg) => {
        setError(msg);
        window.setTimeout(() => {
            if (errorEl?.textContent === msg) {
                setError('');
            }
        }, 5_000);
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
        if (! room) {
            return;
        }
        const total = room.remoteParticipants.size + 1;
        if (countEl) {
            countEl.textContent = String(total);
        }
        document.querySelectorAll('[data-live-viewer-count-num]').forEach((el) => {
            el.textContent = String(total);
        });
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

    const speakerUrl = (kind, userId) => {
        const key = kind === 'invite'
            ? 'speakers_invite'
            : kind === 'unmute'
                ? 'speakers_unmute'
                : 'speakers_mute';
        const template = String(liveConfig.urls?.[key]
            ?? liveConfig[`${key}_url_template`]
            ?? '');
        if (! template) {
            const base = String(liveConfig.bootstrap_url ?? '').replace(/\/bootstrap\/?$/, '');

            return `${base}/speakers/${userId}/${kind === 'unmute' ? 'unmute' : kind === 'invite' ? 'invite' : 'mute'}`;
        }

        return template.replace('__USER__', String(userId));
    };

    const publishMicCommand = async (action, userId, identity) => {
        if (! isRoomUsable() || ! identity) {
            return;
        }
        const payload = JSON.stringify({
            type: 'mic',
            action,
            user_id: userId,
        });
        await room.localParticipant.publishData(new TextEncoder().encode(payload), {
            reliable: true,
            topic: 'moderation',
            destinationIdentities: [identity],
        });
    };

    const apiSpeakerAction = async (kind, userId) => {
        const res = await fetch(speakerUrl(kind, userId), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf(),
            },
            credentials: 'same-origin',
        });
        if (! res.ok) {
            throw new Error(`speaker ${kind} failed`);
        }

        return res.json();
    };

    const setLocalMicEnabled = async (enabled, { locked = null, invited = false, bypassFloor = false } = {}) => {
        if (! isRoomUsable() || ! canPublishAudio) {
            return false;
        }
        if (enabled && micLockedByHost && ! invited && locked !== false) {
            showMicTip('Host đã tắt mic của bạn. Chờ được gọi hoặc host bật lại.');

            return false;
        }
        if (enabled && ! isLocalHost && ! bypassFloor) {
            const active = listActiveLearnerMics().filter((s) => s.userId !== currentUserId);
            if (active.length >= MAX_LEARNER_SPEAKERS) {
                showMicTip('Đã đủ người đang nói. Host sẽ gọi khi đến lượt bạn.');

                return false;
            }
        }
        try {
            await room.localParticipant.setMicrophoneEnabled(
                Boolean(enabled),
                enabled ? profile.room.audioCaptureDefaults : undefined,
            );
            micOn = Boolean(enabled);
            if (locked === true) {
                micLockedByHost = true;
            } else if (locked === false || invited) {
                micLockedByHost = false;
            }
            if (enabled) {
                showMicTip('Nên dùng tai nghe để tránh hú tiếng khi ngồi gần loa.');
            }
            syncButtons();
            renderParticipants();

            return true;
        } catch (e) {
            if (isClosedSocketError(e)) {
                return false;
            }
            console.error('[LiveKit] mic set', e);
            setError(e instanceof Error ? e.message : 'Lỗi micro');

            return false;
        }
    };

    const localProfile = () => {
        const fromConfig = liveConfig.user && typeof liveConfig.user === 'object'
            ? liveConfig.user
            : {};

        return {
            avatar_url: fromConfig.avatar_url ?? liveConfig.user_avatar_url ?? null,
            avatar_initial: fromConfig.avatar_initial
                ?? liveConfig.user_avatar_initial
                ?? '?',
            career_role: fromConfig.career_role ?? null,
            specialty: fromConfig.specialty ?? null,
            institution: fromConfig.institution ?? null,
        };
    };

    const buildAvatarEl = (profile, sizeClass = 'size-10') => {
        const wrap = document.createElement('div');
        wrap.className = `${sizeClass} shrink-0 overflow-hidden rounded-full bg-primary/15 text-primary ring-1 ring-outline-variant/60`;
        wrap.title = String(profile?.name ?? '');
        const url = profile?.avatar_url;
        if (url) {
            const img = document.createElement('img');
            img.src = String(url);
            img.alt = String(profile?.name ?? '');
            img.className = 'size-full object-cover';
            img.loading = 'lazy';
            wrap.appendChild(img);
        } else {
            const initial = document.createElement('span');
            initial.className = 'flex size-full items-center justify-center text-sm font-semibold';
            initial.textContent = String(profile?.avatar_initial || profile?.name?.[0] || '?')
                .toUpperCase()
                .slice(0, 1);
            wrap.appendChild(initial);
        }

        return wrap;
    };

    const iconButton = ({ icon, label, danger = false, onClick }) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.title = label;
        btn.setAttribute('aria-label', label);
        btn.className = danger
            ? 'inline-flex size-8 items-center justify-center rounded-lg border border-error/40 text-error hover:bg-error/5 disabled:opacity-40'
            : 'inline-flex size-8 items-center justify-center rounded-lg border border-outline-variant text-on-surface hover:bg-surface-container-low disabled:opacity-40';
        const glyph = document.createElement('span');
        glyph.className = 'material-symbols-outlined text-[18px]';
        glyph.setAttribute('aria-hidden', 'true');
        glyph.textContent = icon;
        btn.appendChild(glyph);
        btn.addEventListener('click', onClick);

        return btn;
    };

    const statusIcon = (icon, label, active = false) => {
        const el = document.createElement('span');
        el.className = `inline-flex size-8 items-center justify-center rounded-lg ${
            active ? 'bg-primary/10 text-primary' : 'text-on-surface-variant'
        }`;
        el.title = label;
        el.setAttribute('aria-label', label);
        const glyph = document.createElement('span');
        glyph.className = 'material-symbols-outlined text-[18px]';
        glyph.setAttribute('aria-hidden', 'true');
        glyph.textContent = icon;
        el.appendChild(glyph);

        return el;
    };

    const profileSubtitle = (participant) => {
        const role = participant.career_role
            || (participant.isHost ? 'Giảng viên' : 'Học viên');
        const bits = [role];
        if (participant.specialty) {
            bits.push(participant.specialty);
        }
        if (participant.institution) {
            bits.push(participant.institution);
        }

        return bits.join(' · ');
    };

    const mapParticipantRow = (participant, { isSelf = false } = {}) => {
        const meta = parseParticipantMeta(participant);
        const fallback = isSelf ? localProfile() : {};

        return {
            identity: participant.identity,
            userId: userIdFromIdentity(participant.identity) ?? (meta.user_id != null ? Number(meta.user_id) : null),
            name: participant.name || meta.name || participant.identity,
            micOn: isSelf ? micOn : participantMicActive(participant),
            isHost: isHostParticipant(participant) || (isSelf && isLocalHost) || meta.is_host === true,
            isSelf,
            avatar_url: meta.avatar_url ?? fallback.avatar_url ?? null,
            avatar_initial: meta.avatar_initial ?? fallback.avatar_initial ?? '?',
            career_role: meta.career_role ?? fallback.career_role ?? null,
            specialty: meta.specialty ?? fallback.specialty ?? null,
            institution: meta.institution ?? fallback.institution ?? null,
        };
    };

    const renderParticipants = () => {
        if (! room || participantListEls.length === 0) {
            return;
        }

        const byUserId = new Map();
        const local = mapParticipantRow(room.localParticipant, { isSelf: true });
        if (local.userId !== null) {
            byUserId.set(local.userId, local);
        }
        for (const remote of room.remoteParticipants.values()) {
            const row = mapParticipantRow(remote);
            if (row.userId === null || byUserId.has(row.userId)) {
                continue;
            }
            byUserId.set(row.userId, row);
        }

        const participants = Array.from(byUserId.values())
            .sort((a, b) => {
                if (a.isHost !== b.isHost) {
                    return a.isHost ? -1 : 1;
                }
                if (a.isSelf !== b.isSelf) {
                    return a.isSelf ? -1 : 1;
                }

                return String(a.name).localeCompare(String(b.name), 'vi');
            });

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
                item.className = 'flex items-center gap-3 px-4 py-3 text-sm';

                item.appendChild(buildAvatarEl(participant, 'size-11'));

                const left = document.createElement('div');
                left.className = 'min-w-0 flex-1';
                const name = document.createElement('p');
                name.className = 'truncate font-medium text-on-surface';
                name.textContent = participant.isSelf
                    ? `${participant.name} (Bạn)`
                    : participant.name;
                const meta = document.createElement('p');
                meta.className = 'truncate text-[11px] text-on-surface-variant';
                meta.textContent = profileSubtitle(participant);
                left.append(name, meta);

                const actions = document.createElement('div');
                actions.className = 'flex shrink-0 items-center gap-1';

                if (Boolean(liveConfig.can_moderate) && ! participant.isHost && ! participant.isSelf) {
                    const micBtn = iconButton({
                        icon: participant.micOn ? 'mic' : 'mic_off',
                        label: participant.micOn ? 'Tắt mic' : 'Bật mic',
                        onClick: async () => {
                            const shouldMute = participant.micOn;
                            micBtn.disabled = true;
                            try {
                                if (! shouldMute) {
                                    await enforceLearnerFloor(participant.userId);
                                }
                                await publishMicCommand(shouldMute ? 'mute' : 'unmute', participant.userId, participant.identity);
                                await apiSpeakerAction(shouldMute ? 'mute' : 'unmute', participant.userId);
                                if (shouldMute) {
                                    remoteMutedByHost.add(participant.userId);
                                } else {
                                    remoteMutedByHost.delete(participant.userId);
                                }
                            } catch (e) {
                                console.error('[LiveKit] remote mic', e);
                                setError('Không đổi được mic học viên.');
                            } finally {
                                micBtn.disabled = false;
                                renderParticipants();
                            }
                        },
                    });
                    actions.appendChild(micBtn);

                    const kickBtn = iconButton({
                        icon: 'person_remove',
                        label: 'Kick khỏi phòng',
                        danger: true,
                        onClick: async () => {
                            await kickParticipant(
                                participant.userId,
                                participant.identity,
                                kickBtn,
                                participant.name,
                            );
                        },
                    });
                    actions.appendChild(kickBtn);
                } else {
                    actions.appendChild(statusIcon(
                        participant.micOn ? 'mic' : 'mic_off',
                        participant.micOn ? 'Mic đang bật' : 'Mic tắt',
                        participant.micOn,
                    ));
                }

                item.append(left, actions);
                list.appendChild(item);
            });
        });
    };

    const setButtonIcon = (button, icon, label) => {
        if (! (button instanceof HTMLElement)) {
            return;
        }
        button.title = label;
        button.setAttribute('aria-label', label);
        const glyph = button.querySelector('.material-symbols-outlined');
        if (glyph) {
            glyph.textContent = icon;
        } else {
            button.textContent = label;
        }
    };

    const kickParticipant = async (userId, identity, button, displayName = null) => {
        const template = String(liveConfig.kick_member_url_template ?? liveConfig.ban_member_url_template ?? '');
        if (! template || ! userId || ! isRoomUsable()) {
            return;
        }

        const who = String(displayName || identity || 'thành viên này').trim();
        const confirmed = window.confirm(
            `Kick “${who}” khỏi phòng live?\nHọ sẽ bị ngắt kết nối ngay và không thể ở lại buổi này.`,
        );
        if (! confirmed) {
            return;
        }

        button.disabled = true;
        setButtonIcon(button, 'hourglass_top', 'Đang kick...');

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

            setButtonIcon(button, 'check', 'Đã kick');
        } catch (e) {
            if (isClosedSocketError(e)) {
                return;
            }
            console.error('[LiveKit] kick participant', e);
            button.disabled = false;
            setButtonIcon(button, 'person_remove', 'Kick khỏi phòng');
            setError(e instanceof Error ? e.message : 'Không kick được học viên.');
        }
    };

    const enforceLearnerFloor = async (exceptUserId = null) => {
        if (! isLocalHost || ! isRoomUsable()) {
            return;
        }
        const active = listActiveLearnerMics().filter((s) => s.userId !== exceptUserId);
        const maxOthers = exceptUserId != null ? MAX_LEARNER_SPEAKERS - 1 : MAX_LEARNER_SPEAKERS;
        const toMute = active.slice(0, Math.max(0, active.length - maxOthers));
        for (const speaker of toMute) {
            try {
                await publishMicCommand('mute', speaker.userId, speaker.identity);
                await apiSpeakerAction('mute', speaker.userId);
                remoteMutedByHost.add(speaker.userId);
            } catch (e) {
                console.error('[LiveKit] floor mute', e);
            }
        }
        if (toMute.length) {
            renderParticipants();
        }
    };

    const handleMicModeration = async (message) => {
        if (message?.type !== 'mic') {
            return;
        }
        const targetId = Number(message.user_id);
        if (currentUserId === null || targetId !== currentUserId) {
            return;
        }
        if (message.action === 'mute') {
            await setLocalMicEnabled(false, { locked: true });
        } else if (message.action === 'unmute') {
            await setLocalMicEnabled(true, { locked: false, invited: true, bypassFloor: true });
        }
    };

    const onSpeakerUpdated = async (event) => {
        const detail = event?.detail ?? {};
        const action = String(detail.action ?? '');
        const targetId = Number(detail.user_id);
        const muteIds = Array.isArray(detail.mute_user_ids)
            ? detail.mute_user_ids.map(Number)
            : [];

        if (currentUserId !== null && muteIds.includes(currentUserId)) {
            await setLocalMicEnabled(false, { locked: true });
        }

        if (currentUserId === null || targetId !== currentUserId) {
            if (isLocalHost && (action === 'invite' || action === 'unmute') && Number.isFinite(targetId)) {
                await enforceLearnerFloor(targetId);
                try {
                    await publishMicCommand('unmute', targetId, `user-${targetId}`);
                    remoteMutedByHost.delete(targetId);
                } catch (e) {
                    console.error('[LiveKit] invite unmute data', e);
                }
            }
            renderParticipants();

            return;
        }

        if (action === 'invite' || action === 'unmute') {
            await setLocalMicEnabled(true, { locked: false, invited: true, bypassFloor: true });
        } else if (action === 'mute') {
            await setLocalMicEnabled(false, { locked: true });
        }
    };

    const syncButtons = () => {
        const connected = isRoomUsable();
        btnMics.forEach((btnMic) => {
            btnMic.classList.toggle('text-red-300', ! micOn);
            btnMic.classList.toggle('text-white', micOn);
            const icon = btnMic.querySelector('.material-symbols-outlined');
            if (icon) {
                icon.textContent = micOn ? 'mic' : 'mic_off';
            }
            btnMic.setAttribute('aria-label', micOn
                ? 'Tắt micro'
                : (micLockedByHost ? 'Mic bị host tắt' : 'Bật micro'));
            btnMic.title = micLockedByHost && ! micOn
                ? 'Host đã tắt mic — chờ được gọi'
                : 'Bật/tắt micro';
            btnMic.disabled = ! canPublishAudio || ! connected;
            btnMic.classList.toggle('hidden', ! canPublishAudio);
            btnMic.classList.toggle('opacity-50', micLockedByHost && ! micOn);
        });
        btnCams.forEach((btnCam) => {
            btnCam.classList.toggle('text-red-300', ! camOn);
            btnCam.classList.toggle('text-white', camOn);
            const icon = btnCam.querySelector('.material-symbols-outlined');
            if (icon) {
                icon.textContent = camOn ? 'videocam' : 'videocam_off';
            }
            btnCam.setAttribute('aria-label', camOn ? 'Tắt camera' : 'Bật camera');
            btnCam.disabled = ! canPublishVideo || ! connected;
            btnCam.classList.toggle('hidden', ! canPublishVideo);
        });
        btnScreens.forEach((btnScreen) => {
            const icon = btnScreen.querySelector('.material-symbols-outlined');
            if (icon) {
                icon.textContent = screenOn ? 'stop_screen_share' : 'present_to_all';
            }
            btnScreen.classList.toggle('bg-teal-600', screenOn);
            btnScreen.classList.toggle('hover:bg-teal-500', screenOn);
            btnScreen.classList.toggle('text-white', screenOn);
            btnScreen.classList.toggle('bg-white/10', ! screenOn);
            btnScreen.classList.toggle('hover:bg-white/20', ! screenOn);
            btnScreen.setAttribute('aria-label', screenOn ? 'Dừng chia sẻ màn hình' : 'Chia sẻ màn hình');
            btnScreen.title = screenOn
                ? 'Đang chia sẻ màn hình — bấm để dừng'
                : 'Chia sẻ slide/PDF/app ngoài (không cần khi chữa đề trong app)';
            btnScreen.disabled = ! canPublishScreen || ! connected;
            btnScreen.classList.toggle('hidden', ! canPublishScreen);
        });
        controlsEls.forEach((controlsEl) => {
            controlsEl.classList.toggle('hidden', ! canPublish);
            controlsEl.classList.toggle('flex', canPublish);
        });
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
            btnTeach.classList.toggle('hidden', ! Boolean(liveConfig.can_moderate) || ! connected || ! hasQuestions);
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

        // Host / studio: auto-enable mic. Learners keep mic off until self-toggle or invite.
        if (canPublishAudio && (canPublishVideo || studioMode || Boolean(liveConfig.can_moderate))) {
            try {
                await targetRoom.localParticipant.setMicrophoneEnabled(
                    true,
                    profile.room.audioCaptureDefaults,
                );
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
                    if (message.type === 'mic') {
                        void handleMicModeration(message);
                    }
                } catch {
                    // ignore malformed data messages
                }
            })
            .on(RoomEvent.TrackSubscribed, (track, _pub, participant) => {
                attachTrack(track, participant.identity, false);
                renderParticipants();
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
                renderParticipants();
                syncButtons();
            })
            .on(RoomEvent.TrackMuted, () => {
                renderParticipants();
            })
            .on(RoomEvent.TrackUnmuted, () => {
                renderParticipants();
            })
            .on(RoomEvent.LocalTrackPublished, (publication) => {
                if (publication.source === Track.Source.ScreenShare) {
                    screenOn = true;
                    syncButtons();
                }
                if (publication.track) {
                    attachTrack(publication.track, 'local', true);
                }
            })
            .on(RoomEvent.LocalTrackUnpublished, (publication) => {
                if (publication.source === Track.Source.Camera) {
                    stageEl?.querySelector('[data-lk-video="local-cam"]')?.remove();
                }
                if (publication.source === Track.Source.ScreenShare) {
                    screenOn = false;
                    stageEl?.querySelector('[data-lk-main]')?.replaceChildren();
                    if (studioMode) {
                        const camPub = room?.localParticipant.getTrackPublication(Track.Source.Camera);
                        if (camPub?.track) {
                            attachTrack(camPub.track, 'local', true);
                        }
                    }
                    syncButtons();
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
        if (! canPublishAudio) {
            return;
        }
        if (! isRoomUsable()) {
            showMicTip('Đang kết nối phòng live — thử lại sau vài giây.');

            return;
        }
        setError('');
        await setLocalMicEnabled(! micOn);
    };

    const onCam = async () => {
        if (! canPublishVideo) {
            return;
        }
        if (! isRoomUsable()) {
            showMicTip('Đang kết nối phòng live — thử lại sau vài giây.');

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
        if (! canPublishScreen) {
            return;
        }
        if (! isRoomUsable()) {
            showMicTip('Đang kết nối phòng live — thử lại sau vài giây.');

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

    const onLeave = () => {
        leaveRoom(exitUrl);
    };

    /** Desktop + mobile control bars both render [data-lk-*] — bind all via delegation. */
    const onControlsClick = (event) => {
        const target = event.target instanceof Element ? event.target : null;
        if (! target) {
            return;
        }
        if (target.closest('[data-lk-mic]')) {
            event.preventDefault();
            void onMic();

            return;
        }
        if (target.closest('[data-lk-cam]')) {
            event.preventDefault();
            void onCam();

            return;
        }
        if (target.closest('[data-lk-screen]')) {
            event.preventDefault();
            void onScreen();

            return;
        }
        if (target.closest('[data-lk-leave]')) {
            event.preventDefault();
            onLeave();
        }
    };

    root.addEventListener('click', onControlsClick);

    const onStageTeach = () => {
        relayoutPublishedVideos();
        syncButtons();
    };
    root.addEventListener('live:stage-teach', onStageTeach);
    root.addEventListener('live:speaker-updated', onSpeakerUpdated);

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
        root.removeEventListener('click', onControlsClick);
        root.removeEventListener('live:stage-teach', onStageTeach);
        root.removeEventListener('live:speaker-updated', onSpeakerUpdated);
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
