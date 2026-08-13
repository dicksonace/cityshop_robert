import { useEffect, useRef } from 'react';

import {
    jitsiConfigOverwrite,
    jitsiInterfaceConfigOverwrite,
    jitsiScriptUrl,
    type JitsiRoom,
} from '@/lib/jitsi';

interface JitsiLiveRoomProps {
    room: JitsiRoom;
    displayName: string;
    isHost: boolean;
    /** Host-only shop avatar. Avoid for shoppers — it replaces the face when video is muted. */
    avatarUrl?: string | null;
    onJoined?: () => void;
    onHangup?: () => void;
}

type JitsiApi = {
    dispose: () => void;
    addListener: (event: string, handler: (...args: unknown[]) => void) => void;
    executeCommand: (command: string, ...args: unknown[]) => void;
};

declare global {
    interface Window {
        JitsiMeetExternalAPI?: new (domain: string, options: Record<string, unknown>) => JitsiApi;
    }
}

function loadJitsiScript(domain: string): Promise<void> {
    const src = jitsiScriptUrl(domain);
    const existing = document.querySelector<HTMLScriptElement>('script[data-jitsi-api]');
    if (window.JitsiMeetExternalAPI && existing?.dataset.jitsiSrc === src) {
        return Promise.resolve();
    }

    return new Promise((resolve, reject) => {
        if (existing && existing.dataset.jitsiSrc === src) {
            existing.addEventListener('load', () => resolve());
            existing.addEventListener('error', () => reject(new Error('Could not load live video.')));
            return;
        }
        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.dataset.jitsiApi = 'true';
        script.dataset.jitsiSrc = src;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Could not load live video.'));
        document.body.appendChild(script);
    });
}

export default function JitsiLiveRoom({
    room,
    displayName,
    isHost,
    avatarUrl,
    onJoined,
    onHangup,
}: JitsiLiveRoomProps) {
    const wrapRef = useRef<HTMLDivElement>(null);
    const hangupRef = useRef(onHangup);
    const joinedRef = useRef(onJoined);
    hangupRef.current = onHangup;
    joinedRef.current = onJoined;

    useEffect(() => {
        let api: JitsiApi | null = null;
        let cancelled = false;
        let leftHandled = false;

        void loadJitsiScript(room.domain)
            .then(() => {
                if (cancelled || !wrapRef.current || !window.JitsiMeetExternalAPI) return;
                wrapRef.current.innerHTML = '';
                const userInfo: Record<string, string> = {
                    displayName: displayName.trim() || (isHost ? 'Store' : 'CityShop shopper'),
                };
                // Only hosts get a static avatar fallback — shopper avatars hide their camera face.
                if (isHost && avatarUrl && /^https?:\/\//i.test(avatarUrl)) {
                    userInfo.avatarURL = avatarUrl;
                }
                api = new window.JitsiMeetExternalAPI(room.domain || 'jitsi.riot.im', {
                    roomName: room.room_name,
                    parentNode: wrapRef.current,
                    width: '100%',
                    height: '100%',
                    lang: 'en',
                    userInfo,
                    configOverwrite: jitsiConfigOverwrite(isHost),
                    interfaceConfigOverwrite: jitsiInterfaceConfigOverwrite(isHost),
                });

                try {
                    api.executeCommand('setLanguage', 'en');
                } catch {
                    // older Jitsi builds may not support this command
                }

                api.addListener('videoConferenceJoined', () => {
                    if (!api) return;
                    try {
                        api.executeCommand('setLanguage', 'en');
                        api.executeCommand('setVideoMute', !isHost);
                        api.executeCommand('setAudioMute', !isHost);
                        api.executeCommand('setTileView', true);
                    } catch {
                        // browser may still prompt for permission
                    }
                    joinedRef.current?.();
                });

                api.addListener('videoConferenceLeft', () => {
                    // Do not end the CityShop live on a Jitsi reconnect/blip — only the End live button should.
                    if (leftHandled || cancelled || isHost) return;
                    leftHandled = true;
                    hangupRef.current?.();
                });
            })
            .catch(() => {
                // keep empty container; parent can still show a fallback link
            });

        return () => {
            cancelled = true;
            api?.dispose();
        };
    }, [avatarUrl, displayName, isHost, room.domain, room.room_name]);

    return <div ref={wrapRef} className="h-full min-h-[420px] w-full overflow-hidden rounded-2xl bg-black" />;
}
