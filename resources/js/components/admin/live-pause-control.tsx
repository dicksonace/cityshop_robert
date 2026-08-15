import { router } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useState } from 'react';

type Props = {
    title: string;
    description: string;
    enabled: boolean;
    open: boolean;
    instructions: string;
    updateUrl: string;
    /** Extra fields merged into the POST (e.g. sell RMB receive_instructions). */
    extra?: Record<string, string>;
};

/**
 * Big Live / Pause control for China Transfer and Sell RMB.
 * Pause stops new buyer requests immediately; rates and methods stay configured.
 */
export default function LivePauseControl({
    title,
    description,
    enabled,
    open,
    instructions,
    updateUrl,
    extra = {},
}: Props) {
    const [saving, setSaving] = useState(false);

    const setLive = (next: boolean) => {
        setSaving(true);
        router.post(
            updateUrl,
            {
                enabled: next,
                instructions,
                ...extra,
            },
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
            },
        );
    };

    const statusLabel = !enabled ? 'Paused' : open ? 'Live' : 'Enabled — finish rate / methods to go live';
    const statusClass = !enabled
        ? 'border-amber-200 bg-amber-50 text-amber-900'
        : open
          ? 'border-emerald-200 bg-emerald-50 text-emerald-900'
          : 'border-sky-200 bg-sky-50 text-sky-900';

    return (
        <div className={`space-y-4 rounded-2xl border p-5 shadow-sm ${statusClass}`}>
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-base font-bold">{title}</h2>
                    <p className="mt-1 text-sm opacity-80">{description}</p>
                </div>
                <span className="rounded-full bg-white/80 px-3 py-1 text-xs font-extrabold uppercase tracking-wide ring-1 ring-black/5">
                    {statusLabel}
                </span>
            </div>

            <div className="flex flex-wrap gap-2">
                <button
                    type="button"
                    disabled={saving || enabled}
                    onClick={() => setLive(true)}
                    className={`inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold transition ${
                        enabled
                            ? 'bg-emerald-600 text-white shadow-sm'
                            : 'bg-white text-emerald-800 ring-1 ring-emerald-200 hover:bg-emerald-50'
                    } disabled:cursor-not-allowed disabled:opacity-70`}
                >
                    {saving && !enabled ? <LoaderCircle className="h-4 w-4 animate-spin" /> : null}
                    Live
                </button>
                <button
                    type="button"
                    disabled={saving || !enabled}
                    onClick={() => setLive(false)}
                    className={`inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold transition ${
                        !enabled
                            ? 'bg-amber-600 text-white shadow-sm'
                            : 'bg-white text-amber-900 ring-1 ring-amber-200 hover:bg-amber-50'
                    } disabled:cursor-not-allowed disabled:opacity-70`}
                >
                    {saving && enabled ? <LoaderCircle className="h-4 w-4 animate-spin" /> : null}
                    Pause
                </button>
            </div>

            <p className="text-xs opacity-75">
                Pause shows the Paused badge on the app and blocks new requests. Live turns it back on when a rate and
                method are ready.
            </p>
        </div>
    );
}
