import { Head, useForm, usePage } from '@inertiajs/react';
import { Check, ChevronLeft, ChevronRight, LoaderCircle, MapPin, Store, User, FileCheck } from 'lucide-react';
import { FormEventHandler, ReactNode, useEffect, useState } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useToast } from '@/contexts/toast-context';
import ShopLayout from '@/layouts/shop-layout';
import { cn } from '@/lib/utils';
import { SharedData } from '@/types';

const fieldLabels: Record<string, string> = {
    first_name: 'First name',
    last_name: 'Last name',
    mobile: 'Mobile number',
    whatsapp: 'WhatsApp number',
    email: 'Email address',
    ghana_card_number: 'Ghana Card number',
    digital_address: 'Digital address',
    residential_address: 'Residential address',
    region: 'Region',
    city: 'City',
    password: 'Password',
    password_confirmation: 'Password confirmation',
    store_name: 'Store name',
    business_name: 'Business name',
    business_registration_number: 'Business registration number',
    business_address: 'Business address',
    id_card_front: 'Ghana Card (front)',
    id_card_back: 'Ghana Card (back)',
    shop_photo: 'Shop photo',
    form_a: 'Form A',
    form_b: 'Form B',
    business_certificate: 'Business certificate',
};

const STEPS = [
    { id: 0, title: 'Personal', icon: User, description: 'Your contact details' },
    { id: 1, title: 'Business', icon: Store, description: 'Store or company info' },
    { id: 2, title: 'Location', icon: MapPin, description: 'Where you operate' },
    { id: 3, title: 'KYC', icon: FileCheck, description: 'Identity & documents' },
    { id: 4, title: 'Review', icon: Check, description: 'Confirm & submit' },
] as const;

const fieldToStep: Record<string, number> = {
    first_name: 0,
    last_name: 0,
    mobile: 0,
    whatsapp: 0,
    email: 0,
    password: 0,
    password_confirmation: 0,
    is_business_registered: 1,
    business_name: 1,
    business_registration_number: 1,
    business_address: 1,
    tin: 1,
    store_name: 1,
    region: 2,
    city: 2,
    residential_address: 2,
    digital_address: 2,
    ghana_card_number: 3,
    id_card_front: 3,
    id_card_back: 3,
    shop_photo: 3,
    form_a: 3,
    form_b: 3,
    business_certificate: 3,
};

interface SellerDefaults {
    first_name: string;
    last_name: string;
    mobile: string;
    whatsapp: string;
    email: string;
    ghana_card_number: string;
    digital_address: string;
    residential_address: string;
    region: string;
    city: string;
    store_name?: string;
    business_name?: string;
    business_registration_number?: string;
    business_address?: string;
    tin?: string;
    is_business_registered?: boolean;
}

interface SellerRegisterProps {
    token: string;
    expiresAt: string;
    inviteEmail?: string | null;
    isExistingUser?: boolean;
    defaults?: SellerDefaults;
}

type FormData = {
    first_name: string;
    last_name: string;
    mobile: string;
    whatsapp: string;
    email: string;
    ghana_card_number: string;
    digital_address: string;
    residential_address: string;
    region: string;
    city: string;
    password: string;
    password_confirmation: string;
    is_business_registered: boolean;
    business_name: string;
    business_registration_number: string;
    business_address: string;
    tin: string;
    store_name: string;
    id_card_front: File | null;
    id_card_back: File | null;
    shop_photo: File | null;
    form_a: File | null;
    form_b: File | null;
    business_certificate: File | null;
};

function validateStep(step: number, data: FormData, isExistingUser: boolean): Record<string, string> {
    const errs: Record<string, string> = {};
    const req = (field: keyof FormData, label: string) => {
        const val = data[field];
        if (typeof val === 'string' && !val.trim()) {
            errs[field] = `${label} is required.`;
        }
    };

    if (step === 0) {
        req('first_name', 'First name');
        req('last_name', 'Last name');
        req('mobile', 'Mobile number');
        req('whatsapp', 'WhatsApp number');
        req('email', 'Email address');
        if (data.email.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email.trim())) {
            errs.email = 'Enter a valid email address.';
        }
        if (!isExistingUser) {
            if (!data.password) {
                errs.password = 'Password is required.';
            } else if (data.password.length < 8) {
                errs.password = 'Password must be at least 8 characters.';
            }
            if (!data.password_confirmation) {
                errs.password_confirmation = 'Please confirm your password.';
            } else if (data.password !== data.password_confirmation) {
                errs.password_confirmation = 'Passwords do not match.';
            }
        }
    }

    if (step === 1) {
        if (data.is_business_registered) {
            req('business_name', 'Business name');
            req('business_registration_number', 'Registration number');
            req('business_address', 'Business address');
        } else {
            req('store_name', 'Store name');
        }
    }

    if (step === 2) {
        req('region', 'Region');
        req('city', 'City');
        req('residential_address', 'Residential address');
        req('digital_address', 'Digital address');
    }

    if (step === 3) {
        req('ghana_card_number', 'Ghana Card number');
        if (!data.id_card_front) errs.id_card_front = 'Please upload the front of your Ghana Card.';
        if (!data.id_card_back) errs.id_card_back = 'Please upload the back of your Ghana Card.';
        if (!data.shop_photo) errs.shop_photo = 'Please upload a photo of your shop.';
        if (data.is_business_registered) {
            if (!data.form_a) errs.form_a = 'Please upload Form A.';
            if (!data.form_b) errs.form_b = 'Please upload Form B.';
            if (!data.business_certificate) errs.business_certificate = 'Please upload your business certificate.';
        }
    }

    return errs;
}

function fileLabel(file: File | null): string {
    return file ? file.name : 'Not uploaded';
}

export default function SellerRegister({ token, expiresAt, inviteEmail = null, isExistingUser = false, defaults }: SellerRegisterProps) {
    const { flash, errors: pageErrors } = usePage<SharedData & { errors?: Record<string, string> }>().props;
    const { error: toastError } = useToast();
    const [step, setStep] = useState(0);
    const [stepErrors, setStepErrors] = useState<Record<string, string>>({});
    const [direction, setDirection] = useState<'forward' | 'back'>('forward');

    const { data, setData, post, processing, errors: formErrors, reset } = useForm<FormData>({
        first_name: defaults?.first_name ?? '',
        last_name: defaults?.last_name ?? '',
        mobile: defaults?.mobile ?? '',
        whatsapp: defaults?.whatsapp ?? '',
        email: defaults?.email ?? '',
        ghana_card_number: defaults?.ghana_card_number ?? '',
        digital_address: defaults?.digital_address ?? '',
        residential_address: defaults?.residential_address ?? '',
        region: defaults?.region ?? '',
        city: defaults?.city ?? '',
        password: '',
        password_confirmation: '',
        is_business_registered: defaults?.is_business_registered ?? false,
        business_name: defaults?.business_name ?? '',
        business_registration_number: defaults?.business_registration_number ?? '',
        business_address: defaults?.business_address ?? '',
        tin: defaults?.tin ?? '',
        store_name: defaults?.store_name ?? '',
        id_card_front: null,
        id_card_back: null,
        shop_photo: null,
        form_a: null,
        form_b: null,
        business_certificate: null,
    });

    const serverErrors = { ...(pageErrors ?? {}), ...formErrors };
    const displayErrors = { ...stepErrors, ...serverErrors };

    const goToStep = (next: number, dir: 'forward' | 'back' = 'forward') => {
        setDirection(dir);
        setStep(next);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const validateAllSteps = (): Record<string, string> => {
        let all: Record<string, string> = {};
        for (let i = 0; i < STEPS.length - 1; i++) {
            all = { ...all, ...validateStep(i, data, isExistingUser) };
        }
        return all;
    };

    const handleNext = () => {
        const errs = validateStep(step, data, isExistingUser);
        if (Object.keys(errs).length > 0) {
            setStepErrors(errs);
            toastError('Please complete all required fields on this step.');
            return;
        }
        setStepErrors({});
        goToStep(Math.min(step + 1, STEPS.length - 1), 'forward');
    };

    const handlePrevious = () => {
        setStepErrors({});
        goToStep(Math.max(step - 1, 0), 'back');
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        const allErrs = validateAllSteps();
        if (Object.keys(allErrs).length > 0) {
            setStepErrors(allErrs);
            const firstField = Object.keys(allErrs)[0];
            const targetStep = fieldToStep[firstField] ?? 0;
            goToStep(targetStep, 'forward');
            toastError('Please complete all required fields before submitting.');
            return;
        }

        post(route('register.seller', { token }), {
            forceFormData: true,
            onFinish: () => reset('password', 'password_confirmation'),
            onError: (errs) => {
                const firstField = Object.keys(errs)[0];
                if (firstField && fieldToStep[firstField] !== undefined) {
                    goToStep(fieldToStep[firstField], 'forward');
                }
                window.scrollTo({ top: 0, behavior: 'smooth' });
                const first = Object.values(errs)[0];
                if (typeof first === 'string') {
                    toastError(first);
                } else {
                    toastError('Please complete all required fields and try again.');
                }
            },
        });
    };

    useEffect(() => {
        if (inviteEmail && data.email !== inviteEmail) {
            setData('email', inviteEmail);
        }
    }, [inviteEmail, data.email, setData]);

    useEffect(() => {
        if (flash.error) {
            toastError(flash.error);
        }
    }, [flash.error, toastError]);

    useEffect(() => {
        const errs = { ...(pageErrors ?? {}), ...formErrors };
        if (Object.keys(errs).length > 0) {
            const firstField = Object.keys(errs)[0];
            if (firstField && fieldToStep[firstField] !== undefined) {
                setStep(fieldToStep[firstField]);
            }
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }, [pageErrors, formErrors]);

    const fileInput = (field: keyof FormData, label: string, required = true) => (
        <div>
            <Label>{label}</Label>
            <Input
                type="file"
                accept="image/*,.pdf"
                className="mt-1"
                onChange={(e) => {
                    setData(field, e.target.files?.[0] ?? null);
                    setStepErrors((prev) => {
                        const next = { ...prev };
                        delete next[field];
                        return next;
                    });
                }}
            />
            {data[field] instanceof File && (
                <p className="mt-1 text-xs text-emerald-600">Selected: {(data[field] as File).name}</p>
            )}
            <InputError message={displayErrors[field]} />
        </div>
    );

    const progress = ((step + 1) / STEPS.length) * 100;

    const SummaryRow = ({ label, value }: { label: string; value: string }) => (
        <div className="flex justify-between gap-4 border-b border-gray-50 py-2 text-sm last:border-0">
            <span className="text-gray-500">{label}</span>
            <span className="text-right font-medium text-gray-900">{value || '—'}</span>
        </div>
    );

    const SummarySection = ({ title, stepIndex, children }: { title: string; stepIndex: number; children: ReactNode }) => (
        <div className="rounded-xl border border-gray-100 bg-gray-50/50 p-4">
            <div className="mb-3 flex items-center justify-between">
                <h3 className="font-semibold text-gray-900">{title}</h3>
                <button
                    type="button"
                    onClick={() => goToStep(stepIndex, 'back')}
                    className="text-xs font-medium text-orange-600 hover:text-orange-700"
                >
                    Edit
                </button>
            </div>
            {children}
        </div>
    );

    return (
        <ShopLayout>
            <Head title="Become a Seller" />

            {(flash.error || Object.keys(serverErrors).length > 0) && (
                <div className="sticky top-0 z-50 border-b border-red-300 bg-red-600 px-4 py-3 text-white shadow-md">
                    <p className="mx-auto max-w-2xl text-sm font-semibold">
                        {flash.error ?? 'Some required fields are missing or invalid. Please check the form below.'}
                    </p>
                    {Object.keys(serverErrors).length > 0 && (
                        <ul className="mx-auto mt-1 max-w-2xl list-inside list-disc text-xs text-red-100">
                            {Object.entries(serverErrors)
                                .slice(0, 6)
                                .map(([field, message]) => (
                                    <li key={field}>
                                        {fieldLabels[field] ?? field}: {message}
                                    </li>
                                ))}
                        </ul>
                    )}
                </div>
            )}

            <div className="mx-auto max-w-2xl px-4 py-8 sm:py-12">
                <div className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm sm:p-8">
                    <h1 className="text-2xl font-bold text-gray-900">Seller Application</h1>
                    <p className="mt-1 text-sm text-gray-500">
                        {isExistingUser
                            ? 'Complete your seller application using your existing account.'
                            : 'Complete your application using your private invitation link.'}
                    </p>
                    <p className="mt-2 text-xs text-amber-700">
                        Link expires: {new Date(expiresAt).toLocaleString()}.
                    </p>

                    {/* Progress bar */}
                    <div className="mt-6">
                        <div className="mb-2 flex items-center justify-between text-xs font-medium text-gray-500">
                            <span>
                                Step {step + 1} of {STEPS.length}
                            </span>
                            <span>{STEPS[step].title}</span>
                        </div>
                        <div className="h-2 overflow-hidden rounded-full bg-gray-100">
                            <div
                                className="h-full rounded-full bg-orange-500 transition-all duration-500 ease-out"
                                style={{ width: `${progress}%` }}
                            />
                        </div>
                    </div>

                    {/* Step indicators */}
                    <div className="mt-4 hidden gap-1 sm:flex">
                        {STEPS.map((s, i) => {
                            const Icon = s.icon;
                            const isActive = i === step;
                            const isDone = i < step;
                            return (
                                <button
                                    key={s.id}
                                    type="button"
                                    onClick={() => i < step && goToStep(i, 'back')}
                                    disabled={i > step}
                                    className={cn(
                                        'flex flex-1 flex-col items-center gap-1 rounded-lg px-2 py-2 text-center transition',
                                        isActive && 'bg-orange-50',
                                        isDone && 'cursor-pointer hover:bg-gray-50',
                                        i > step && 'opacity-40',
                                    )}
                                >
                                    <div
                                        className={cn(
                                            'flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold',
                                            isDone && 'bg-emerald-500 text-white',
                                            isActive && !isDone && 'bg-orange-500 text-white',
                                            !isActive && !isDone && 'bg-gray-200 text-gray-500',
                                        )}
                                    >
                                        {isDone ? <Check className="h-4 w-4" /> : <Icon className="h-4 w-4" />}
                                    </div>
                                    <span className={cn('text-[10px] font-medium leading-tight', isActive ? 'text-orange-700' : 'text-gray-500')}>
                                        {s.title}
                                    </span>
                                </button>
                            );
                        })}
                    </div>

                    <p className="mt-4 rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-xs text-blue-800">
                        Complete each step and click Next. After submit you will see a confirmation page. Seller login
                        is only available after admin approval.
                    </p>

                    <form className="mt-6" onSubmit={step === STEPS.length - 1 ? submit : (e) => { e.preventDefault(); handleNext(); }}>
                        <div
                            key={step}
                            className={cn(
                                'space-y-6 transition-all duration-300',
                                direction === 'forward' ? 'animate-in fade-in slide-in-from-right-4' : 'animate-in fade-in slide-in-from-left-4',
                            )}
                        >
                            {/* Step 1: Personal */}
                            {step === 0 && (
                                <section>
                                    <h2 className="text-lg font-semibold text-gray-900">Personal Information</h2>
                                    <p className="mt-1 text-sm text-gray-500">Your name, contact details, and account security.</p>
                                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <Label>First Name</Label>
                                            <Input value={data.first_name} onChange={(e) => setData('first_name', e.target.value)} className="mt-1" />
                                            <InputError message={displayErrors.first_name} />
                                        </div>
                                        <div>
                                            <Label>Last Name</Label>
                                            <Input value={data.last_name} onChange={(e) => setData('last_name', e.target.value)} className="mt-1" />
                                            <InputError message={displayErrors.last_name} />
                                        </div>
                                        <div>
                                            <Label>Mobile Number</Label>
                                            <Input value={data.mobile} onChange={(e) => setData('mobile', e.target.value)} className="mt-1" />
                                            <InputError message={displayErrors.mobile} />
                                        </div>
                                        <div>
                                            <Label>WhatsApp Number</Label>
                                            <Input value={data.whatsapp} onChange={(e) => setData('whatsapp', e.target.value)} className="mt-1" />
                                            <InputError message={displayErrors.whatsapp} />
                                        </div>
                                        <div className="sm:col-span-2">
                                            <Label>Email Address</Label>
                                            <Input
                                                type="email"
                                                value={data.email}
                                                readOnly={Boolean(inviteEmail)}
                                                onChange={(e) => setData('email', e.target.value)}
                                                className={cn('mt-1', inviteEmail && 'bg-gray-50 text-gray-700')}
                                            />
                                            {inviteEmail && (
                                                <p className="mt-1 text-xs text-gray-500">
                                                    This invite is tied to <span className="font-medium text-gray-700">{inviteEmail}</span>. The email cannot be changed.
                                                </p>
                                            )}
                                            <InputError message={displayErrors.email} />
                                        </div>
                                        {!isExistingUser && (
                                            <>
                                                <div>
                                                    <Label>Password</Label>
                                                    <Input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} className="mt-1" />
                                                    <InputError message={displayErrors.password} />
                                                </div>
                                                <div>
                                                    <Label>Confirm Password</Label>
                                                    <Input type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} className="mt-1" />
                                                    <InputError message={displayErrors.password_confirmation} />
                                                </div>
                                            </>
                                        )}
                                    </div>
                                </section>
                            )}

                            {/* Step 2: Business */}
                            {step === 1 && (
                                <section>
                                    <h2 className="text-lg font-semibold text-gray-900">Business Information</h2>
                                    <p className="mt-1 text-sm text-gray-500">Tell us about your store or registered business.</p>
                                    <div className="mt-4">
                                        <Label>Is your business registered?</Label>
                                        <div className="mt-2 flex gap-4">
                                            <label className="flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 has-[:checked]:border-orange-500 has-[:checked]:bg-orange-50">
                                                <input type="radio" checked={data.is_business_registered} onChange={() => setData('is_business_registered', true)} />
                                                <span className="text-sm font-medium">Yes, registered</span>
                                            </label>
                                            <label className="flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 has-[:checked]:border-orange-500 has-[:checked]:bg-orange-50">
                                                <input type="radio" checked={!data.is_business_registered} onChange={() => setData('is_business_registered', false)} />
                                                <span className="text-sm font-medium">No, individual seller</span>
                                            </label>
                                        </div>
                                    </div>

                                    {data.is_business_registered ? (
                                        <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                            <div className="sm:col-span-2">
                                                <Label>Business Name</Label>
                                                <Input value={data.business_name} onChange={(e) => setData('business_name', e.target.value)} className="mt-1" />
                                                <InputError message={displayErrors.business_name} />
                                            </div>
                                            <div>
                                                <Label>Registration Number</Label>
                                                <Input value={data.business_registration_number} onChange={(e) => setData('business_registration_number', e.target.value)} className="mt-1" />
                                                <InputError message={displayErrors.business_registration_number} />
                                            </div>
                                            <div>
                                                <Label>TIN (optional)</Label>
                                                <Input value={data.tin} onChange={(e) => setData('tin', e.target.value)} className="mt-1" />
                                            </div>
                                            <div className="sm:col-span-2">
                                                <Label>Business Address</Label>
                                                <Input value={data.business_address} onChange={(e) => setData('business_address', e.target.value)} className="mt-1" />
                                                <InputError message={displayErrors.business_address} />
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="mt-4">
                                            <Label>Store Name</Label>
                                            <Input value={data.store_name} onChange={(e) => setData('store_name', e.target.value)} className="mt-1" placeholder="How customers will see your shop" />
                                            <InputError message={displayErrors.store_name} />
                                        </div>
                                    )}
                                </section>
                            )}

                            {/* Step 3: Location */}
                            {step === 2 && (
                                <section>
                                    <h2 className="text-lg font-semibold text-gray-900">Location</h2>
                                    <p className="mt-1 text-sm text-gray-500">Where you live and operate from in Ghana.</p>
                                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <Label>Region</Label>
                                            <Input value={data.region} onChange={(e) => setData('region', e.target.value)} className="mt-1" placeholder="e.g. Greater Accra" />
                                            <InputError message={displayErrors.region} />
                                        </div>
                                        <div>
                                            <Label>City</Label>
                                            <Input value={data.city} onChange={(e) => setData('city', e.target.value)} className="mt-1" placeholder="e.g. Accra" />
                                            <InputError message={displayErrors.city} />
                                        </div>
                                        <div className="sm:col-span-2">
                                            <Label>Residential Address</Label>
                                            <Input value={data.residential_address} onChange={(e) => setData('residential_address', e.target.value)} className="mt-1" />
                                            <InputError message={displayErrors.residential_address} />
                                        </div>
                                        <div className="sm:col-span-2">
                                            <Label>Digital Address (Ghana Post GPS)</Label>
                                            <Input value={data.digital_address} onChange={(e) => setData('digital_address', e.target.value)} className="mt-1" placeholder="GA-XXX-XXXX" />
                                            <InputError message={displayErrors.digital_address} />
                                        </div>
                                    </div>
                                </section>
                            )}

                            {/* Step 4: KYC */}
                            {step === 3 && (
                                <section>
                                    <h2 className="text-lg font-semibold text-gray-900">KYC & Verification</h2>
                                    <p className="mt-1 text-sm text-gray-500">Upload your ID and shop photos for admin verification.</p>
                                    <div className="mt-4">
                                        <Label>Ghana Card Number</Label>
                                        <Input value={data.ghana_card_number} onChange={(e) => setData('ghana_card_number', e.target.value)} className="mt-1" />
                                        <InputError message={displayErrors.ghana_card_number} />
                                    </div>
                                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                        {fileInput('id_card_front', 'Ghana Card (Front)')}
                                        {fileInput('id_card_back', 'Ghana Card (Back)')}
                                    </div>
                                    <div className="mt-4">{fileInput('shop_photo', 'Front of your Shop')}</div>
                                    {data.is_business_registered && (
                                        <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                            {fileInput('form_a', 'Form A')}
                                            {fileInput('form_b', 'Form B')}
                                            <div className="sm:col-span-2">{fileInput('business_certificate', 'Business Certificate')}</div>
                                        </div>
                                    )}
                                    <p className="mt-3 text-xs text-gray-500">Accepted formats: JPG, PNG, PDF. Max 5MB per file.</p>
                                </section>
                            )}

                            {/* Step 5: Review */}
                            {step === 4 && (
                                <section className="space-y-4">
                                    <h2 className="text-lg font-semibold text-gray-900">Review & Submit</h2>
                                    <p className="text-sm text-gray-500">Check everything is correct before sending your application.</p>

                                    <SummarySection title="Personal" stepIndex={0}>
                                        <SummaryRow label="Name" value={`${data.first_name} ${data.last_name}`} />
                                        <SummaryRow label="Mobile" value={data.mobile} />
                                        <SummaryRow label="WhatsApp" value={data.whatsapp} />
                                        <SummaryRow label="Email" value={data.email} />
                                    </SummarySection>

                                    <SummarySection title="Business" stepIndex={1}>
                                        <SummaryRow label="Registered business" value={data.is_business_registered ? 'Yes' : 'No'} />
                                        {data.is_business_registered ? (
                                            <>
                                                <SummaryRow label="Business name" value={data.business_name} />
                                                <SummaryRow label="Registration #" value={data.business_registration_number} />
                                                <SummaryRow label="Business address" value={data.business_address} />
                                                {data.tin && <SummaryRow label="TIN" value={data.tin} />}
                                            </>
                                        ) : (
                                            <SummaryRow label="Store name" value={data.store_name} />
                                        )}
                                    </SummarySection>

                                    <SummarySection title="Location" stepIndex={2}>
                                        <SummaryRow label="Region / City" value={`${data.region}, ${data.city}`} />
                                        <SummaryRow label="Address" value={data.residential_address} />
                                        <SummaryRow label="Digital address" value={data.digital_address} />
                                    </SummarySection>

                                    <SummarySection title="Documents" stepIndex={3}>
                                        <SummaryRow label="Ghana Card #" value={data.ghana_card_number} />
                                        <SummaryRow label="ID front" value={fileLabel(data.id_card_front)} />
                                        <SummaryRow label="ID back" value={fileLabel(data.id_card_back)} />
                                        <SummaryRow label="Shop photo" value={fileLabel(data.shop_photo)} />
                                        {data.is_business_registered && (
                                            <>
                                                <SummaryRow label="Form A" value={fileLabel(data.form_a)} />
                                                <SummaryRow label="Form B" value={fileLabel(data.form_b)} />
                                                <SummaryRow label="Certificate" value={fileLabel(data.business_certificate)} />
                                            </>
                                        )}
                                    </SummarySection>

                                    <p className="rounded-lg border border-amber-100 bg-amber-50 px-4 py-3 text-xs text-amber-800">
                                        By submitting, you confirm that all information provided is accurate. Admin review
                                        typically takes 24–48 hours.
                                    </p>
                                </section>
                            )}
                        </div>

                        {/* Navigation */}
                        <div className="mt-8 flex items-center justify-between gap-3 border-t border-gray-100 pt-6">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={handlePrevious}
                                disabled={step === 0 || processing}
                                className={cn(step === 0 && 'invisible')}
                            >
                                <ChevronLeft className="mr-1 h-4 w-4" />
                                Previous
                            </Button>

                            {step < STEPS.length - 1 ? (
                                <Button type="submit" className="bg-orange-500 hover:bg-orange-600">
                                    Next
                                    <ChevronRight className="ml-1 h-4 w-4" />
                                </Button>
                            ) : (
                                <Button type="submit" disabled={processing} className="bg-orange-500 hover:bg-orange-600">
                                    {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                    {processing ? 'Submitting…' : 'Submit Application'}
                                </Button>
                            )}
                        </div>
                    </form>

                    {!isExistingUser && (
                        <p className="mt-4 text-center text-sm text-gray-500">
                            Want to buy instead? <TextLink href={route('register.buyer')}>Register as Buyer</TextLink>
                        </p>
                    )}
                </div>
            </div>
        </ShopLayout>
    );
}
