let audioContext: AudioContext | null = null;

function getContext(): AudioContext {
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
        const now = ctx.currentTime;
        playTone(520, now, 0.08, 0.12);
        playTone(780, now + 0.06, 0.1, 0.08);
    } catch {
        // Audio not available
    }
}

/** Pleasant two-note chime when a reply arrives */
export function playChatReceiveSound(): void {
    try {
        const ctx = getContext();
        const now = ctx.currentTime;
        playTone(660, now, 0.14, 0.18);
        playTone(880, now + 0.12, 0.18, 0.15);
        playTone(1100, now + 0.22, 0.12, 0.08);
    } catch {
        // Audio not available
    }
}

/** Unlock audio on first user interaction (browser autoplay policy) */
export function unlockChatSounds(): void {
    try {
        getContext();
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
