import { Check, ScanSearch, Sparkles } from 'lucide-react';
import { useEffect, useState } from 'react';

import { cn } from '@/lib/utils';

const STEPS = [
    {
        id: 'upload',
        title: 'Uploading your photo',
        detail: 'Securely sending your image so we can start matching.',
    },
    {
        id: 'understand',
        title: 'Understanding your request',
        detail: 'Our AI reads your photo — colors, shape, and product type.',
    },
    {
        id: 'analyze',
        title: 'Analyzing visual details',
        detail: 'Looking at patterns, branding cues, and similar listings.',
    },
    {
        id: 'match',
        title: 'Finding matching products',
        detail: 'Comparing your image with items live in CityShop.',
    },
    {
        id: 'rank',
        title: 'Ranking best matches',
        detail: 'Sorting the closest products so the best ones show first.',
    },
] as const;

interface DeepSearchOverlayProps {
    open: boolean;
    previewUrl?: string | null;
    /** Advance through steps while the server request runs. */
    active?: boolean;
}

export default function DeepSearchOverlay({ open, previewUrl, active = true }: DeepSearchOverlayProps) {
    const [stepIndex, setStepIndex] = useState(0);

    useEffect(() => {
        if (!open) {
            setStepIndex(0);
            return;
        }

        if (!active) return;

        setStepIndex(0);
        const timers: number[] = [];
        // Stagger step advances so the animation feels deliberate
        const delays = [700, 1600, 2600, 3600, 4600];
        delays.forEach((delay, i) => {
            timers.push(
                window.setTimeout(() => {
                    setStepIndex(i);
                }, delay),
            );
        });

        return () => timers.forEach((t) => window.clearTimeout(t));
    }, [open, active]);

    if (!open) return null;

    const progress = ((stepIndex + 1) / STEPS.length) * 100;

    return (
        <div className="fixed inset-0 z-[80] flex items-end justify-center bg-slate-950/55 p-4 backdrop-blur-[2px] sm:items-center">
            <div className="absolute inset-0" aria-hidden />

            <div
                role="status"
                aria-live="polite"
                className="relative w-full max-w-lg overflow-hidden rounded-[1.75rem] border border-white/20 bg-white shadow-2xl shadow-orange-900/20"
            >
                <div className="bg-gradient-to-r from-orange-500 via-amber-500 to-emerald-500 px-5 py-4 text-white">
                    <div className="flex items-center gap-2">
                        <span className="flex h-8 w-8 items-center justify-center rounded-full bg-white/20">
                            <Sparkles className="h-4 w-4" />
                        </span>
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-[0.18em] text-white/80">Deep Search</p>
                            <p className="text-lg font-bold leading-tight">Finding products like your photo</p>
                        </div>
                    </div>
                    <div className="mt-4 h-1.5 overflow-hidden rounded-full bg-white/25">
                        <div
                            className="h-full rounded-full bg-white transition-all duration-700 ease-out"
                            style={{ width: `${progress}%` }}
                        />
                    </div>
                </div>

                <div className="grid gap-5 p-5 sm:grid-cols-[7.5rem_1fr] sm:items-start">
                    <div className="relative mx-auto w-28 sm:mx-0">
                        <div className="overflow-hidden rounded-2xl border border-slate-100 bg-slate-50 shadow-inner">
                            {previewUrl ? (
                                <img src={previewUrl} alt="" className="aspect-square w-full object-cover" />
                            ) : (
                                <div className="flex aspect-square items-center justify-center text-slate-300">
                                    <ScanSearch className="h-8 w-8" />
                                </div>
                            )}
                        </div>
                        <span className="pointer-events-none absolute inset-x-2 top-2 h-8 overflow-hidden rounded-xl">
                            <span className="absolute inset-x-0 h-full animate-[deep-scan_1.6s_ease-in-out_infinite] bg-gradient-to-b from-orange-400/0 via-orange-400/50 to-orange-400/0" />
                        </span>
                    </div>

                    <div className="min-w-0 space-y-3">
                        <div className="flex items-center justify-center gap-1.5 sm:justify-start">
                            {[0, 1, 2].map((i) => (
                                <span
                                    key={i}
                                    className="h-2 w-2 rounded-full bg-emerald-400"
                                    style={{
                                        animation: `deep-dot 1s ease-in-out ${i * 0.18}s infinite`,
                                    }}
                                />
                            ))}
                            <span className="ml-2 text-sm font-medium text-slate-500">Please wait…</span>
                        </div>

                        <ul className="space-y-2.5">
                            {STEPS.map((step, index) => {
                                const done = index < stepIndex;
                                const current = index === stepIndex;

                                return (
                                    <li
                                        key={step.id}
                                        className={cn(
                                            'rounded-2xl border px-3 py-2.5 transition-all duration-500',
                                            current
                                                ? 'border-orange-200 bg-orange-50 shadow-sm'
                                                : done
                                                    ? 'border-emerald-100 bg-emerald-50/60'
                                                    : 'border-transparent bg-slate-50/80 opacity-55',
                                        )}
                                    >
                                        <div className="flex items-start gap-2.5">
                                            <span
                                                className={cn(
                                                    'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold',
                                                    done
                                                        ? 'bg-emerald-500 text-white'
                                                        : current
                                                            ? 'bg-orange-500 text-white'
                                                            : 'bg-slate-200 text-slate-500',
                                                )}
                                            >
                                                {done ? <Check className="h-3 w-3" strokeWidth={3} /> : index + 1}
                                            </span>
                                            <div className="min-w-0">
                                                <p
                                                    className={cn(
                                                        'text-sm font-semibold',
                                                        current ? 'text-orange-900' : done ? 'text-emerald-800' : 'text-slate-500',
                                                    )}
                                                >
                                                    {step.title}
                                                </p>
                                                {(current || done) && (
                                                    <p className="mt-0.5 text-xs leading-relaxed text-slate-500">
                                                        {step.detail}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                </div>
            </div>

            <style>{`
                @keyframes deep-dot {
                    0%, 80%, 100% { transform: scale(0.65); opacity: 0.35; }
                    40% { transform: scale(1.15); opacity: 1; }
                }
                @keyframes deep-scan {
                    0% { transform: translateY(-120%); }
                    100% { transform: translateY(220%); }
                }
            `}</style>
        </div>
    );
}
