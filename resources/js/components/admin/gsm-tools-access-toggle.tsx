import { router } from '@inertiajs/react';

import { Label } from '@/components/ui/label';

export default function GsmToolsAccessToggle({
    enabled,
    action,
}: {
    enabled: boolean;
    action: string;
}) {
    return (
        <div className="rounded-xl border border-violet-100 bg-violet-50/70 p-4">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <Label htmlFor="gsm-tools-enabled">GSM Tools</Label>
                    <p className="mt-1 text-sm text-gray-600">
                        Off by default. Enable only for GSM engineers who should see GSM Tools.
                    </p>
                </div>
                <input
                    id="gsm-tools-enabled"
                    type="checkbox"
                    className="mt-1 h-5 w-5 rounded border-gray-300 text-violet-600"
                    checked={enabled}
                    onChange={(event) => {
                        router.post(action, { enabled: event.target.checked }, { preserveScroll: true });
                    }}
                />
            </div>
            <p className="mt-2 text-xs font-semibold uppercase tracking-wide text-violet-700">
                {enabled ? 'Enabled' : 'Disabled'}
            </p>
        </div>
    );
}
