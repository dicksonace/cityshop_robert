import { QRCodeSVG } from 'qrcode.react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

import HeadingSmall from '@/components/heading-small';
import OtpCodeInput from '@/components/otp-code-input';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { PasswordInput } from '@/components/ui/password-input';
import AdminLayout from '@/layouts/admin-layout';
import AppLayout from '@/layouts/app-layout';
import SellerLayout from '@/layouts/seller-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { SharedData, type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Security', href: '/settings/security' }];

type MfaStatus = {
    email: string | null;
    email_hint: string | null;
    has_email: boolean;
    mobile?: string | null;
    mobile_hint?: string | null;
    has_mobile?: boolean;
    code_channel?: 'sms' | 'email' | 'both';
    email_enabled: boolean;
    totp_enabled: boolean;
};

type Setup = { secret: string; otpauth_url: string } | null;

export default function Security({ mfa, setup, status }: { mfa: MfaStatus; setup?: Setup; status?: string }) {
    const role = usePage<SharedData>().props.auth.user?.role;
    const seller = role === 'seller';
    const admin = role === 'admin';
    const [tab, setTab] = useState<'email' | 'totp'>(seller || admin ? 'totp' : 'email');
    const notice = status;
    const channel = mfa.code_channel ?? 'sms';
    const codeLabel = channel === 'email' ? 'Email / Gmail' : channel === 'both' ? 'SMS or email' : 'SMS';

    const panel = (
        <>
            <Head title="Google Authenticator" />
            <div className="space-y-6">
                <HeadingSmall title="Two-factor sign-in" description="Turn on SMS codes, Google Authenticator, or both. You choose." />
                {notice ? <p className="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{notice}</p> : null}
                <div className="flex gap-2">
                    <button
                        type="button"
                        className={`rounded-full px-4 py-1.5 text-sm font-semibold ${tab === 'email' ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-700'}`}
                        onClick={() => setTab('email')}
                    >
                        {codeLabel}
                    </button>
                    <button
                        type="button"
                        className={`rounded-full px-4 py-1.5 text-sm font-semibold ${tab === 'totp' ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-700'}`}
                        onClick={() => setTab('totp')}
                    >
                        Google Authenticator
                    </button>
                </div>
                {tab === 'email' ? <EmailTab mfa={mfa} /> : <TotpTab mfa={mfa} setup={setup ?? null} />}
            </div>
        </>
    );

    if (seller) {
        return (
            <SellerLayout title="Google Authenticator" active="account">
                {panel}
            </SellerLayout>
        );
    }

    if (admin) {
        return (
            <AdminLayout title="Google Authenticator" active="dashboard">
                {panel}
            </AdminLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <SettingsLayout>{panel}</SettingsLayout>
        </AppLayout>
    );
}

function EmailTab({ mfa }: { mfa: MfaStatus }) {
    const send = useForm({ password: '' });
    const confirm = useForm({ code: '' });
    const disable = useForm({ password: '' });
    const channel = mfa.code_channel ?? 'sms';
    const viaSms = channel !== 'email';
    const ready = channel === 'both' ? Boolean(mfa.has_mobile || mfa.has_email) : viaSms ? Boolean(mfa.has_mobile) : Boolean(mfa.has_email);
    const where = channel === 'both' ? `${mfa.mobile || 'your phone'} and ${mfa.email || 'your email'}` : viaSms ? mfa.mobile || 'your phone' : mfa.email || 'your email';
    const sendLabel = channel === 'both' ? 'Send me a code' : viaSms ? 'Text me a code' : 'Email me a code';
    const onLabel = channel === 'email' ? 'Turn on email codes' : channel === 'both' ? 'Turn on codes' : 'Turn on SMS codes';
    const offLabel = channel === 'email' ? 'Turn off email codes' : channel === 'both' ? 'Turn off codes' : 'Turn off SMS codes';
    const codeFrom = channel === 'email' ? 'Code from the email' : channel === 'both' ? 'Code from the message' : 'Code from the text';

    const submitSend: FormEventHandler = (e) => {
        e.preventDefault();
        send.post(route('security.email'), { onSuccess: () => send.reset('password') });
    };
    const submitConfirm: FormEventHandler = (e) => {
        e.preventDefault();
        confirm.post(route('security.email.confirm'), { onSuccess: () => confirm.reset('code') });
    };
    const submitDisable: FormEventHandler = (e) => {
        e.preventDefault();
        disable.delete(route('security.email.disable'), { onSuccess: () => disable.reset('password') });
    };

    if (!ready) {
        return (
            <p className="text-sm text-gray-600">
                {viaSms ? 'Add a phone number on your profile first. Codes are sent by SMS.' : 'Add an email on your profile first.'}
            </p>
        );
    }

    if (mfa.email_enabled) {
        return (
            <form onSubmit={submitDisable} className="space-y-3">
                <p className="text-sm text-gray-600">Sign-in codes are on. They go to {where}.</p>
                <div>
                    <Label>Password to turn this off</Label>
                    <PasswordInput className="mt-1" value={disable.data.password} onChange={(e) => disable.setData('password', e.target.value)} />
                    <InputError message={disable.errors.password} />
                </div>
                <Button type="submit" variant="outline" disabled={disable.processing}>
                    {offLabel}
                </Button>
            </form>
        );
    }

    return (
        <div className="space-y-6">
            <form onSubmit={submitSend} className="space-y-3">
                <p className="text-sm text-gray-600">We send a 6-digit code to {where}.</p>
                <div>
                    <Label>Current password</Label>
                    <PasswordInput className="mt-1" value={send.data.password} onChange={(e) => send.setData('password', e.target.value)} />
                    <InputError message={send.errors.password} />
                </div>
                <Button type="submit" className="bg-orange-500 hover:bg-orange-600" disabled={send.processing}>
                    {sendLabel}
                </Button>
            </form>
            <form onSubmit={submitConfirm} className="space-y-3">
                <div>
                    <Label htmlFor="email-code">{codeFrom}</Label>
                    <OtpCodeInput id="email-code" value={confirm.data.code} onChange={(code) => confirm.setData('code', code)} disabled={confirm.processing} />
                    <InputError message={confirm.errors.code} />
                </div>
                <Button type="submit" disabled={confirm.processing}>
                    {onLabel}
                </Button>
            </form>
        </div>
    );
}

function TotpTab({ mfa, setup }: { mfa: MfaStatus; setup: Setup }) {
    const start = useForm({ password: '' });
    const confirm = useForm({ code: '' });
    const disable = useForm({ password: '' });

    const submitStart: FormEventHandler = (e) => {
        e.preventDefault();
        start.post(route('security.totp'), { onSuccess: () => start.reset('password') });
    };
    const submitConfirm: FormEventHandler = (e) => {
        e.preventDefault();
        confirm.post(route('security.totp.confirm'), { onSuccess: () => confirm.reset('code') });
    };
    const submitDisable: FormEventHandler = (e) => {
        e.preventDefault();
        disable.delete(route('security.totp.disable'), { onSuccess: () => disable.reset('password') });
    };

    if (mfa.totp_enabled) {
        return (
            <form onSubmit={submitDisable} className="space-y-3">
                <p className="text-sm text-gray-600">Authenticator is on. Sign-in asks for the 6-digit code from the app you scanned.</p>
                <div>
                    <Label>Password to turn this off</Label>
                    <PasswordInput className="mt-1" value={disable.data.password} onChange={(e) => disable.setData('password', e.target.value)} />
                    <InputError message={disable.errors.password} />
                </div>
                <Button type="submit" variant="outline" disabled={disable.processing}>
                    Turn off authenticator
                </Button>
            </form>
        );
    }

    return (
        <div className="space-y-6">
            <form onSubmit={submitStart} className="space-y-3">
                <p className="text-sm text-gray-600">Scan the QR code with Google Authenticator, Authy, or a similar app. You can also type the setup code.</p>
                <div>
                    <Label>Current password</Label>
                    <PasswordInput className="mt-1" value={start.data.password} onChange={(e) => start.setData('password', e.target.value)} />
                    <InputError message={start.errors.password} />
                </div>
                <Button type="submit" className="bg-orange-500 hover:bg-orange-600" disabled={start.processing}>
                    Show QR code
                </Button>
            </form>
            {setup ? (
                <form onSubmit={submitConfirm} className="space-y-3">
                    <div className="flex justify-center rounded-2xl border border-gray-100 bg-white p-4">
                        <QRCodeSVG value={setup.otpauth_url} size={180} />
                    </div>
                    <p className="break-all text-center text-xs text-gray-500">Setup code: {setup.secret}</p>
                    <div>
                        <Label htmlFor="totp-code">Code from the authenticator app</Label>
                        <OtpCodeInput id="totp-code" value={confirm.data.code} onChange={(code) => confirm.setData('code', code)} disabled={confirm.processing} />
                        <InputError message={confirm.errors.code} />
                    </div>
                    <Button type="submit" disabled={confirm.processing}>
                        Turn on authenticator
                    </Button>
                </form>
            ) : null}
        </div>
    );
}
