import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Clock, LoaderCircle, Mail, MapPin, MessageCircle, Phone } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ShopLayout from '@/layouts/shop-layout';
import { SharedData } from '@/types';

interface ContactInfo {
    email: string;
    phone: string;
    whatsapp: string;
    address: string;
    hours: string;
}

interface ContactProps {
    contact: ContactInfo;
    subjects: Record<string, string>;
    defaults: { name: string; email: string; phone: string };
}

export default function Contact({ contact, subjects, defaults }: ContactProps) {
    const { flash } = usePage<SharedData>().props;

    const { data, setData, post, processing, errors, reset } = useForm({
        name: defaults.name,
        email: defaults.email,
        phone: defaults.phone,
        subject: 'general',
        message: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('contact.store'), {
            onSuccess: () => reset('message'),
        });
    };

    return (
        <ShopLayout>
            <Head title="Contact Us" />
            <div className="mx-auto max-w-6xl px-4 py-10">
                <div className="text-center">
                    <h1 className="text-3xl font-bold text-gray-900">Contact Us</h1>
                    <p className="mt-2 text-gray-500">We&apos;re here to help buyers and sellers across Ghana.</p>
                </div>

                {flash.success && (
                    <div className="mt-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                        {flash.success}
                    </div>
                )}

                {flash.info && (
                    <div className="mt-6 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                        {flash.info}
                    </div>
                )}

                {flash.error && (
                    <div className="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {flash.error}
                    </div>
                )}

                <div className="mt-6 rounded-xl border border-orange-100 bg-orange-50 px-4 py-3 text-sm text-orange-900">
                    Interested in selling on CityShop? Choose <strong>Become a Seller</strong> as your subject below.
                    Our team will review your request and send a private registration link if approved.
                    Already a seller?{' '}
                    <Link href={route('seller.login')} className="font-medium text-orange-600 hover:underline">
                        Sign in to Seller Centre
                    </Link>
                    .
                </div>

                <div className="mt-10 grid gap-8 lg:grid-cols-5">
                    <div className="space-y-4 lg:col-span-2">
                        <div className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                            <h2 className="font-semibold text-gray-900">Get in touch</h2>
                            <ul className="mt-4 space-y-4 text-sm text-gray-600">
                                <li className="flex items-start gap-3">
                                    <Mail className="mt-0.5 h-5 w-5 shrink-0 text-orange-500" />
                                    <div>
                                        <p className="font-medium text-gray-900">Email</p>
                                        <a href={`mailto:${contact.email}`} className="hover:text-orange-500">{contact.email}</a>
                                    </div>
                                </li>
                                <li className="flex items-start gap-3">
                                    <Phone className="mt-0.5 h-5 w-5 shrink-0 text-orange-500" />
                                    <div>
                                        <p className="font-medium text-gray-900">Phone</p>
                                        <a href={`tel:${contact.phone.replace(/\s/g, '')}`} className="hover:text-orange-500">{contact.phone}</a>
                                    </div>
                                </li>
                                <li className="flex items-start gap-3">
                                    <MessageCircle className="mt-0.5 h-5 w-5 shrink-0 text-green-500" />
                                    <div>
                                        <p className="font-medium text-gray-900">WhatsApp</p>
                                        <a
                                            href={`https://wa.me/${contact.whatsapp.replace(/\D/g, '')}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="hover:text-orange-500"
                                        >
                                            {contact.whatsapp}
                                        </a>
                                    </div>
                                </li>
                                <li className="flex items-start gap-3">
                                    <MapPin className="mt-0.5 h-5 w-5 shrink-0 text-orange-500" />
                                    <div>
                                        <p className="font-medium text-gray-900">Office</p>
                                        <p>{contact.address}</p>
                                    </div>
                                </li>
                                <li className="flex items-start gap-3">
                                    <Clock className="mt-0.5 h-5 w-5 shrink-0 text-orange-500" />
                                    <div>
                                        <p className="font-medium text-gray-900">Business Hours</p>
                                        <p>{contact.hours}</p>
                                    </div>
                                </li>
                            </ul>
                        </div>

                        <div className="rounded-2xl bg-gradient-to-br from-orange-500 to-orange-600 p-6 text-white">
                            <h3 className="font-semibold">Need quick answers?</h3>
                            <p className="mt-2 text-sm text-orange-100">Check our FAQ for help with orders, payments, and selling.</p>
                            <Button asChild variant="secondary" className="mt-4 bg-white text-orange-600 hover:bg-orange-50">
                                <Link href={route('faq')}>View FAQ</Link>
                            </Button>
                        </div>
                    </div>

                    <form onSubmit={submit} className="space-y-5 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm lg:col-span-3">
                        <h2 className="text-lg font-semibold text-gray-900">Send us a message</h2>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="name">Full Name</Label>
                                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required className="mt-1" />
                                <InputError message={errors.name} />
                            </div>
                            <div>
                                <Label htmlFor="email">Email</Label>
                                <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} required className="mt-1" />
                                <InputError message={errors.email} />
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="phone">Phone (optional)</Label>
                                <Input id="phone" value={data.phone} onChange={(e) => setData('phone', e.target.value)} placeholder="024 XXX XXXX" className="mt-1" />
                                <InputError message={errors.phone} />
                            </div>
                            <div>
                                <Label htmlFor="subject">Subject</Label>
                                <select
                                    id="subject"
                                    value={data.subject}
                                    onChange={(e) => setData('subject', e.target.value)}
                                    className="mt-1 w-full rounded-md border border-input px-3 py-2 text-sm"
                                >
                                    {Object.entries(subjects).map(([value, label]) => (
                                        <option key={value} value={value}>{label}</option>
                                    ))}
                                </select>
                                <InputError message={errors.subject} />
                            </div>
                        </div>

                        <div>
                            <Label htmlFor="message">Message</Label>
                            <textarea
                                id="message"
                                value={data.message}
                                onChange={(e) => setData('message', e.target.value)}
                                rows={6}
                                required
                                placeholder="Tell us how we can help..."
                                className="mt-1 w-full rounded-md border border-input px-3 py-2 text-sm"
                            />
                            <InputError message={errors.message} />
                        </div>

                        <Button type="submit" disabled={processing} className="w-full bg-orange-500 py-6 hover:bg-orange-600 sm:w-auto sm:px-10">
                            {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                            Send Message
                        </Button>
                    </form>
                </div>
            </div>
        </ShopLayout>
    );
}
