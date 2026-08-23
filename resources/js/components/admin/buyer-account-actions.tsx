import { router } from '@inertiajs/react';
import { Ban, ShieldOff, Trash2 } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

interface BuyerAccountActionsProps {
    buyerId: number;
    email: string;
    isBlocked: boolean;
    blockReason?: string | null;
}

export default function BuyerAccountActions({ buyerId, email, isBlocked, blockReason }: BuyerAccountActionsProps) {
    const [blockReasonInput, setBlockReasonInput] = useState('');
    const [deleteReason, setDeleteReason] = useState('');
    const [confirmEmail, setConfirmEmail] = useState('');
    const [showBlock, setShowBlock] = useState(false);
    const [showDelete, setShowDelete] = useState(false);

    const block = () => {
        if (!blockReasonInput.trim()) return;
        router.post(route('admin.buyers.block', buyerId), { reason: blockReasonInput }, {
            onSuccess: () => {
                setShowBlock(false);
                setBlockReasonInput('');
            },
        });
    };

    const unblock = () => {
        if (!confirm('Unblock this buyer? They can sign in and shop again.')) return;
        router.post(route('admin.buyers.unblock', buyerId));
    };

    const destroy = () => {
        if (!deleteReason.trim() || !confirmEmail.trim()) return;
        router.delete(route('admin.buyers.destroy', buyerId), {
            data: { reason: deleteReason, confirm_email: confirmEmail },
        });
    };

    return (
        <div className="mt-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h3 className="font-semibold text-gray-900">Account controls</h3>
            <p className="mt-1 text-sm text-gray-500">
                Block stops sign-in. Delete removes the account and frees their email and phone for a new registration.
            </p>

            {isBlocked && blockReason && (
                <p className="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">Block reason: {blockReason}</p>
            )}

            <div className="mt-4 space-y-4">
                {isBlocked ? (
                    <Button type="button" className="bg-green-600 hover:bg-green-700" onClick={unblock}>
                        <ShieldOff className="mr-2 h-4 w-4" />
                        Unblock buyer
                    </Button>
                ) : !showBlock ? (
                    <Button type="button" variant="destructive" onClick={() => setShowBlock(true)}>
                        <Ban className="mr-2 h-4 w-4" />
                        Block buyer
                    </Button>
                ) : (
                    <div className="space-y-2 rounded-lg border border-red-100 bg-red-50/50 p-3">
                        <p className="text-sm font-medium text-gray-900">Block buyer account</p>
                        <Input
                            placeholder="Reason for blocking..."
                            value={blockReasonInput}
                            onChange={(e) => setBlockReasonInput(e.target.value)}
                        />
                        <div className="flex flex-wrap gap-2">
                            <Button type="button" variant="destructive" size="sm" onClick={block} disabled={!blockReasonInput.trim()}>
                                Confirm block
                            </Button>
                            <Button type="button" variant="ghost" size="sm" onClick={() => setShowBlock(false)}>
                                Cancel
                            </Button>
                        </div>
                    </div>
                )}

                {!showDelete ? (
                    <Button type="button" variant="outline" className="border-red-200 text-red-700 hover:bg-red-50" onClick={() => setShowDelete(true)}>
                        <Trash2 className="mr-2 h-4 w-4" />
                        Delete buyer account
                    </Button>
                ) : (
                    <div className="space-y-3 rounded-lg border border-red-200 bg-red-50/40 p-4">
                        <div>
                            <p className="font-semibold text-red-900">Delete buyer account</p>
                            <p className="mt-1 text-sm text-red-800">
                                This removes their login. Their email and phone number will be released so they can register again.
                            </p>
                        </div>
                        <Input placeholder="Reason for deletion..." value={deleteReason} onChange={(e) => setDeleteReason(e.target.value)} />
                        <div>
                            <Input
                                placeholder={`Type "${email}" to confirm`}
                                value={confirmEmail}
                                onChange={(e) => setConfirmEmail(e.target.value)}
                            />
                            <p className="mt-1 text-xs text-red-700">Type the buyer email exactly to confirm deletion.</p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="destructive"
                                size="sm"
                                onClick={destroy}
                                disabled={!deleteReason.trim() || !confirmEmail.trim()}
                            >
                                <Trash2 className="mr-1 h-3.5 w-3.5" />
                                Permanently delete account
                            </Button>
                            <Button type="button" variant="ghost" size="sm" onClick={() => setShowDelete(false)}>
                                Cancel
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
