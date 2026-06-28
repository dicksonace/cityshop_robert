import { Head, useForm, usePage } from '@inertiajs/react';
import { Check, Copy, LoaderCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { Paginated } from '@/types/marketplace';
import { SharedData } from '@/types';

interface SellerInvite {
    id: number;
    email: string | null;
    name: string | null;
    notes: string | null;
    status: string;
    expires_at: string | null;
    used_at: string | null;
    created_at: string | null;
    registration_url: string | null;
    creator: { name: string } | null;
    seller: { name: string; email: string } | null;
}

interface SellerInvitesIndexProps {
    invites: Paginated<SellerInvite>;
    expiryHours: number;
}


function CopyLinkButton({ url }: { url: string }) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        await navigator.clipboard.writeText(url);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <Button type="button" variant="outline" size="sm" onClick={copy}>
            {copied ? <Check className="mr-1 h-3.5 w-3.5" /> : <Copy className="mr-1 h-3.5 w-3.5" />}
            {copied ? 'Copied' : 'Copy link'}
        </Button>
    );
}

export default function SellerInvitesIndex({ invites, expiryHours }: SellerInvitesIndexProps) {
    const { flash } = usePage<SharedData>().props;
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        name: '',
        notes: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.seller-invites.store'), {
            onSuccess: () => reset(),
        });
    };

    const statusBadge = (status: string) => {
        const colors: Record<string, string> = {
            pending: 'bg-yellow-100 text-yellow-800',
            used: 'bg-green-100 text-green-800',
            expired: 'bg-gray-100 text-gray-700',
            cancelled: 'bg-red-100 text-red-800',
        };

        return (
            <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${colors[status] ?? 'bg-gray-100'}`}>
                {status}
            </span>
        );
    };

    return (
        <AdminLayout title="Seller Registration Invites" active="invites">
            <Head title="Seller Invites" />

            {flash.success && (
                <div className="mb-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {flash.success}
                </div>
            )}

            {flash.sellerInviteUrl && (
                <div className="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-4">
                    <p className="text-sm font-medium text-blue-900">Share this registration link with the applicant:</p>
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        <code className="flex-1 break-all rounded bg-white px-3 py-2 text-xs text-gray-800">
                            {flash.sellerInviteUrl}
                        </code>
                        <CopyLinkButton url={flash.sellerInviteUrl} />
                    </div>
                    <p className="mt-2 text-xs text-blue-700">
                        Valid for {expiryHours} hours. Can only be submitted once.
                    </p>
                </div>
            )}

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="rounded-xl bg-white p-6 shadow-sm lg:col-span-1">
                    <h2 className="font-semibold text-gray-900">Create invite link</h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Generate a private seller registration link to share after a support inquiry.
                    </p>
                    <form className="mt-4 space-y-4" onSubmit={submit}>
                        <div>
                            <Label>Email (optional)</Label>
                            <Input
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                placeholder="buyer@example.com"
                                className="mt-1"
                            />
                            <InputError message={errors.email} />
                        </div>
                        <div>
                            <Label>Name (optional)</Label>
                            <Input
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="Applicant name"
                                className="mt-1"
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div>
                            <Label>Notes (optional)</Label>
                            <Input
                                value={data.notes}
                                onChange={(e) => setData('notes', e.target.value)}
                                placeholder="Internal note"
                                className="mt-1"
                            />
                            <InputError message={errors.notes} />
                        </div>
                        <Button type="submit" disabled={processing} className="w-full bg-orange-500 hover:bg-orange-600">
                            {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                            Generate link
                        </Button>
                    </form>
                </div>

                <div className="rounded-xl bg-white p-6 shadow-sm lg:col-span-2">
                    <h2 className="font-semibold text-gray-900">Recent invites</h2>
                    <div className="mt-4 overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b text-gray-500">
                                    <th className="px-2 py-2">Applicant</th>
                                    <th className="px-2 py-2">Status</th>
                                    <th className="px-2 py-2">Expires</th>
                                    <th className="px-2 py-2">Link</th>
                                </tr>
                            </thead>
                            <tbody>
                                {invites.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="px-2 py-8 text-center text-gray-500">
                                            No invites yet.
                                        </td>
                                    </tr>
                                ) : (
                                    invites.data.map((invite) => (
                                        <tr key={invite.id} className="border-b border-gray-50">
                                            <td className="px-2 py-3">
                                                <p className="font-medium">{invite.name ?? invite.seller?.name ?? '—'}</p>
                                                <p className="text-xs text-gray-500">{invite.email ?? invite.seller?.email ?? '—'}</p>
                                            </td>
                                            <td className="px-2 py-3">{statusBadge(invite.status)}</td>
                                            <td className="px-2 py-3 text-xs text-gray-500">
                                                {invite.expires_at ? new Date(invite.expires_at).toLocaleString() : '—'}
                                            </td>
                                            <td className="px-2 py-3">
                                                {invite.registration_url ? (
                                                    <CopyLinkButton url={invite.registration_url} />
                                                ) : (
                                                    <span className="text-xs text-gray-400">—</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
