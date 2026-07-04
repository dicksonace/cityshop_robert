import { router, usePage } from '@inertiajs/react';
import { Flag } from 'lucide-react';
import { FormEvent, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { SharedData } from '@/types';

const reasons = [
    { value: 'scam', label: 'Scam or fraud' },
    { value: 'counterfeit', label: 'Counterfeit or fake products' },
    { value: 'harassment', label: 'Harassment or abuse' },
    { value: 'poor_service', label: 'Poor service or unresponsive seller' },
    { value: 'prohibited_items', label: 'Prohibited or illegal items' },
    { value: 'fake_listings', label: 'Misleading or fake listings' },
    { value: 'other', label: 'Other' },
];

interface ReportSellerButtonProps {
    sellerId: number;
    productId?: number;
    storeName?: string;
    className?: string;
}

export default function ReportSellerButton({ sellerId, productId, storeName, className }: ReportSellerButtonProps) {
    const { auth } = usePage<SharedData>().props;
    const [open, setOpen] = useState(false);
    const [reason, setReason] = useState('scam');
    const [details, setDetails] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const openForm = () => {
        if (!auth.user) {
            router.visit(route('login'));
            return;
        }
        setOpen(true);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        setSubmitting(true);
        router.post(
            route('sellers.report'),
            {
                seller_id: sellerId,
                product_id: productId,
                reason,
                details,
            },
            {
                preserveScroll: true,
                onFinish: () => setSubmitting(false),
                onSuccess: () => {
                    setOpen(false);
                    setDetails('');
                    setReason('scam');
                },
            },
        );
    };

    if (auth.user?.id === sellerId) {
        return null;
    }

    return (
        <>
            <button
                type="button"
                onClick={openForm}
                className={className ?? 'inline-flex items-center gap-1.5 text-xs font-medium text-red-600 hover:text-red-700 hover:underline'}
            >
                <Flag className="h-3.5 w-3.5" />
                Report account
            </button>

            {open && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                        <h3 className="text-lg font-semibold text-gray-900">Report seller account</h3>
                        <p className="mt-1 text-sm text-gray-500">
                            Report {storeName ? <strong>{storeName}</strong> : 'this seller'} for review by CityShop admin.
                        </p>

                        <form onSubmit={submit} className="mt-4 space-y-4">
                            <div>
                                <Label htmlFor="report-reason">Reason</Label>
                                <select
                                    id="report-reason"
                                    value={reason}
                                    onChange={(e) => setReason(e.target.value)}
                                    className="mt-1 w-full rounded-md border border-gray-200 px-3 py-2 text-sm"
                                    required
                                >
                                    {reasons.map((item) => (
                                        <option key={item.value} value={item.value}>
                                            {item.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="report-details">Details (optional)</Label>
                                <textarea
                                    id="report-details"
                                    value={details}
                                    onChange={(e) => setDetails(e.target.value)}
                                    rows={4}
                                    maxLength={2000}
                                    placeholder="Tell us what happened..."
                                    className="mt-1 w-full rounded-md border border-gray-200 px-3 py-2 text-sm"
                                />
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button type="submit" disabled={submitting} variant="destructive">
                                    {submitting ? 'Submitting…' : 'Submit report'}
                                </Button>
                                <Button type="button" variant="outline" onClick={() => setOpen(false)} disabled={submitting}>
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </>
    );
}
