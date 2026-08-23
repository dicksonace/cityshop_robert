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
            <h3 className="font-semibold text-gray-900">Security controls</h3>
            <p className="mt-1 text-sm text-gray-500">
                <strong>Blacklist</strong> locks the account for security — they cannot sign in or register again with the same email or phone.
                <strong className="ml-1">Delete account</strong> removes them and frees email/phone so they can sign up fresh.
            </p>

            {isBlocked && blockReason && (
                <p className="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">Blacklisted: {blockReason}</p>
            )}

            <div className="mt-4 space-y-4">
                {isBlocked ? (
                    <Button type="button" className="bg-green-600 hover:bg-green-700" onClick={unblock}>
                        <ShieldOff className="mr-2 h-4 w-4" />
                        Remove blacklist
                    </Button>
                ) : !showBlock ? (
                    <Button type="button" variant="destructive" onClick={() => setShowBlock(true)}>
                        <Ban className="mr-2 h-4 w-4" />
                        Blacklist user
                    </Button>
                ) : (
                    <div className="space-y-2 rounded-lg border border-red-100 bg-red-50/50 p-3">
                        <p className="text-sm font-medium text-gray-900">Blacklist for security</p>
                        <p className="text-xs text-gray-600">They stay in the system but cannot log in or re-register with the same email or phone.</p>
                        <Input
                            placeholder="Security reason..."
                            value={blockReasonInput}
                            onChange={(e) => setBlockReasonInput(e.target.value)}
                        />
                        <div className="flex flex-wrap gap-2">
                            <Button type="button" variant="destructive" size="sm" onClick={block} disabled={!blockReasonInput.trim()}>
                                Confirm blacklist
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
                        Delete account (allow re-register)
                    </Button>
                ) : (
                    <div className="space-y-3 rounded-lg border border-red-200 bg-red-50/40 p-4">
                        <div>
                            <p className="font-semibold text-red-900">Delete account (allow re-register)</p>
                            <p className="mt-1 text-sm text-red-800">
                                Removes their login completely. Email and phone are released so they can create a new account.
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
