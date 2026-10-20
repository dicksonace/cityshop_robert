import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

export default function WithdrawalRestrictionForm({
    restricted,
    note,
    action,
}: {
    restricted: boolean;
    note?: string | null;
    action: string;
}) {
    const form = useForm({
        restricted,
        note: note ?? '',
    });

    const save: FormEventHandler = (event) => {
        event.preventDefault();
        form.post(action, { preserveScroll: true });
    };

    return (
        <form onSubmit={save} className="rounded-xl border border-red-100 bg-red-50/50 p-4">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <Label htmlFor="withdrawal-restricted">Restricted 🚫</Label>
                    <p className="mt-1 text-sm text-gray-600">
                        Blocks withdrawal for this account. The note below is the message they see.
                    </p>
                </div>
                <input
                    id="withdrawal-restricted"
                    type="checkbox"
                    className="mt-1 h-5 w-5 rounded border-gray-300 text-red-600"
                    checked={form.data.restricted}
                    onChange={(event) => form.setData('restricted', event.target.checked)}
                />
            </div>
            <label className="mt-3 block text-sm font-medium text-gray-700" htmlFor="withdrawal-restriction-note">
                Note message
            </label>
            <textarea
                id="withdrawal-restriction-note"
                value={form.data.note}
                onChange={(event) => form.setData('note', event.target.value)}
                rows={3}
                maxLength={1000}
                placeholder="Write the message they will see on withdraw"
                className="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm"
            />
            {form.errors.note && <p className="mt-1 text-sm text-red-600">{form.errors.note}</p>}
            <Button type="submit" disabled={form.processing} className="mt-3 bg-red-600 hover:bg-red-700">
                Save restriction
            </Button>
        </form>
    );
}
