import { router } from '@inertiajs/react';
import { Ban, ShieldOff, Trash2 } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

interface SellerAccountActionsProps {
    sellerId: number;
    status: string;
    storeName: string;
    compact?: boolean;
    className?: string;
}

export default function SellerAccountActions({
    sellerId,
    status,
    storeName,
    compact = false,
    className,
}: SellerAccountActionsProps) {
    const [blockReason, setBlockReason] = useState('');
    const [deleteReason, setDeleteReason] = useState('');
    const [confirmStoreName, setConfirmStoreName] = useState('');
    const [showBlock, setShowBlock] = useState(false);
    const [showDelete, setShowDelete] = useState(false);

    const block = () => {
        if (!blockReason.trim()) return;
        router.post(route('admin.sellers.block', sellerId), { reason: blockReason }, {
            onSuccess: () => {
                setShowBlock(false);
                setBlockReason('');
            },
        });
    };

    const unblock = () => {
        if (!confirm('Unblock this seller? Their products will be visible in the shop again.')) return;
        router.post(route('admin.sellers.unblock', sellerId));
    };

    const destroy = () => {
        if (!deleteReason.trim() || !confirmStoreName.trim()) return;
        router.delete(route('admin.sellers.destroy', sellerId), {
            data: { reason: deleteReason, confirm_store_name: confirmStoreName },
        });
    };

    if (status === 'suspended') {
        return (
            <div className={cn('space-y-3', className)}>
                <Button type="button" size={compact ? 'sm' : 'default'} className="bg-green-600 hover:bg-green-700" onClick={unblock}>
                    <ShieldOff className="mr-2 h-4 w-4" />
                    Unblock seller
                </Button>
                {!compact && (
                    <DeleteSellerForm
                        storeName={storeName}
                        deleteReason={deleteReason}
                        confirmStoreName={confirmStoreName}
                        onDeleteReasonChange={setDeleteReason}
                        onConfirmStoreNameChange={setConfirmStoreName}
                        onSubmit={destroy}
                    />
                )}
            </div>
        );
    }

    if (status === 'approved') {
        return (
            <div className={cn('space-y-4', className)}>
                {!showBlock ? (
                    <Button type="button" variant="destructive" size={compact ? 'sm' : 'default'} onClick={() => setShowBlock(true)}>
                        <Ban className="mr-2 h-4 w-4" />
                        Block seller
                    </Button>
                ) : (
                    <div className="space-y-2 rounded-lg border border-red-100 bg-red-50/50 p-3">
                        <p className="text-sm font-medium text-gray-900">Block seller account</p>
                        <Input
                            placeholder="Reason for blocking..."
                            value={blockReason}
                            onChange={(e) => setBlockReason(e.target.value)}
                        />
                        <div className="flex flex-wrap gap-2">
                            <Button type="button" variant="destructive" size="sm" onClick={block} disabled={!blockReason.trim()}>
                                Confirm block
                            </Button>
                            <Button type="button" variant="ghost" size="sm" onClick={() => setShowBlock(false)}>
                                Cancel
                            </Button>
                        </div>
                    </div>
                )}

                {!compact && (
                    <>
                        {!showDelete ? (
                            <Button type="button" variant="outline" className="border-red-200 text-red-700 hover:bg-red-50" onClick={() => setShowDelete(true)}>
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete seller account
                            </Button>
                        ) : (
                            <DeleteSellerForm
                                storeName={storeName}
                                deleteReason={deleteReason}
                                confirmStoreName={confirmStoreName}
                                onDeleteReasonChange={setDeleteReason}
                                onConfirmStoreNameChange={setConfirmStoreName}
                                onSubmit={destroy}
                                onCancel={() => setShowDelete(false)}
                            />
                        )}
                    </>
                )}
            </div>
        );
    }

    if (!compact && (status === 'pending' || status === 'rejected')) {
        return (
            <div className={cn('space-y-3', className)}>
                <DeleteSellerForm
                    storeName={storeName}
                    deleteReason={deleteReason}
                    confirmStoreName={confirmStoreName}
                    onDeleteReasonChange={setDeleteReason}
                    onConfirmStoreNameChange={setConfirmStoreName}
                    onSubmit={destroy}
                    helperText="Remove this application and delete the seller user account."
                />
            </div>
        );
    }

    return null;
}

function DeleteSellerForm({
    storeName,
    deleteReason,
    confirmStoreName,
    onDeleteReasonChange,
    onConfirmStoreNameChange,
    onSubmit,
    onCancel,
    helperText,
}: {
    storeName: string;
    deleteReason: string;
    confirmStoreName: string;
    onDeleteReasonChange: (value: string) => void;
    onConfirmStoreNameChange: (value: string) => void;
    onSubmit: () => void;
    onCancel?: () => void;
    helperText?: string;
}) {
    const canDelete = deleteReason.trim() && confirmStoreName.trim();

    return (
        <div className="space-y-3 rounded-lg border border-red-200 bg-red-50/40 p-4">
            <div>
                <p className="font-semibold text-red-900">Delete seller account</p>
                <p className="mt-1 text-sm text-red-800">
                    {helperText ?? 'This soft-deletes the seller user and all their products. They cannot log in again.'}
                </p>
            </div>
            <Input placeholder="Reason for deletion..." value={deleteReason} onChange={(e) => onDeleteReasonChange(e.target.value)} />
            <div>
                <Input
                    placeholder={`Type "${storeName}" to confirm`}
                    value={confirmStoreName}
                    onChange={(e) => onConfirmStoreNameChange(e.target.value)}
                />
                <p className="mt-1 text-xs text-red-700">Type the store name exactly to confirm deletion.</p>
            </div>
            <div className="flex flex-wrap gap-2">
                <Button type="button" variant="destructive" size="sm" onClick={onSubmit} disabled={!canDelete}>
                    <Trash2 className="mr-1 h-3.5 w-3.5" />
                    Permanently delete account
                </Button>
                {onCancel && (
                    <Button type="button" variant="ghost" size="sm" onClick={onCancel}>
                        Cancel
                    </Button>
                )}
            </div>
        </div>
    );
}
