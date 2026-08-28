const waveformCache = new Map<string, number[]>();

export function cacheVoiceWaveform(key: string, samples: number[]): void {
    if (!key || samples.length === 0) return;
    waveformCache.set(key, samples.slice());
}

export function getCachedVoiceWaveform(key: string): number[] | undefined {
    return waveformCache.get(key);
}

export function moveCachedVoiceWaveform(from: string, to: string): void {
    if (!from || !to || from === to) return;
    const samples = waveformCache.get(from);
    if (samples) {
        waveformCache.set(to, samples);
        waveformCache.delete(from);
    }
}

export function normalizeAmplitudeDb(db: number): number {
    if (!Number.isFinite(db)) return 0.1;
    const minDb = -50;
    const maxDb = -5;
    if (db <= minDb) return 0.1;
    if (db >= maxDb) return 1;
    return 0.1 + ((db - minDb) / (maxDb - minDb)) * 0.9;
}

export function downsampleWaveform(samples: number[], maxBars: number): number[] {
    if (samples.length === 0) return [];
    if (samples.length <= maxBars) return samples.slice();
    const out: number[] = [];
    const chunk = samples.length / maxBars;
    for (let i = 0; i < maxBars; i++) {
        const start = Math.floor(i * chunk);
        const end = Math.min(samples.length, Math.ceil((i + 1) * chunk));
        let peak = 0;
        for (let j = start; j < end; j++) peak = Math.max(peak, samples[j] ?? 0);
        out.push(peak);
    }
    return out;
}

export function normalizeWaveformBars(samples: number[], barCount: number): number[] {
    if (samples.length === 0) {
        return Array.from({ length: barCount }, () => 0.12);
    }
    const resized = downsampleWaveform(samples, barCount);
    const maxVal = Math.max(...resized, 0.001);
    return resized.map((v) => Math.max(0.08, Math.min(1, v / maxVal)));
}

export function fallbackWaveform(barCount: number, durationSeconds: number, seed = ''): number[] {
    let hash = durationSeconds;
    for (let i = 0; i < seed.length; i++) hash = (hash * 31 + seed.charCodeAt(i)) | 0;
    return Array.from({ length: barCount }, (_, i) => {
        const n = Math.abs(Math.sin((hash + i * 17) * 0.17));
        return 0.12 + n * 0.55 + (i % 2 === 0 ? 0.08 : 0);
    });
}

export async function extractWaveformFromUrl(src: string, barCount = 36): Promise<number[]> {
    const cached = waveformCache.get(src);
    if (cached) return cached;

    try {
        const response = await fetch(src);
        const arrayBuffer = await response.arrayBuffer();
        const audioContext = new AudioContext();
        const audioBuffer = await audioContext.decodeAudioData(arrayBuffer.slice(0));
        await audioContext.close();

        const channel = audioBuffer.getChannelData(0);
        const blockSize = Math.max(1, Math.floor(channel.length / barCount));
        const samples: number[] = [];
        for (let i = 0; i < barCount; i++) {
            let sum = 0;
            for (let j = 0; j < blockSize; j++) {
                sum += Math.abs(channel[i * blockSize + j] ?? 0);
            }
            samples.push(sum / blockSize);
        }
        const normalized = normalizeWaveformBars(samples, barCount);
        waveformCache.set(src, normalized);
        return normalized;
    } catch {
        const fallback = fallbackWaveform(barCount, 0, src);
        waveformCache.set(src, fallback);
        return fallback;
    }
}

export function rmsFromTimeDomain(data: Uint8Array): number {
    let sum = 0;
    for (let i = 0; i < data.length; i++) {
        const v = (data[i] - 128) / 128;
        sum += v * v;
    }
    return Math.sqrt(sum / data.length);
}
