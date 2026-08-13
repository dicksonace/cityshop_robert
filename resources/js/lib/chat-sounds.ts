let audioContext: AudioContext | null = null;
let audioUnlocked = false;
let ringAudio: HTMLAudioElement | null = null;
let ringStopTimer: number | null = null;

function getContext(): AudioContext | null {
    if (!audioUnlocked || typeof window === 'undefined') {
        return null;
    }

    if (!audioContext) {
        audioContext = new AudioContext();
    }

    if (audioContext.state === 'suspended') {
        void audioContext.resume();
    }

    return audioContext;
}

function playTone(frequency: number, startTime: number, duration: number, volume: number, type: OscillatorType = 'sine'): void {
    const ctx = getContext();
    if (!ctx) {
        return;
    }
    const oscillator = ctx.createOscillator();
    const gain = ctx.createGain();

    oscillator.type = type;
    oscillator.frequency.setValueAtTime(frequency, startTime);

    gain.gain.setValueAtTime(0.0001, startTime);
    gain.gain.exponentialRampToValueAtTime(volume, startTime + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.0001, startTime + duration);

    oscillator.connect(gain);
    gain.connect(ctx.destination);

    oscillator.start(startTime);
    oscillator.stop(startTime + duration + 0.05);
}

/** Soft pop when you send a message */
export function playChatSendSound(): void {
    try {
        const ctx = getContext();
        if (!ctx) {
            return;
        }
        const now = ctx.currentTime;
        playTone(520, now, 0.08, 0.12);
        playTone(780, now + 0.06, 0.1, 0.08);
    } catch {
        // Audio not available
    }
}

/** Cash ding when money lands (QR / chat transfer). */
let lastMoneySoundAt = 0;

export function playMoneyReceivedSound(): void {
    try {
        const nowMs = Date.now();
        if (nowMs - lastMoneySoundAt < 1400) {
            return;
        }
        lastMoneySoundAt = nowMs;

        unlockChatSounds();
        const audio = new Audio('/sounds/money_received.wav');
        audio.volume = 0.9;
        void audio.play().catch(() => {
            const ctx = getContext();
            if (!ctx) return;
            const now = ctx.currentTime;
            playTone(1046, now, 0.22, 0.22);
            playTone(1568, now, 0.18, 0.12);
            playTone(1318, now + 0.16, 0.28, 0.24);
            playTone(2093, now + 0.16, 0.22, 0.14);
        });
    } catch {
        // Audio not available
    }
}

/** Pleasant two-note chime when a reply arrives (once — never loop). */
let lastReceiveSoundAt = 0;

export function playChatReceiveSound(): void {
    try {
        const nowMs = Date.now();
        // Poll/realtime can deliver the same message more than once; don't beep in a loop.
        if (nowMs - lastReceiveSoundAt < 1600) {
            return;
        }
        lastReceiveSoundAt = nowMs;

        const ctx = getContext();
        if (!ctx) {
            return;
        }
        const now = ctx.currentTime;
        playTone(660, now, 0.14, 0.18);
        playTone(880, now + 0.12, 0.18, 0.15);
        playTone(1100, now + 0.22, 0.12, 0.08);
    } catch {
        // Audio not available
    }
}

function clearRingTimer(): void {
    if (ringStopTimer !== null) {
        window.clearInterval(ringStopTimer);
        ringStopTimer = null;
    }
}

/** Stop outgoing/incoming call ring. */
export function stopCallRing(): void {
    clearRingTimer();
    if (ringAudio) {
        try {
            ringAudio.pause();
            ringAudio.currentTime = 0;
        } catch {
            // ignore
        }
        ringAudio = null;
    }
}

function startLoopingAudio(src: string, volume = 0.7): void {
    stopCallRing();
    unlockChatSounds();
    try {
        const audio = new Audio(src);
        audio.loop = true;
        audio.volume = volume;
        ringAudio = audio;
        void audio.play().catch(() => {
            startOscillatorRing(src.includes('ringtone'));
        });
    } catch {
        startOscillatorRing(src.includes('ringtone'));
    }
}

/** Oscillator fallback when WAV cannot play. */
function startOscillatorRing(incoming: boolean): void {
    stopCallRing();
    unlockChatSounds();
    const beat = () => {
        try {
            const ctx = getContext();
            if (!ctx) return;
            const now = ctx.currentTime;
            if (incoming) {
                playTone(520, now, 0.28, 0.22);
                playTone(660, now, 0.28, 0.18);
                playTone(520, now + 0.4, 0.28, 0.22);
                playTone(660, now + 0.4, 0.28, 0.18);
            } else {
                playTone(440, now, 0.45, 0.16);
                playTone(480, now, 0.45, 0.14);
            }
        } catch {
            // ignore
        }
    };
    beat();
    ringStopTimer = window.setInterval(beat, incoming ? 1400 : 2800);
}

/** Soft ringback while you are calling someone. */
export function startOutgoingRing(): void {
    startLoopingAudio('/sounds/call_ringback.wav', 0.55);
}

/** Louder ringtone when someone is calling you. */
export function startIncomingRing(): void {
    startLoopingAudio('/sounds/call_ringtone.wav', 0.8);
}

/** Unlock audio on first user interaction (browser autoplay policy) */
export function unlockChatSounds(): void {
    audioUnlocked = true;

    try {
        if (!audioContext) {
            audioContext = new AudioContext();
        }
        if (audioContext.state === 'suspended') {
            void audioContext.resume();
        }
    } catch {
        // ignore
    }
}

if (typeof window !== 'undefined') {
    const unlock = () => {
        unlockChatSounds();
        window.removeEventListener('click', unlock);
        window.removeEventListener('keydown', unlock);
        window.removeEventListener('touchstart', unlock);
    };
    window.addEventListener('click', unlock, { once: true });
    window.addEventListener('keydown', unlock, { once: true });
    window.addEventListener('touchstart', unlock, { once: true });
}
