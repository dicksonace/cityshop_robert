import { useEffect, useRef } from 'react';

export interface JitsiRoom {
    domain: string;
    room_name: string;
}

interface JitsiLiveRoomProps {
    room: JitsiRoom;
    displayName: string;
    isHost: boolean;
    avatarUrl?: string | null;
    onHangup?: () => void;
}

declare global {
    interface Window {
        JitsiMeetExternalAPI?: new (
            domain: string,
            options: Record<string, unknown>,
        ) => {
            dispose: () => void;
            addListener: (event: string, handler: () => void) => void;
        };
    }
}

function loadJitsiScript(): Promise<void> {
    if (window.JitsiMeetExternalAPI) {
        return Promise.resolve();
    }

    return new Promise((resolve, reject) => {
        const existing = document.querySelector<HTMLScriptElement>('script[data-jitsi-api]');
        if (existing) {
            existing.addEventListener('load', () => resolve());
            existing.addEventListener('error', () => reject(new Error('Could not load live video.')));
            return;
        }
        const script = document.createElement('script');
        script.src = 'https://meet.jit.si/external_api.js';
        script.async = true;
        script.dataset.jitsiApi = 'true';
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Could not load live video.'));
        document.body.appendChild(script);
    });
}

export default function JitsiLiveRoom({ room, displayName, isHost, avatarUrl, onHangup }: JitsiLiveRoomProps) {
    const wrapRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        let api: { dispose: () => void; addListener: (event: string, handler: () => void) => void } | null = null;
        let cancelled = false;

        void loadJitsiScript()
            .then(() => {
                if (cancelled || !wrapRef.current || !window.JitsiMeetExternalAPI) return;
                wrapRef.current.innerHTML = '';
                const userInfo: Record<string, string> = { displayName };
                if (avatarUrl && /^https?:\/\//i.test(avatarUrl)) {
                    userInfo.avatarURL = avatarUrl;
                }
                api = new window.JitsiMeetExternalAPI(room.domain || 'meet.jit.si', {
                    roomName: room.room_name,
                    parentNode: wrapRef.current,
                    width: '100%',
                    height: '100%',
                    userInfo,
                    configOverwrite: {
                        prejoinPageEnabled: false,
                        startWithAudioMuted: !isHost,
                        startWithVideoMuted: !isHost,
                        disableDeepLinking: true,
                        disableInviteFunctions: true,
                    },
                    interfaceConfigOverwrite: {
                        SHOW_JITSI_WATERMARK: false,
                        SHOW_WATERMARK_FOR_GUESTS: false,
                        TOOLBAR_BUTTONS: isHost
                            ? ['microphone', 'camera', 'hangup', 'tileview', 'fullscreen']
                            : ['microphone', 'tileview', 'fullscreen'],
                    },
                });
                if (onHangup) {
                    api.addListener('videoConferenceLeft', onHangup);
                }
            })
            .catch(() => {
                // keep empty container; parent can still show a fallback link
            });

        return () => {
            cancelled = true;
            api?.dispose();
        };
    }, [avatarUrl, displayName, isHost, onHangup, room.domain, room.room_name]);

    return <div ref={wrapRef} className="h-full min-h-[420px] w-full overflow-hidden rounded-2xl bg-black" />;
}
