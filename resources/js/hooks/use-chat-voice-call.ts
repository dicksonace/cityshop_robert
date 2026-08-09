import { useCallback, useEffect, useRef, useState } from 'react';

import * as chatApi from '@/lib/chat-api';
import { startIncomingRing, startOutgoingRing, stopCallRing, unlockChatSounds } from '@/lib/chat-sounds';
import type { ChatMessage } from '@/types/chat';

export type CallState = 'idle' | 'calling' | 'incoming' | 'active';
export type CallKind = 'voice' | 'video';
export type EndCallReason = 'declined' | 'completed' | 'missed' | 'cancelled';

interface UseChatVoiceCallOptions {
    callerName?: string;
    onCallLog?: (message: ChatMessage) => void;
    onCallError?: (message: string) => void;
}

const FALLBACK_ICE_SERVERS: RTCIceServer[] = [
    { urls: ['stun:stun.l.google.com:19302', 'stun:stun1.l.google.com:19302'] },
];

/** Deterministic audio-then-video order so both peers build matching m-lines. */
function orderedTracks(stream: MediaStream): MediaStreamTrack[] {
    return [...stream.getAudioTracks(), ...stream.getVideoTracks()];
}

function cleanSdpPayload(init: RTCSessionDescriptionInit | null | undefined): { type: RTCSdpType; sdp: string } | null {
    if (!init) return null;
    const type = String(init.type ?? '').trim().toLowerCase();
    let sdp = typeof init.sdp === 'string' ? init.sdp : '';
    if (sdp.includes('\\n') && !sdp.includes('\n')) {
        sdp = sdp.replace(/\\r\\n/g, '\n').replace(/\\n/g, '\n');
    }
    sdp = sdp.replace(/\r\n/g, '\n').replace(/\r/g, '\n').trim();
    if (!sdp || (type !== 'offer' && type !== 'answer' && type !== 'pranswer')) return null;
    if (!sdp.includes('v=0') || !/^m=(audio|video)\s/m.test(sdp)) return null;
    return { type: type as RTCSdpType, sdp };
}

function mLineKinds(sdp: string): Array<'audio' | 'video'> {
    const kinds: Array<'audio' | 'video'> = [];
    for (const line of sdp.split('\n')) {
        if (line.startsWith('m=audio')) kinds.push('audio');
        if (line.startsWith('m=video')) kinds.push('video');
    }
    return kinds;
}

async function attachLocalTracksForAnswer(pc: RTCPeerConnection, stream: MediaStream, offerSdp: string) {
    const audios = stream.getAudioTracks();
    const videos = stream.getVideoTracks();
    const kinds = mLineKinds(offerSdp);
    let audioIdx = 0;
    let videoIdx = 0;

    for (let i = 0; i < pc.getTransceivers().length; i++) {
        const t = pc.getTransceivers()[i];
        const kind =
            t.receiver.track?.kind === 'audio' || t.receiver.track?.kind === 'video'
                ? t.receiver.track.kind
                : kinds[i];
        const track =
            kind === 'audio' && audioIdx < audios.length
                ? audios[audioIdx++]
                : kind === 'video' && videoIdx < videos.length
                  ? videos[videoIdx++]
                  : null;
        if (!track) continue;
        try {
            await t.sender.replaceTrack(track);
            t.direction = 'sendrecv';
        } catch {
            // Never addTrack on the answer path — extra m-lines break createAnswer.
        }
    }
}

export function useChatVoiceCall(
    conversationId: number | undefined,
    currentUserId: number | undefined,
    options: UseChatVoiceCallOptions = {},
) {
    const [callState, setCallState] = useState<CallState>('idle');
    const [callKind, setCallKind] = useState<CallKind>('voice');
    const pcRef = useRef<RTCPeerConnection | null>(null);
    const localStreamRef = useRef<MediaStream | null>(null);
    const remoteAudioRef = useRef<HTMLAudioElement>(null);
    const localVideoRef = useRef<HTMLVideoElement>(null);
    const remoteVideoRef = useRef<HTMLVideoElement>(null);
    const pendingOfferRef = useRef<RTCSessionDescriptionInit | null>(null);
    const pendingIceRef = useRef<RTCIceCandidateInit[]>([]);
    const pendingIceOutRef = useRef<RTCIceCandidateInit[]>([]);
    const iceFlushTimerRef = useRef<number | null>(null);
    const iceFlushInFlightRef = useRef(false);
    const processedCallIds = useRef<Set<number>>(new Set());
    const callerIdRef = useRef<number | null>(null);
    const callerNameRef = useRef<string>('');
    const callStartedAtRef = useRef<number | null>(null);
    const callKindRef = useRef<CallKind>('voice');
    const iceServersRef = useRef<RTCIceServer[] | null>(null);

    const attachLocalVideo = useCallback((stream: MediaStream) => {
        if (localVideoRef.current) {
            localVideoRef.current.srcObject = stream;
            void localVideoRef.current.play().catch(() => undefined);
        }
    }, []);

    const cleanup = useCallback(() => {
        stopCallRing();
        if (iceFlushTimerRef.current !== null) {
            window.clearTimeout(iceFlushTimerRef.current);
            iceFlushTimerRef.current = null;
        }
        pendingIceOutRef.current = [];
        iceFlushInFlightRef.current = false;
        pcRef.current?.close();
        pcRef.current = null;
        localStreamRef.current?.getTracks().forEach((track) => track.stop());
        localStreamRef.current = null;
        if (remoteAudioRef.current) {
            remoteAudioRef.current.srcObject = null;
        }
        if (localVideoRef.current) {
            localVideoRef.current.srcObject = null;
        }
        if (remoteVideoRef.current) {
            remoteVideoRef.current.srcObject = null;
        }
        pendingOfferRef.current = null;
        pendingIceRef.current = [];
        callStartedAtRef.current = null;
        callKindRef.current = 'voice';
        setCallKind('voice');
        setCallState('idle');
    }, []);

    const sendSignal = useCallback(
        async (type: string, body = '', metadata?: Record<string, unknown>) => {
            if (!conversationId) return null;
            return chatApi.sendCallSignal(conversationId, type, body, {
                call_kind: callKindRef.current,
                ...metadata,
            });
        },
        [conversationId],
    );

    const flushOutgoingIce = useCallback(async () => {
        iceFlushTimerRef.current = null;
        if (pendingIceOutRef.current.length === 0 || iceFlushInFlightRef.current) {
            if (pendingIceOutRef.current.length > 0 && !iceFlushInFlightRef.current) {
                iceFlushTimerRef.current = window.setTimeout(() => {
                    void flushOutgoingIce();
                }, 200);
            }
            return;
        }

        iceFlushInFlightRef.current = true;
        const batch = pendingIceOutRef.current.splice(0, pendingIceOutRef.current.length);
        try {
            await sendSignal(
                'call_ice',
                '',
                batch.length === 1 ? { candidate: batch[0] } : { candidates: batch },
            );
        } catch {
            // Drop this batch; peers keep polling for later candidates.
        } finally {
            iceFlushInFlightRef.current = false;
            if (pendingIceOutRef.current.length > 0) {
                iceFlushTimerRef.current = window.setTimeout(() => {
                    void flushOutgoingIce();
                }, 200);
            }
        }
    }, [sendSignal]);

    const queueIceCandidate = useCallback(
        (candidate: RTCIceCandidateInit) => {
            pendingIceOutRef.current.push(candidate);
            if (iceFlushTimerRef.current === null) {
                iceFlushTimerRef.current = window.setTimeout(() => {
                    void flushOutgoingIce();
                }, 400);
            }
        },
        [flushOutgoingIce],
    );

    const endCall = useCallback(
        async (reason?: EndCallReason) => {
            if (callState !== 'idle' && conversationId && currentUserId) {
                // Caller hang-up while ringing = cancelled ("Call ended"), not missed.
                const status: 'completed' | 'missed' | 'declined' | 'cancelled' = reason
                    ? reason === 'missed'
                        ? 'cancelled'
                        : reason
                    : callState === 'active'
                      ? 'completed'
                      : callState === 'incoming'
                        ? 'declined'
                        : callState === 'calling'
                          ? 'cancelled'
                          : 'cancelled';
                const durationSeconds =
                    callState === 'active' && callStartedAtRef.current
                        ? Math.max(0, Math.floor((Date.now() - callStartedAtRef.current) / 1000))
                        : 0;

                try {
                    const result = await sendSignal('call_end', '', {
                        call_log: {
                            status,
                            caller_id: callerIdRef.current ?? currentUserId,
                            caller_name: callerNameRef.current || options.callerName || 'User',
                            duration_seconds: durationSeconds,
                            call_kind: callKindRef.current,
                        },
                    });
                    if (result?.call_log) {
                        options.onCallLog?.(result.call_log);
                    }
                } catch {
                    // ignore signal errors on hang up
                }
            }
            cleanup();
        },
        [callState, cleanup, conversationId, currentUserId, options, sendSignal],
    );

    // Hard-bounded: this sits on the call-start path, so a slow response must
    // never hold up ringing or answering.
    const resolveIceServers = useCallback(async (): Promise<RTCIceServer[]> => {
        if (iceServersRef.current) return iceServersRef.current;
        try {
            const servers = await Promise.race([
                chatApi.fetchIceServers(),
                new Promise<RTCIceServer[]>((resolve) =>
                    window.setTimeout(() => resolve([]), 3000),
                ),
            ]);
            if (servers.length > 0) {
                iceServersRef.current = servers;
                return servers;
            }
        } catch {
            // fall through to public STUN
        }
        return FALLBACK_ICE_SERVERS;
    }, []);

    // Warm the cache while the thread loads so the call path never waits.
    useEffect(() => {
        if (conversationId) void resolveIceServers();
    }, [conversationId, resolveIceServers]);

    const createPeerConnection = useCallback(async () => {
        const pc = new RTCPeerConnection({ iceServers: await resolveIceServers() });
        pc.onconnectionstatechange = () => {
            if (pc.connectionState !== 'failed') return;
            options.onCallError?.(
                'Call dropped — your networks could not connect. Try a different network.',
            );
            void endCall('completed');
        };
        pc.ontrack = (event) => {
            const stream = event.streams[0];
            if (!stream) return;
            if (remoteAudioRef.current) {
                remoteAudioRef.current.srcObject = stream;
            }
            if (remoteVideoRef.current) {
                remoteVideoRef.current.srcObject = stream;
                void remoteVideoRef.current.play().catch(() => undefined);
            }
        };
        pc.onicecandidate = (event) => {
            if (event.candidate) {
                queueIceCandidate(event.candidate.toJSON());
            }
        };
        return pc;
    }, [endCall, options, queueIceCandidate, resolveIceServers]);

    const startCall = useCallback(
        async (kind: CallKind = 'voice') => {
            if (!conversationId || !currentUserId) return;
            try {
                callerIdRef.current = currentUserId;
                callerNameRef.current = options.callerName ?? 'You';
                callKindRef.current = kind;
                setCallKind(kind);

                const stream = await navigator.mediaDevices.getUserMedia({
                    audio: true,
                    video: kind === 'video',
                });
                localStreamRef.current = stream;
                if (kind === 'video') {
                    attachLocalVideo(stream);
                }
                const pc = await createPeerConnection();
                pcRef.current = pc;
                orderedTracks(stream).forEach((track) => pc.addTrack(track, stream));

                const offer = await pc.createOffer();
                await pc.setLocalDescription(offer);
                const payload = cleanSdpPayload(offer);
                if (!payload) {
                    throw new Error('Could not build call offer.');
                }
                await sendSignal('call_offer', kind === 'video' ? 'Video call' : 'Voice call', {
                    sdp: payload,
                    call_kind: kind,
                });
                unlockChatSounds();
                startOutgoingRing();
                setCallState('calling');
            } catch {
                cleanup();
                throw new Error(
                    kind === 'video'
                        ? 'Could not access camera/microphone. Please allow permissions.'
                        : 'Could not access microphone. Please allow microphone access.',
                );
            }
        },
        [attachLocalVideo, cleanup, conversationId, createPeerConnection, currentUserId, options.callerName, sendSignal],
    );

    const acceptCall = useCallback(async () => {
        if (!conversationId || !pendingOfferRef.current) return;
        try {
            stopCallRing();
            const offerInit = cleanSdpPayload(pendingOfferRef.current);
            if (!offerInit) {
                throw new Error('That call is no longer available. Ask them to call again.');
            }
            const needsVideo = /^m=video\s/m.test(offerInit.sdp) || callKindRef.current === 'video';
            const kind = needsVideo ? 'video' : 'voice';
            callKindRef.current = kind;
            setCallKind(kind);

            const pc = await createPeerConnection();
            pcRef.current = pc;
            await pc.setRemoteDescription(new RTCSessionDescription(offerInit));

            const stream = await navigator.mediaDevices.getUserMedia({
                audio: true,
                video: needsVideo,
            });
            localStreamRef.current = stream;
            if (needsVideo) {
                attachLocalVideo(stream);
            }

            await attachLocalTracksForAnswer(pc, stream, offerInit.sdp);

            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);

            for (const candidate of pendingIceRef.current) {
                try {
                    await pc.addIceCandidate(new RTCIceCandidate(candidate));
                } catch {
                    // ignore stale ICE candidates
                }
            }
            pendingIceRef.current = [];

            const answerPayload = cleanSdpPayload(answer);
            if (!answerPayload) {
                throw new Error('Could not build call answer.');
            }
            await sendSignal('call_answer', '', {
                sdp: answerPayload,
                call_kind: kind,
            });
            pendingOfferRef.current = null;
            callStartedAtRef.current = Date.now();
            setCallState('active');
        } catch {
            cleanup();
            throw new Error('Could not join the call. Ask them to call again.');
        }
    }, [attachLocalVideo, cleanup, conversationId, createPeerConnection, sendSignal]);

    const handleCallMessage = useCallback(
        async (msg: ChatMessage) => {
            if (!msg.type.startsWith('call') || msg.type === 'call_log' || !currentUserId) return;
            if (processedCallIds.current.has(msg.id)) return;
            processedCallIds.current.add(msg.id);

            if (msg.type === 'call_end') {
                cleanup();
                return;
            }

            if (msg.type === 'call_offer' && msg.sender_id !== currentUserId) {
                const cleaned = cleanSdpPayload(msg.metadata?.sdp as RTCSessionDescriptionInit | undefined);
                const kind =
                    (cleaned && /^m=video\s/m.test(cleaned.sdp)) || msg.metadata?.call_kind === 'video'
                        ? 'video'
                        : 'voice';
                callKindRef.current = kind;
                setCallKind(kind);
                callerIdRef.current = msg.sender_id;
                callerNameRef.current = msg.sender?.name ?? 'Caller';
                pendingOfferRef.current = cleaned;
                pendingIceRef.current = [];
                unlockChatSounds();
                startIncomingRing();
                setCallState('incoming');
                return;
            }

            if (msg.type === 'call_ice') {
                const candidates: RTCIceCandidateInit[] = [];
                if (msg.metadata?.candidate) {
                    candidates.push(msg.metadata.candidate as RTCIceCandidateInit);
                }
                if (Array.isArray(msg.metadata?.candidates)) {
                    for (const row of msg.metadata.candidates) {
                        if (row && typeof row === 'object') {
                            candidates.push(row as RTCIceCandidateInit);
                        }
                    }
                }
                for (const candidate of candidates) {
                    if (!pcRef.current) {
                        if (pendingOfferRef.current) {
                            pendingIceRef.current.push(candidate);
                            if (pendingIceRef.current.length > 80) {
                                pendingIceRef.current.shift();
                            }
                        }
                        continue;
                    }
                    try {
                        await pcRef.current.addIceCandidate(new RTCIceCandidate(candidate));
                    } catch {
                        // ignore stale ICE candidates
                    }
                }
                return;
            }

            if (!pcRef.current) return;

            // Our own answer echoes back through polling; applying it would blow
            // up because this peer is already in the `stable` state.
            if (msg.type === 'call_answer' && msg.sender_id === currentUserId) return;

            if (msg.type === 'call_answer' && msg.metadata?.sdp) {
                if (pcRef.current.signalingState !== 'have-local-offer') return;
                const answer = cleanSdpPayload(msg.metadata.sdp as RTCSessionDescriptionInit);
                if (!answer) return;
                stopCallRing();
                await pcRef.current.setRemoteDescription(new RTCSessionDescription(answer));
                for (const candidate of pendingIceRef.current) {
                    try {
                        await pcRef.current.addIceCandidate(new RTCIceCandidate(candidate));
                    } catch {
                        // ignore stale ICE candidates
                    }
                }
                pendingIceRef.current = [];
                callStartedAtRef.current = Date.now();
                setCallState('active');
            }
        },
        [cleanup, currentUserId],
    );

    return {
        callState,
        callKind,
        remoteAudioRef,
        localVideoRef,
        remoteVideoRef,
        startCall,
        acceptCall,
        endCall,
        handleCallMessage,
    };
}
