import { useCallback, useRef, useState } from 'react';

import * as chatApi from '@/lib/chat-api';
import { startIncomingRing, startOutgoingRing, stopCallRing, unlockChatSounds } from '@/lib/chat-sounds';
import type { ChatMessage } from '@/types/chat';

export type CallState = 'idle' | 'calling' | 'incoming' | 'active';
export type CallKind = 'voice' | 'video';
export type EndCallReason = 'declined' | 'completed' | 'missed' | 'cancelled';

interface UseChatVoiceCallOptions {
    callerName?: string;
    onCallLog?: (message: ChatMessage) => void;
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
    const processedCallIds = useRef<Set<number>>(new Set());
    const callerIdRef = useRef<number | null>(null);
    const callerNameRef = useRef<string>('');
    const callStartedAtRef = useRef<number | null>(null);
    const callKindRef = useRef<CallKind>('voice');

    const attachLocalVideo = useCallback((stream: MediaStream) => {
        if (localVideoRef.current) {
            localVideoRef.current.srcObject = stream;
            void localVideoRef.current.play().catch(() => undefined);
        }
    }, []);

    const cleanup = useCallback(() => {
        stopCallRing();
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

    const createPeerConnection = useCallback(() => {
        const pc = new RTCPeerConnection({ iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] });
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
                sendSignal('call_ice', '', { candidate: event.candidate.toJSON() });
            }
        };
        return pc;
    }, [sendSignal]);

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
                const pc = createPeerConnection();
                pcRef.current = pc;
                stream.getTracks().forEach((track) => pc.addTrack(track, stream));

                const offer = await pc.createOffer();
                await pc.setLocalDescription(offer);
                await sendSignal('call_offer', kind === 'video' ? 'Video call' : 'Voice call', {
                    sdp: offer,
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
            const kind = callKindRef.current;
            const stream = await navigator.mediaDevices.getUserMedia({
                audio: true,
                video: kind === 'video',
            });
            localStreamRef.current = stream;
            if (kind === 'video') {
                attachLocalVideo(stream);
            }
            const pc = createPeerConnection();
            pcRef.current = pc;
            stream.getTracks().forEach((track) => pc.addTrack(track, stream));

            await pc.setRemoteDescription(new RTCSessionDescription(pendingOfferRef.current));
            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);
            await sendSignal('call_answer', '', { sdp: answer, call_kind: kind });
            pendingOfferRef.current = null;
            callStartedAtRef.current = Date.now();
            setCallState('active');
        } catch {
            cleanup();
            throw new Error('Could not access camera/microphone.');
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
                const kind = msg.metadata?.call_kind === 'video' ? 'video' : 'voice';
                callKindRef.current = kind;
                setCallKind(kind);
                callerIdRef.current = msg.sender_id;
                callerNameRef.current = msg.sender?.name ?? 'Caller';
                pendingOfferRef.current = msg.metadata?.sdp as RTCSessionDescriptionInit;
                unlockChatSounds();
                startIncomingRing();
                setCallState('incoming');
                return;
            }

            if (!pcRef.current) return;

            if (msg.type === 'call_answer' && msg.metadata?.sdp) {
                stopCallRing();
                await pcRef.current.setRemoteDescription(
                    new RTCSessionDescription(msg.metadata.sdp as RTCSessionDescriptionInit),
                );
                callStartedAtRef.current = Date.now();
                setCallState('active');
            }

            if (msg.type === 'call_ice' && msg.metadata?.candidate) {
                try {
                    await pcRef.current.addIceCandidate(new RTCIceCandidate(msg.metadata.candidate as RTCIceCandidateInit));
                } catch {
                    // ignore stale ICE candidates
                }
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
