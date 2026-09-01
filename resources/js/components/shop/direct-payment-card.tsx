import { FormEvent, useEffect } from 'react';
import { LoaderCircle } from 'lucide-react';
import { useForm } from '@inertiajs/react';

import DocumentUploadField from '@/components/forms/document-upload-field';
import DirectPaymentDetails from '@/components/shop/direct-payment-details';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    clearPaymentReference,
    loadPaymentReference,
    savePaymentReference,
} from '@/lib/checkout-draft';
import { isBankPaymentMethod, bankPaymentTitle } from '@/lib/payment-method-display';
import { formatPrice, productImageUrl } from '@/types/marketplace';

type PaymentMethod = {
    account_name: string;
    account_number?: string | null;
    network?: string | null;
    bank_name?: string | null;
    type?: string;
    instructions?: string | null;
};

type DirectPaymentOrder = {
    id: number;
    order_number: string;
    total: number;
    payment_status: string;
    direct_payment_reference?: string | null;
    direct_payment_proof_path?: string | null;
    direct_payment_rejection_reason?: string | null;
    seller_payment_method?: PaymentMethod | null;
};

type Props = {
    order: DirectPaymentOrder;
    /** Hide order header row when embedded on order detail page */
    compact?: boolean;
};

export default function DirectPaymentCard({ order, compact = false }: Props) {
    const method = order.seller_payment_method;
    const { data, setData, post, processing, errors } = useForm<{
        reference: string;
        proof: File | null;
    }>({
        reference: order.direct_payment_reference ?? loadPaymentReference(order.id) ?? '',
        proof: null,
    });

    useEffect(() => {
        savePaymentReference(order.id, data.reference);
    }, [order.id, data.reference]);

    const accountNumber = method?.account_number ?? '';
    const isBank = isBankPaymentMethod(method);
    const bankTitle = bankPaymentTitle(method);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('orders.direct-payment', order.id), {
            forceFormData: true,
            onSuccess: () => clearPaymentReference(order.id),
        });
    };

    const existingProof = order.direct_payment_proof_path
        ? productImageUrl(order.direct_payment_proof_path)
        : null;

    return (
        <div className={compact ? 'space-y-4' : 'rounded-xl border border-gray-100 p-4'}>
            {!compact && (
                <div className="flex justify-between gap-2">
                    <p className="font-medium">Order {order.order_number}</p>
                    <p className="font-bold text-orange-500">{formatPrice(order.total)}</p>
                </div>
            )}
            {compact && (
                <p className="text-sm font-semibold text-gray-900">Pay {formatPrice(order.total)} directly to seller</p>
            )}
            {!compact && isBank && (
                <p className="mt-1 text-xs text-gray-500">{bankTitle}</p>
            )}

            {method && accountNumber && (
                <DirectPaymentDetails
                    className={compact ? '' : 'mt-4'}
                    accountNumber={accountNumber}
                    accountName={method.account_name}
                    network={isBank ? null : method.network}
                    isBank={isBank}
                    bankName={method.bank_name || (isBank ? bankTitle : null)}
                    hint={
                        isBank
                            ? `Send ${formatPrice(order.total)} to the bank account above, then upload a screenshot or transaction ID below.${method.instructions ? ` ${method.instructions}` : ''}`
                            : `Send ${formatPrice(order.total)} to the number above. Leave the MoMo reference blank if you're paying by USSD/keypad — then upload a screenshot or SMS ID below.${method.instructions ? ` ${method.instructions}` : ''}`
                    }
                />
            )}

            {order.payment_status !== 'paid' && (
                <form onSubmit={submit} className={`space-y-4 ${compact ? '' : 'mt-4 border-t border-gray-100 pt-4'}`}>
                    {order.direct_payment_rejection_reason && !order.direct_payment_reference && !order.direct_payment_proof_path && (
                        <div className="rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-700">
                            <p className="font-medium">Seller rejected your payment claim</p>
                            <p className="mt-1">{order.direct_payment_rejection_reason}</p>
                            <p className="mt-1 text-xs">Submit a screenshot or transaction ID again.</p>
                        </div>
                    )}
                    <DocumentUploadField
                        id={`proof-${order.id}`}
                        label="Upload payment proof"
                        hint="Upload a screenshot of your MoMo or bank payment confirmation"
                        required={false}
                        accept="image/jpeg,image/png,image/webp,image/gif"
                        maxSizeMb={5}
                        value={data.proof}
                        onChange={(file) => setData('proof', file)}
                        existingUrl={existingProof}
                        error={errors.proof}
                    />
                    <div>
                        <Label htmlFor={`ref-${order.id}`}>Transaction ID (optional)</Label>
                        <Input
                            id={`ref-${order.id}`}
                            placeholder="From MoMo or bank SMS — skip if you upload a screenshot"
                            value={data.reference}
                            onChange={(e) => setData('reference', e.target.value)}
                            className="mt-1"
                        />
                        {errors.reference && <p className="mt-1 text-xs text-red-600">{errors.reference}</p>}
                    </div>
                    <Button
                        type="submit"
                        disabled={processing}
                        className="w-full bg-green-600 py-6 text-base font-semibold text-white hover:bg-green-700"
                    >
                        {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                        {order.direct_payment_reference || order.direct_payment_proof_path ? 'Update payment details' : "I've paid"}
                    </Button>
                    {(order.direct_payment_reference || order.direct_payment_proof_path) && (
                        <p className="text-xs text-amber-700">Waiting for the seller to confirm your payment.</p>
                    )}
                </form>
            )}
            {order.payment_status === 'paid' && <p className="text-sm text-green-600">Payment confirmed</p>}
        </div>
    );
}
