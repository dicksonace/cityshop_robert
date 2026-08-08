import { router } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useToastOptional } from '@/contexts/toast-context';
import * as chatApi from '@/lib/chat-api';
import { cn } from '@/lib/utils';
import type { ChatMessage } from '@/types/chat';
import { formatPrice, productImageUrl } from '@/types/marketplace';

interface ChatTransferSheetProps {
    open: boolean;
    conversationId: number | null;
    fallbackRecipientName?: string;
    fallbackRecipientAvatar?: string | null;
    onOpenChange: (open: boolean) => void;
    onSent: (message: ChatMessage) => void;
}

export default function ChatTransferSheet({
    open,
    conversationId,
    fallbackRecipientName,
    fallbackRecipientAvatar,
    onOpenChange,
    onSent,
}: ChatTransferSheetProps) {
    const toast = useToastOptional();
    const [loadingMeta, setLoadingMeta] = useState(false);
    const [sending, setSending] = useState(false);
    const [amount, setAmount] = useState('');
    const [note, setNote] = useState('');
    const [showNote, setShowNote] = useState(false);
    const [pin, setPin] = useState('');
    const [balance, setBalance] = useState<number | null>(null);
    const [hasPin, setHasPin] = useState(true);
    const [recipientName, setRecipientName] = useState(fallbackRecipientName ?? 'recipient');
    const [recipientMobile, setRecipientMobile] = useState<string | null>(null);
    const [recipientAvatar, setRecipientAvatar] = useState<string | null>(fallbackRecipientAvatar ?? null);

    useEffect(() => {
        if (!open || !conversationId) return;

        let cancelled = false;
        setAmount('');
        setNote('');
        setShowNote(false);
        setPin('');
        setLoadingMeta(true);
        setRecipientName(fallbackRecipientName ?? 'recipient');
        setRecipientAvatar(fallbackRecipientAvatar ?? null);
        setRecipientMobile(null);

        chatApi
            .fetchChatTransferMeta(conversationId)
            .then((meta) => {
                if (cancelled) return;
                setBalance(meta.available_balance);
                setHasPin(meta.has_payment_pin);
                setRecipientName(meta.recipient.name || fallbackRecipientName || 'recipient');
                setRecipientMobile(meta.recipient.mobile?.trim() || null);
                setRecipientAvatar(meta.recipient.avatar ?? fallbackRecipientAvatar ?? null);
            })
            .catch((err: unknown) => {
                if (cancelled) return;
                toast?.error(err instanceof Error ? err.message : 'Could not load transfer details');
                onOpenChange(false);
            })
            .finally(() => {
                if (!cancelled) setLoadingMeta(false);
            });

        return () => {
            cancelled = true;
        };
    }, [open, conversationId, fallbackRecipientName, fallbackRecipientAvatar, onOpenChange, toast]);

    const parsedAmount = (() => {
        const n = Number(amount);
        return Number.isFinite(n) ? n : null;
    })();

    const amountError = (() => {
        if (parsedAmount === null || amount.trim() === '') return null;
        if (parsedAmount < 1) return 'Minimum transfer is GH₵1.00.';
        if (balance !== null && parsedAmount > balance + 0.0001) {
            return `Insufficient balance. You have ${formatPrice(balance)} available.`;
        }
        if (parsedAmount > 50000) return 'Maximum transfer is GH₵50,000.00 per send.';
        return null;
    })();

    const pinError =
        pin.length > 0 && pin.length < 4 ? 'Payment PIN must be 4 digits.' : null;

    const canSend =
        !loadingMeta &&
        !sending &&
        hasPin &&
        parsedAmount !== null &&
        parsedAmount >= 1 &&
        amountError === null &&
        pin.length === 4;

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        if (!conversationId || parsedAmount === null) return;

        if (amountError) {
            toast?.error(amountError);
            return;
        }
        if (pin.length !== 4) {
            toast?.error('Enter your 4-digit payment PIN.');
            return;
        }
        if (!canSend) return;

        setSending(true);
        try {
            const message = await chatApi.sendChatTransfer(conversationId, {
                amount: parsedAmount,
                note: showNote ? note : undefined,
                payment_pin: pin,
            });
            onSent(message);
            onOpenChange(false);
            toast?.success(`Transferred ${formatPrice(parsedAmount)}`);
        } catch (err: unknown) {
            toast?.error(err instanceof Error ? err.message : 'Transfer failed');
        } finally {
            setSending(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md gap-0 overflow-hidden p-0 sm:rounded-2xl">
                <form onSubmit={submit}>
                    <DialogHeader className="space-y-1 border-b border-gray-100 px-5 py-4 text-left">
                        <DialogTitle className="text-base">Transfer money</DialogTitle>
                        <DialogDescription className="text-sm text-gray-500">
                            Send GH₵ from your CityShop wallet to {recipientName}.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 px-5 py-4">
                        {loadingMeta ? (
                            <div className="flex items-center justify-center gap-2 py-10 text-sm text-gray-500">
                                <LoaderCircle className="h-4 w-4 animate-spin" />
                                Loading…
                            </div>
                        ) : (
                            <>
                                <div className="flex items-start gap-3">
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-semibold text-gray-900">
                                            Transfer to {recipientName}
                                        </p>
                                        <p className="mt-0.5 text-xs text-gray-500">
                                            {recipientMobile
                                                ? `Mobile: ${recipientMobile}`
                                                : balance !== null
                                                  ? `Available ${formatPrice(balance)}`
                                                  : 'From your wallet'}
                                        </p>
                                    </div>
                                    <div className="h-11 w-11 shrink-0 overflow-hidden rounded-lg bg-emerald-500 text-center text-lg font-bold leading-[2.75rem] text-white">
                                        {recipientAvatar ? (
                                            <img
                                                src={productImageUrl(recipientAvatar)}
                                                alt=""
                                                className="h-full w-full object-cover"
                                            />
                                        ) : (
                                            (recipientName.trim()[0] || '?').toUpperCase()
                                        )}
                                    </div>
                                </div>

                                {!hasPin ? (
                                    <div className="rounded-xl bg-amber-50 px-3 py-3 text-sm text-amber-900 ring-1 ring-amber-100">
                                        Set a payment PIN before transferring.
                                        <button
                                            type="button"
                                            className="mt-2 block font-semibold text-orange-600 underline"
                                            onClick={() => {
                                                onOpenChange(false);
                                                router.visit(route('payment-pin.edit'));
                                            }}
                                        >
                                            Set payment PIN
                                        </button>
                                    </div>
                                ) : (
                                    <>
                                        <div>
                                            <label className="text-xs font-medium text-gray-500">
                                                Amount (GHS)
                                            </label>
                                            <div
                                                className={cn(
                                                    'mt-1.5 flex items-center gap-2 rounded-xl border px-3 focus-within:ring-1',
                                                    amountError
                                                        ? 'border-red-300 focus-within:border-red-400 focus-within:ring-red-200'
                                                        : 'border-gray-200 focus-within:border-orange-300 focus-within:ring-orange-300',
                                                )}
                                            >
                                                <span className="text-lg font-semibold text-gray-900">GH₵</span>
                                                <Input
                                                    value={amount}
                                                    onChange={(e) => {
                                                        const next = e.target.value
                                                            .replace(/[^\d.]/g, '')
                                                            .replace(/(\..*)\./g, '$1');
                                                        const parts = next.split('.');
                                                        if (parts[0].length > 8) return;
                                                        if (parts[1]?.length > 2) return;
                                                        setAmount(next);
                                                    }}
                                                    inputMode="decimal"
                                                    placeholder="0.00"
                                                    className="border-0 px-0 text-2xl font-semibold shadow-none focus-visible:ring-0"
                                                    autoFocus
                                                />
                                            </div>
                                            {amountError ? (
                                                <p className="mt-1.5 text-xs font-medium text-red-600">{amountError}</p>
                                            ) : (
                                                balance !== null && (
                                                    <p className="mt-1.5 text-xs text-gray-400">
                                                        Available {formatPrice(balance)}
                                                    </p>
                                                )
                                            )}
                                        </div>

                                        {!showNote ? (
                                            <button
                                                type="button"
                                                onClick={() => setShowNote(true)}
                                                className="text-sm font-medium text-[#576B95] hover:underline"
                                            >
                                                Add note
                                            </button>
                                        ) : (
                                            <div>
                                                <label className="text-xs font-medium text-gray-500">Note</label>
                                                <Input
                                                    value={note}
                                                    onChange={(e) => setNote(e.target.value.slice(0, 120))}
                                                    placeholder="Optional note"
                                                    className="mt-1.5"
                                                    maxLength={120}
                                                />
                                            </div>
                                        )}

                                        <div>
                                            <label className="text-xs font-medium text-gray-500">
                                                Payment PIN
                                            </label>
                                            <Input
                                                type="password"
                                                inputMode="numeric"
                                                autoComplete="one-time-code"
                                                value={pin}
                                                onChange={(e) =>
                                                    setPin(e.target.value.replace(/\D/g, '').slice(0, 4))
                                                }
                                                placeholder="••••"
                                                className={cn(
                                                    'mt-1.5 tracking-[0.35em]',
                                                    pin.length > 0 && 'font-semibold',
                                                    pinError && 'border-red-300 focus-visible:ring-red-200',
                                                )}
                                                maxLength={4}
                                            />
                                            {pinError ? (
                                                <p className="mt-1.5 text-xs font-medium text-red-600">{pinError}</p>
                                            ) : (
                                                <p className="mt-1.5 text-xs text-gray-400">
                                                    Enter your 4-digit CityShop payment PIN to confirm.
                                                </p>
                                            )}
                                        </div>
                                    </>
                                )}
                            </>
                        )}
                    </div>

                    <DialogFooter className="border-t border-gray-100 bg-gray-50 px-5 py-3 sm:flex-row">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={!canSend}
                            className="bg-emerald-600 hover:bg-emerald-700"
                        >
                            {sending ? (
                                <>
                                    <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />
                                    Sending…
                                </>
                            ) : (
                                'Transfer'
                            )}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
