import { router } from '@inertiajs/react';

import { Label } from '@/components/ui/label';

export default function ChinaRmbAccessToggle({
    enabled,
    action,
}: {
    enabled: boolean;
    action: string;
}) {
    return (
        <div className="rounded-xl border border-orange-100 bg-orange-50/60 p-4">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <Label htmlFor="china-rmb-enabled">China / RMB</Label>
                    <p className="mt-1 text-sm text-gray-600">
                        Off by default. Enable only for people who should Buy RMB and Sell RMB.
                    </p>
                </div>
                <input
                    id="china-rmb-enabled"
                    type="checkbox"
                    className="mt-1 h-5 w-5 rounded border-gray-300 text-orange-600"
                    checked={enabled}
                    onChange={(event) => {
                        router.post(action, { enabled: event.target.checked }, { preserveScroll: true });
                    }}
                />
            </div>
            <p className="mt-2 text-xs font-semibold uppercase tracking-wide text-orange-700">
                {enabled ? 'Enabled' : 'Disabled'}
            </p>
        </div>
    );
}
