import { Head, router } from '@inertiajs/react';
import { Mail, MailOpen } from 'lucide-react';

import PanelLayout from '@/layouts/panel-layout';
import { Paginated } from '@/types/marketplace';

interface ContactMessage {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    subject: string;
    message: string;
    is_read: boolean;
    created_at: string;
    user?: { name: string } | null;
}

interface ContactMessagesProps {
    messages: Paginated<ContactMessage>;
}

const nav = [
    { label: 'Dashboard', href: route('admin.dashboard') },
    { label: 'Sellers', href: route('admin.sellers.index') },
    { label: 'Products', href: route('admin.products.index') },
    { label: 'Orders', href: route('admin.orders.index') },
    { label: 'Withdrawals', href: route('admin.withdrawals.index') },
    { label: 'Disputes', href: route('admin.disputes.index') },
    { label: 'Messages', href: route('admin.contact-messages.index'), active: true },
];

const subjectLabels: Record<string, string> = {
    general: 'General Inquiry',
    order: 'Order Issue',
    payment: 'Payment Problem',
    seller: 'Seller Support',
    technical: 'Technical Issue',
    other: 'Other',
};

export default function ContactMessagesIndex({ messages }: ContactMessagesProps) {
    return (
        <PanelLayout title="Contact Messages" nav={nav} brandColor="text-blue-500">
            <Head title="Contact Messages" />
            <div className="space-y-4">
                {messages.data.length === 0 ? (
                    <div className="rounded-xl bg-white p-12 text-center shadow-sm">
                        <Mail className="mx-auto h-10 w-10 text-gray-300" />
                        <p className="mt-4 text-gray-500">No contact messages yet.</p>
                    </div>
                ) : (
                    messages.data.map((msg) => (
                        <div
                            key={msg.id}
                            className={`rounded-xl border bg-white p-5 shadow-sm ${msg.is_read ? 'border-gray-100' : 'border-orange-200 bg-orange-50/30'}`}
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <h3 className="font-semibold text-gray-900">{msg.name}</h3>
                                        {!msg.is_read && (
                                            <span className="rounded-full bg-orange-500 px-2 py-0.5 text-[10px] font-bold text-white uppercase">New</span>
                                        )}
                                    </div>
                                    <p className="text-sm text-gray-500">{msg.email}{msg.phone ? ` · ${msg.phone}` : ''}</p>
                                </div>
                                <div className="text-right text-xs text-gray-400">
                                    <p>{new Date(msg.created_at).toLocaleString()}</p>
                                    <p className="mt-1 font-medium text-orange-600">{subjectLabels[msg.subject] ?? msg.subject}</p>
                                </div>
                            </div>
                            <p className="mt-4 whitespace-pre-wrap text-sm text-gray-700">{msg.message}</p>
                            {!msg.is_read && (
                                <button
                                    type="button"
                                    onClick={() => router.patch(route('admin.contact-messages.read', msg.id))}
                                    className="mt-4 flex items-center gap-1 text-sm font-medium text-blue-600 hover:underline"
                                >
                                    <MailOpen className="h-4 w-4" />
                                    Mark as read
                                </button>
                            )}
                        </div>
                    ))
                )}
            </div>
        </PanelLayout>
    );
}
