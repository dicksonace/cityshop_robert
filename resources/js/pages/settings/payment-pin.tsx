import InputError from '@/components/input-error';
import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Payment PIN',
        href: '/settings/payment-pin',
    },
];

type PageProps = {
    hasPaymentPin: boolean;
    hasEmail?: boolean;
    hasMobile?: boolean;
    status?: string;
    emailHint?: string;
    hint?: string;
    via?: 'email' | 'sms';
};

export default function PaymentPin({
    hasPaymentPin,
    hasEmail = true,
    hasMobile = false,
    status,
    emailHint,
    hint,
    via,
}: PageProps) {
    const [mode, setMode] = useState<'manage' | 'reset'>('manage');
    const destinationHint = hint || emailHint;
    const sentVia = via === 'sms' ? 'SMS' : 'email';

    const setForm = useForm({
        pin: '',
        pin_confirmation: '',
    });

    const changeForm = useForm({
        current_pin: '',
        pin: '',
        pin_confirmation: '',
    });

    const resetForm = useForm({
        code: '',
        pin: '',
        pin_confirmation: '',
    });

    const forgotForm = useForm({
        via: 'email',
    });

    const submitSet: FormEventHandler = (e) => {
        e.preventDefault();
        setForm.post(route('payment-pin.store'), {
            preserveScroll: true,
            onSuccess: () => setForm.reset(),
        });
    };

    const submitChange: FormEventHandler = (e) => {
        e.preventDefault();
        changeForm.put(route('payment-pin.update'), {
            preserveScroll: true,
            onSuccess: () => changeForm.reset(),
        });
    };

    const sendForgot = (channel: 'email' | 'sms') => {
        forgotForm
            .transform(() => ({ via: channel }))
            .post(route('payment-pin.forgot'), {
                preserveScroll: true,
                onSuccess: () => setMode('reset'),
            });
    };

    const submitReset: FormEventHandler = (e) => {
        e.preventDefault();
        resetForm.post(route('payment-pin.reset'), {
            preserveScroll: true,
            onSuccess: () => {
                resetForm.reset();
                setMode('manage');
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payment PIN" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Payment PIN"
                        description="A 4-digit PIN protects wallet checkout, MoMo withdrawals, and chat transfers"
                    />

                    {status && (
                        <p className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                            {status}
                        </p>
                    )}

                    {mode === 'manage' && !hasPaymentPin && (
                        <form onSubmit={submitSet} className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="pin">4-digit PIN</Label>
                                <Input
                                    id="pin"
                                    type="password"
                                    inputMode="numeric"
                                    maxLength={4}
                                    value={setForm.data.pin}
                                    onChange={(e) => setForm.setData('pin', e.target.value.replace(/\D/g, '').slice(0, 4))}
                                    autoComplete="off"
                                />
                                <InputError message={setForm.errors.pin} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="pin_confirmation">Confirm PIN</Label>
                                <Input
                                    id="pin_confirmation"
                                    type="password"
                                    inputMode="numeric"
                                    maxLength={4}
                                    value={setForm.data.pin_confirmation}
                                    onChange={(e) =>
                                        setForm.setData('pin_confirmation', e.target.value.replace(/\D/g, '').slice(0, 4))
                                    }
                                    autoComplete="off"
                                />
                                <InputError message={setForm.errors.pin_confirmation} />
                            </div>
                            <Button disabled={setForm.processing}>Set payment PIN</Button>
                        </form>
                    )}

                    {mode === 'manage' && hasPaymentPin && (
                        <>
                            <form onSubmit={submitChange} className="space-y-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="current_pin">Current PIN</Label>
                                    <Input
                                        id="current_pin"
                                        type="password"
                                        inputMode="numeric"
                                        maxLength={4}
                                        value={changeForm.data.current_pin}
                                        onChange={(e) =>
                                            changeForm.setData('current_pin', e.target.value.replace(/\D/g, '').slice(0, 4))
                                        }
                                        autoComplete="off"
                                    />
                                    <InputError message={changeForm.errors.current_pin} />
                                    <InputError message={changeForm.errors.payment_pin} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="new_pin">New PIN</Label>
                                    <Input
                                        id="new_pin"
                                        type="password"
                                        inputMode="numeric"
                                        maxLength={4}
                                        value={changeForm.data.pin}
                                        onChange={(e) => changeForm.setData('pin', e.target.value.replace(/\D/g, '').slice(0, 4))}
                                        autoComplete="off"
                                    />
                                    <InputError message={changeForm.errors.pin} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="new_pin_confirmation">Confirm new PIN</Label>
                                    <Input
                                        id="new_pin_confirmation"
                                        type="password"
                                        inputMode="numeric"
                                        maxLength={4}
                                        value={changeForm.data.pin_confirmation}
                                        onChange={(e) =>
                                            changeForm.setData(
                                                'pin_confirmation',
                                                e.target.value.replace(/\D/g, '').slice(0, 4),
                                            )
                                        }
                                        autoComplete="off"
                                    />
                                    <InputError message={changeForm.errors.pin_confirmation} />
                                </div>
                                <div className="flex items-center gap-4">
                                    <Button disabled={changeForm.processing}>Update PIN</Button>
                                    <Transition
                                        show={changeForm.recentlySuccessful}
                                        enter="transition ease-in-out"
                                        enterFrom="opacity-0"
                                        leave="transition ease-in-out"
                                        leaveTo="opacity-0"
                                    >
                                        <p className="text-sm text-neutral-600">Saved</p>
                                    </Transition>
                                </div>
                            </form>

                            <div className="space-y-3 border-t pt-4">
                                <p className="text-sm font-medium text-gray-800">Forgot PIN? Send a reset code via</p>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={forgotForm.processing || !hasEmail}
                                        onClick={() => sendForgot('email')}
                                    >
                                        Email
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={forgotForm.processing || !hasMobile}
                                        onClick={() => sendForgot('sms')}
                                    >
                                        SMS
                                    </Button>
                                </div>
                                {!hasMobile && (
                                    <p className="text-xs text-muted-foreground">Add a mobile number to your profile to use SMS.</p>
                                )}
                                <InputError message={forgotForm.errors.via || forgotForm.errors.email} className="mt-2" />
                            </div>
                        </>
                    )}

                    {mode === 'reset' && (
                        <form onSubmit={submitReset} className="space-y-4">
                            <p className="text-sm text-muted-foreground">
                                {destinationHint
                                    ? `Enter the 6-digit code sent to ${destinationHint} via ${sentVia}, then choose a new PIN.`
                                    : `Enter the 6-digit ${sentVia} code, then choose a new PIN.`}
                            </p>
                            <div className="grid gap-2">
                                <Label htmlFor="code">Reset code</Label>
                                <Input
                                    id="code"
                                    inputMode="numeric"
                                    maxLength={6}
                                    value={resetForm.data.code}
                                    onChange={(e) => resetForm.setData('code', e.target.value.replace(/\D/g, '').slice(0, 6))}
                                    autoComplete="off"
                                />
                                <InputError message={resetForm.errors.code} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="reset_pin">New PIN</Label>
                                <Input
                                    id="reset_pin"
                                    type="password"
                                    inputMode="numeric"
                                    maxLength={4}
                                    value={resetForm.data.pin}
                                    onChange={(e) => resetForm.setData('pin', e.target.value.replace(/\D/g, '').slice(0, 4))}
                                    autoComplete="off"
                                />
                                <InputError message={resetForm.errors.pin} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="reset_pin_confirmation">Confirm new PIN</Label>
                                <Input
                                    id="reset_pin_confirmation"
                                    type="password"
                                    inputMode="numeric"
                                    maxLength={4}
                                    value={resetForm.data.pin_confirmation}
                                    onChange={(e) =>
                                        resetForm.setData('pin_confirmation', e.target.value.replace(/\D/g, '').slice(0, 4))
                                    }
                                    autoComplete="off"
                                />
                                <InputError message={resetForm.errors.pin_confirmation} />
                            </div>
                            <div className="flex flex-wrap gap-3">
                                <Button disabled={resetForm.processing}>Reset PIN</Button>
                                <Button type="button" variant="ghost" onClick={() => setMode('manage')}>
                                    Back
                                </Button>
                            </div>
                        </form>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
