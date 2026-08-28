import { cn } from '@/lib/utils';
import { normalizeWaveformBars } from '@/lib/voice-waveform';

type VoiceWaveformBarsProps = {
    samples: number[];
    progress?: number | null;
    barCount?: number;
    className?: string;
    activeClassName?: string;
    inactiveClassName?: string;
    onSeek?: (progress: number) => void;
};

export default function VoiceWaveformBars({
    samples,
    progress = null,
    barCount = 36,
    className,
    activeClassName = 'bg-[#111B21]',
    inactiveClassName = 'bg-[#8696A0]/45',
    onSeek,
}: VoiceWaveformBarsProps) {
    const bars = normalizeWaveformBars(samples, barCount);
    const played = progress == null ? null : Math.max(0, Math.min(1, progress));

    return (
        <div
            className={cn('flex h-7 min-w-0 flex-1 items-center gap-[2px]', className, onSeek && 'cursor-pointer')}
            role={onSeek ? 'slider' : undefined}
            aria-valuemin={0}
            aria-valuemax={1000}
            aria-valuenow={played == null ? undefined : Math.round(played * 1000)}
            onClick={
                onSeek
                    ? (e) => {
                          const rect = e.currentTarget.getBoundingClientRect();
                          onSeek(Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width)));
                      }
                    : undefined
            }
        >
            {bars.map((height, index) => {
                const isPlayed = played == null ? true : index / bars.length <= played;
                return (
                    <span
                        key={index}
                        className={cn(
                            'w-[2px] shrink-0 rounded-full transition-colors',
                            isPlayed ? activeClassName : inactiveClassName,
                        )}
                        style={{ height: `${Math.max(18, height * 100)}%` }}
                    />
                );
            })}
        </div>
    );
}
