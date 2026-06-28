import { Head, router } from '@inertiajs/react';
import { AlertTriangle, Copy, ExternalLink, LogOut, ShieldCheck, Store, User } from 'lucide-react';
import { useState } from 'react';

import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import ShopLayout from '@/layouts/shop-layout';

type GateReason = 'admin_signed_in' | 'wrong_account' | 'already_approved' | 'suspended';

interface SellerInviteGateProps {
    token: string;
    reason: GateReason;
    inviteUrl: string;
    inviteEmail?: string | null;
    inviteName?: string | null;
    expiresAt: string;
    currentUser: {
        name: string;
        email: string;
        role: string;
    };
}

const reasonCopy: Record<
    GateReason,
    { title: string; description: string; canLogoutAndContinue: boolean }
> = {
    admin_signed_in: {
        title: 'You are signed in as admin',
        description:
            'This private link is for a new seller account. To test registration, sign out first or open the link in a private/incognito window so your admin session stays separate.',
        canLogoutAndContinue: true,
    },
    wrong_account: {
        title: 'This link is for a different account',
        description:
            'You are signed in with a different email than the one this invitation was issued for. Sign out to register with the correct account, or send this link to the invited seller.',
        canLogoutAndContinue: true,
    },
    already_approved: {
        title: 'You already have an approved seller account',
        description: 'This registration link is not needed for your account. Go to Seller Centre to manage your store.',
        canLogoutAndContinue: false,
    },
    suspended: {
        title: 'Seller account suspended',
        description: 'Your seller account is suspended. Please contact support before using a new registration link.',
        canLogoutAndContinue: false,
    },
};

export default function SellerInviteGate({
    reason,
    inviteUrl,
    inviteEmail,
    inviteName,
    expiresAt,
    currentUser,
}: SellerInviteGateProps) {
    const [copied, setCopied] = useState(false);
    const copy = reasonCopy[reason];
    const registerPath = new URL(inviteUrl).pathname;

    const logoutAndContinue = () => {
        router.post(`/logout?redirect=${encodeURIComponent(registerPath)}`);
    };

    const copyLink = async () => {
        try {
            await navigator.clipboard.writeText(inviteUrl);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2500);
        } catch {
            window.prompt('Copy this link for the seller:', inviteUrl);
        }
    };

    const dashboardHref =
        currentUser.role === 'admin'
            ? route('admin.dashboard')
            : currentUser.role === 'seller'
              ? route('seller.dashboard')
              : route('home');

    return (
        <ShopLayout>
            <Head title="Seller invitation" />
            <div className="mx-auto max-w-lg px-4 py-12">
                <div className="rounded-2xl border border-amber-200 bg-white p-8 shadow-sm">
                    <div className="flex items-start gap-3">
                        <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-amber-100">
                            <AlertTriangle className="h-5 w-5 text-amber-600" />
                        </div>
                        <div>
                            <h1 className="text-xl font-bold text-gray-900">{copy.title}</h1>
                            <p className="mt-2 text-sm text-gray-600">{copy.description}</p>
                        </div>
                    </div>

                    <div className="mt-6 space-y-3 rounded-xl bg-gray-50 p-4 text-sm">
                        <p className="flex items-center gap-2 font-medium text-gray-800">
                            <User className="h-4 w-4 text-gray-400" />
                            Signed in as {currentUser.name} ({currentUser.email})
                        </p>
                        {(inviteEmail || inviteName) && (
                            <p className="text-gray-600">
                                Invitation for:{' '}
                                <span className="font-medium text-gray-800">
                                    {inviteName ? `${inviteName} ` : ''}
                                    {inviteEmail ?? 'new seller'}
                                </span>
                            </p>
                        )}
                        <p className="text-xs text-gray-500">Link expires {new Date(expiresAt).toLocaleString()}.</p>
                    </div>

                    <div className="mt-6 space-y-3">
                        {copy.canLogoutAndContinue && (
                            <Button
                                type="button"
                                onClick={logoutAndContinue}
                                className="w-full bg-orange-500 hover:bg-orange-600"
                            >
                                <LogOut className="mr-2 h-4 w-4" />
                                Sign out and continue registration
                            </Button>
                        )}

                        <Button type="button" variant="outline" onClick={copyLink} className="w-full">
                            <Copy className="mr-2 h-4 w-4" />
                            {copied ? 'Link copied!' : 'Copy link for seller'}
                        </Button>

                        <a
                            href={inviteUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="flex w-full items-center justify-center gap-2 rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            <ExternalLink className="h-4 w-4" />
                            Open in new tab (use private window to test)
                        </a>

                        <TextLink href={dashboardHref} className="flex w-full justify-center text-sm text-gray-500">
                            {currentUser.role === 'admin' ? (
                                <>
                                    <ShieldCheck className="mr-1.5 h-4 w-4" />
                                    Back to admin dashboard
                                </>
                            ) : (
                                <>
                                    <Store className="mr-1.5 h-4 w-4" />
                                    Back to my account
                                </>
                            )}
                        </TextLink>
                    </div>
                </div>
            </div>
        </ShopLayout>
    );
}
