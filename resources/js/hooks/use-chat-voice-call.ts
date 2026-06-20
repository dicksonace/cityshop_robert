import { useCallback, useRef, useState } from 'react';

import * as chatApi from '@/lib/chat-api';
import type { ChatMessage } from '@/types/chat';

export type CallState = 'idle' | 'calling' | 'incoming' | 'active';

export function useChatVoiceCall(conversationId: number | undefined, currentUserId: number | undefined) {
    const [callState, setCallState] = useState<CallState>('idle');
    const pcRef = useRef<RTCPeerConnection | null>(null);
    const localStreamRef = useRef<MediaStream | null>(null);
    const remoteAudioRef = useRef<HTMLAudioElement>(null);
    const pendingOfferRef = useRef<RTCSessionDescriptionInit | null>(null);
    const processedCallIds = useRef<Set<number>>(new Set());

    const cleanup = useCallback(() => {
        pcRef.current?.close();
        pcRef.current = null;
        localStreamRef.current?.getTracks().forEach((track) => track.stop());
        localStreamRef.current = null;
        if (remoteAudioRef.current) {
            remoteAudioRef.current.srcObject = null;
        }
        pendingOfferRef.current = null;
        setCallState('idle');
    }, []);

    const sendSignal = useCallback(
        async (type: string, body = '', metadata?: Record<string, unknown>) => {
            if (!conversationId) return;
            await chatApi.sendCallSignal(conversationId, type, body, metadata);
        },
        [conversationId],
    );

    const endCall = useCallback(async () => {
        if (callState !== 'idle' && conversationId) {
            try {
                await sendSignal('call_end');
            } catch {
                // ignore signal errors on hang up
            }
        }
        cleanup();
    }, [callState, cleanup, conversationId, sendSignal]);

    const createPeerConnection = useCallback(() => {
        const pc = new RTCPeerConnection({ iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] });
        pc.ontrack = (event) => {
            if (remoteAudioRef.current) {
                remoteAudioRef.current.srcObject = event.streams[0];
            }
        };
        pc.onicecandidate = (event) => {
            if (event.candidate) {
                sendSignal('call_ice', '', { candidate: event.candidate.toJSON() });
            }
        };
        return pc;
    }, [sendSignal]);

    const startCall = useCallback(async () => {
        if (!conversationId) return;
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            localStreamRef.current = stream;
            const pc = createPeerConnection();
            pcRef.current = pc;
            stream.getTracks().forEach((track) => pc.addTrack(track, stream));

            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);
            await sendSignal('call_offer', 'Voice call', { sdp: offer });
            setCallState('calling');
        } catch {
            cleanup();
            throw new Error('Could not access microphone. Please allow microphone access.');
        }
    }, [cleanup, conversationId, createPeerConnection, sendSignal]);

    const acceptCall = useCallback(async () => {
        if (!conversationId || !pendingOfferRef.current) return;
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            localStreamRef.current = stream;
            const pc = createPeerConnection();
            pcRef.current = pc;
            stream.getTracks().forEach((track) => pc.addTrack(track, stream));

            await pc.setRemoteDescription(new RTCSessionDescription(pendingOfferRef.current));
            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);
            await sendSignal('call_answer', '', { sdp: answer });
            pendingOfferRef.current = null;
            setCallState('active');
        } catch {
            cleanup();
            throw new Error('Could not access microphone.');
        }
    }, [cleanup, conversationId, createPeerConnection, sendSignal]);

    const handleCallMessage = useCallback(
        async (msg: ChatMessage) => {
            if (!msg.type.startsWith('call') || !currentUserId) return;
            if (processedCallIds.current.has(msg.id)) return;
            processedCallIds.current.add(msg.id);

            if (msg.type === 'call_end') {
                cleanup();
                return;
            }

            if (msg.type === 'call_offer' && msg.sender_id !== currentUserId) {
                pendingOfferRef.current = msg.metadata?.sdp as RTCSessionDescriptionInit;
                setCallState('incoming');
                return;
            }

            if (!pcRef.current) return;

            if (msg.type === 'call_answer' && msg.metadata?.sdp) {
                await pcRef.current.setRemoteDescription(
                    new RTCSessionDescription(msg.metadata.sdp as RTCSessionDescriptionInit),
                );
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
        remoteAudioRef,
        startCall,
        acceptCall,
        endCall,
        handleCallMessage,
    };
}
