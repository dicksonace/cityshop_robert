import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { QRCodeSVG } from 'qrcode.react';
import { Copy, QrCode } from 'lucide-react';
import { FormEvent, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ShopLayout from '@/layouts/shop-layout';
import { SharedData } from '@/types';

type QrData = {
    payload: string;
    user: { id: number; name: string; mobile?: string | null };
    amount?: number | null;
    reason?: string | null;
};

type Props = {
    qr: QrData;
    amount?: number | null;
    reason?: string | null;
};

export default function BuyerQrReceive({ qr, amount, reason }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [copied, setCopied] = useState(false);

    const form = useForm({
        amount: amount != null ? String(amount) : '',
        reason: reason ?? '',
    });

    const refreshQr = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('wallet.qr.receive'),
            {
                amount: form.data.amount.trim() || undefined,
                reason: form.data.reason.trim() || undefined,
            },
            { preserveScroll: true },
        );
    };

    const copyPayload = async () => {
        try {
            await navigator.clipboard.writeText(qr.payload);
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        } catch {
            // ignore
        }
    };

    return (
        <ShopLayout>
            <Head title="My QR" />
            <div className="mx-auto max-w-lg px-4 py-6 sm:py-8">
                <Link href={route('wallet.index')} className="text-sm font-semibold text-orange-600 hover:underline">
                    &larr; Back to wallet
                </Link>

                <div className="mt-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                    <div className="flex items-center gap-3">
                        <span className="flex h-11 w-11 items-center justify-center rounded-full bg-orange-50 text-orange-600">
                            <QrCode className="h-5 w-5" />
                        </span>
                        <div>
                            <h1 className="text-xl font-bold text-gray-900">My QR code</h1>
                            <p className="text-sm text-gray-500">Others scan or paste this to pay you from their wallet</p>
                        </div>
                    </div>

                    {flash?.success && (
                        <p className="mt-4 rounded-xl bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{flash.success}</p>
                    )}

                    <div className="mt-6 flex flex-col items-center rounded-2xl border border-gray-100 bg-gray-50 p-6">
                        <QRCodeSVG value={qr.payload} size={220} level="M" includeMargin />
                        <p className="mt-4 text-center text-lg font-bold text-gray-900">{qr.user.name}</p>
                        {qr.amount != null && (
                            <p className="mt-1 text-sm font-semibold text-orange-600">Request: GH₵{Number(qr.amount).toFixed(2)}</p>
                        )}
                        {qr.reason && <p className="mt-1 text-center text-sm text-gray-600">{qr.reason}</p>}
                        <button
                            type="button"
                            onClick={copyPayload}
                            className="mt-4 inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50"
                        >
                            <Copy className="h-3.5 w-3.5" />
                            {copied ? 'Copied' : 'Copy code'}
                        </button>
                    </div>

                    <form onSubmit={refreshQr} className="mt-6 space-y-3 border-t border-gray-100 pt-5">
                        <p className="text-sm font-semibold text-gray-900">Optional fixed amount</p>
                        <div>
                            <Label htmlFor="qr-amount">Amount (GHS)</Label>
                            <Input
                                id="qr-amount"
                                inputMode="decimal"
                                placeholder="Leave blank for any amount"
                                value={form.data.amount}
                                onChange={(e) => form.setData('amount', e.target.value)}
                                className="mt-1"
                            />
                        </div>
                        <div>
                            <Label htmlFor="qr-reason">Reason (optional)</Label>
                            <Input
                                id="qr-reason"
                                maxLength={80}
                                placeholder="e.g. Market payment"
                                value={form.data.reason}
                                onChange={(e) => form.setData('reason', e.target.value)}
                                className="mt-1"
                            />
                        </div>
                        <Button type="submit" variant="outline" className="w-full">
                            Update QR
                        </Button>
                    </form>

                    <Link
                        href={route('wallet.qr.pay')}
                        className="mt-4 block text-center text-sm font-semibold text-orange-600 hover:underline"
                    >
                        Scan or pay someone else &rarr;
                    </Link>
                </div>
            </div>
        </ShopLayout>
    );
}
