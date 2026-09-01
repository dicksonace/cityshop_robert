import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

import DocumentUploadField from '@/components/forms/document-upload-field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type KycPayload = {
    id?: number | null;
    status: string;
    status_label: string;
    can_store_funds: boolean;
    can_submit: boolean;
    ghana_card_number?: string | null;
    full_name?: string | null;
    admin_notes?: string | null;
    submitted_at?: string | null;
    reviewed_at?: string | null;
    front_url?: string | null;
    back_url?: string | null;
    selfie_url?: string | null;
};

type Props = {
    kyc: KycPayload;
    submitRoute?: string;
};

export default function KycVerificationForm({ kyc, submitRoute = route('kyc.store') }: Props) {
    const isApproved = kyc.status === 'approved';
    const isPending = kyc.status === 'pending';
    const needsResubmit = ['rejected', 'needs_improvement', 'unverified'].includes(kyc.status);

    const form = useForm<{
        ghana_card_number: string;
        full_name: string;
        front: File | null;
        back: File | null;
        selfie: File | null;
    }>({
        ghana_card_number: kyc.ghana_card_number ?? '',
        full_name: kyc.full_name ?? '',
        front: null,
        back: null,
        selfie: null,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (!kyc.can_submit) return;
        form.post(submitRoute, { forceFormData: true, preserveScroll: true });
    };

    return (
        <div className="space-y-4">
            <div className="rounded-xl border border-gray-100 bg-gray-50 p-4">
                <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">Status</p>
                <p className="mt-1 text-lg font-bold text-gray-900">{kyc.status_label}</p>
                {isApproved && (
                    <p className="mt-2 text-sm text-emerald-700">
                        Your Ghana Card is verified. You can use the CityShop wallet, withdraw, and convert.
                    </p>
                )}
                {isPending && (
                    <p className="mt-2 text-sm text-amber-800">
                        The system is reviewing your Ghana Card. You can still buy items with Paystack.
                    </p>
                )}
                {kyc.admin_notes && (
                    <p className="mt-2 rounded-lg border border-amber-100 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                        {kyc.admin_notes}
                    </p>
                )}
                {!isApproved && (
                    <p className="mt-2 text-sm text-gray-600">
                        The system must approve your Ghana Card before you can transact with the CityShop wallet.
                    </p>
                )}
            </div>

            {kyc.can_submit && (
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <Label htmlFor="ghana_card_number">Ghana Card number</Label>
                        <Input
                            id="ghana_card_number"
                            value={form.data.ghana_card_number}
                            onChange={(e) => form.setData('ghana_card_number', e.target.value)}
                            placeholder="GHA-123456789-1"
                            className="mt-1"
                            required
                        />
                        <InputError message={form.errors.ghana_card_number} />
                    </div>

                    <div>
                        <Label htmlFor="full_name">Full name on card (optional)</Label>
                        <Input
                            id="full_name"
                            value={form.data.full_name}
                            onChange={(e) => form.setData('full_name', e.target.value)}
                            className="mt-1"
                        />
                        <InputError message={form.errors.full_name} />
                    </div>

                    <DocumentUploadField
                        id="kyc-front"
                        label="Front of Ghana Card"
                        accept="image/jpeg,image/png,image/webp"
                        value={form.data.front}
                        onChange={(file) => form.setData('front', file)}
                        existingUrl={kyc.front_url}
                        required={needsResubmit && !kyc.front_url}
                        error={form.errors.front}
                    />

                    <DocumentUploadField
                        id="kyc-back"
                        label="Back of Ghana Card"
                        accept="image/jpeg,image/png,image/webp"
                        value={form.data.back}
                        onChange={(file) => form.setData('back', file)}
                        existingUrl={kyc.back_url}
                        required={needsResubmit && !kyc.back_url}
                        error={form.errors.back}
                    />

                    <DocumentUploadField
                        id="kyc-selfie"
                        label="Selfie with Ghana Card (optional)"
                        accept="image/jpeg,image/png,image/webp"
                        value={form.data.selfie}
                        onChange={(file) => form.setData('selfie', file)}
                        existingUrl={kyc.selfie_url}
                        required={false}
                        error={form.errors.selfie}
                    />

                    <Button type="submit" disabled={form.processing} className="w-full bg-orange-500 hover:bg-orange-600">
                        {form.processing ? 'Submitting…' : isPending ? 'Update Ghana Card' : 'Submit Ghana Card'}
                    </Button>
                </form>
            )}
        </div>
    );
}
