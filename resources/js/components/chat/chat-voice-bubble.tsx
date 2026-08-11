import { Pause, Play } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { cn } from '@/lib/utils';

type ChatVoiceBubbleProps = {
    src: string;
    durationSeconds?: number | null;
    mine?: boolean;
};

function clock(totalSeconds: number): string {
    const safe = Math.max(0, Math.floor(totalSeconds));
    const minutes = Math.floor(safe / 60);
    const seconds = safe % 60;
    return `${minutes}:${String(seconds).padStart(2, '0')}`;
}

export default function ChatVoiceBubble({ src, durationSeconds, mine = false }: ChatVoiceBubbleProps) {
    const audioRef = useRef<HTMLAudioElement | null>(null);
    const [playing, setPlaying] = useState(false);
    const [position, setPosition] = useState(0);
    const [duration, setDuration] = useState(durationSeconds ?? 0);
    const [speed, setSpeed] = useState(1);

    useEffect(() => {
        const audio = audioRef.current;
        if (!audio) return;

        const onTime = () => setPosition(audio.currentTime || 0);
        const onMeta = () => {
            if (Number.isFinite(audio.duration) && audio.duration > 0) {
                setDuration(audio.duration);
            }
        };
        const onEnded = () => {
            setPlaying(false);
            setPosition(0);
        };

        audio.addEventListener('timeupdate', onTime);
        audio.addEventListener('loadedmetadata', onMeta);
        audio.addEventListener('ended', onEnded);
        return () => {
            audio.removeEventListener('timeupdate', onTime);
            audio.removeEventListener('loadedmetadata', onMeta);
            audio.removeEventListener('ended', onEnded);
        };
    }, [src]);

    const toggle = async () => {
        const audio = audioRef.current;
        if (!audio) return;
        if (playing) {
            audio.pause();
            setPlaying(false);
            return;
        }
        document.querySelectorAll('audio[data-chat-voice]').forEach((node) => {
            if (node !== audio) {
                (node as HTMLAudioElement).pause();
            }
        });
        audio.playbackRate = speed;
        await audio.play();
        setPlaying(true);
    };

    const cycleSpeed = () => {
        const next = speed === 1 ? 1.5 : speed === 1.5 ? 2 : 1;
        setSpeed(next);
        if (audioRef.current) audioRef.current.playbackRate = next;
    };

    const progress = duration > 0 ? Math.min(1, position / duration) : 0;

    return (
        <div className="flex min-w-[13.5rem] items-center gap-2">
            <audio ref={audioRef} src={src} preload="metadata" data-chat-voice hidden />
            <button
                type="button"
                onClick={() => void toggle()}
                className={cn(
                    'flex h-10 w-10 shrink-0 items-center justify-center rounded-full',
                    mine ? 'bg-white text-orange-500' : 'bg-orange-500 text-white',
                )}
                aria-label={playing ? 'Pause voice note' : 'Play voice note'}
            >
                {playing ? <Pause className="h-5 w-5" fill="currentColor" /> : <Play className="ml-0.5 h-5 w-5" fill="currentColor" />}
            </button>
            <div className="min-w-0 flex-1">
                <input
                    type="range"
                    min={0}
                    max={1000}
                    value={Math.round(progress * 1000)}
                    onChange={(e) => {
                        const audio = audioRef.current;
                        if (!audio || duration <= 0) return;
                        const next = (Number(e.target.value) / 1000) * duration;
                        audio.currentTime = next;
                        setPosition(next);
                    }}
                    className={cn(
                        'h-1.5 w-full cursor-pointer appearance-none rounded-full',
                        mine ? 'bg-orange-300' : 'bg-gray-200',
                    )}
                />
                <div className={cn('mt-1 flex items-center justify-between text-[11px] font-semibold', mine ? 'text-orange-50' : 'text-gray-500')}>
                    <span>
                        {clock(position)} / {clock(duration)}
                    </span>
                    <button type="button" onClick={cycleSpeed} className={cn('font-extrabold', mine ? 'text-white' : 'text-gray-800')}>
                        {speed === 1 ? '1' : speed === 1.5 ? '1.5' : '2'}x
                    </button>
                </div>
            </div>
        </div>
    );
}
