import { Head, Link, usePage } from '@inertiajs/react';
import { Megaphone, Plus } from 'lucide-react';

import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/admin-layout';
import { Paginated } from '@/types/marketplace';
import { SharedData } from '@/types';

interface AnnouncementRow {
    id: number;
    audience: string;
    title: string;
    body: string;
    send_email: boolean;
    recipients_count: number;
    admin?: { id: number; name: string } | null;
    created_at?: string | null;
}

interface BuyerAnnouncementsIndexProps {
    announcements: Paginated<AnnouncementRow>;
}

const audienceLabels: Record<string, string> = {
    one: 'One buyer',
    selected: 'Selected buyers',
    all: 'All buyers',
};

export default function BuyerAnnouncementsIndex({ announcements }: BuyerAnnouncementsIndexProps) {
    const { flash } = usePage<SharedData>().props;

    return (
        <AdminLayout title="Message Buyers" active="buyer-announcements">
            <Head title="Message Buyers" />

            {flash.success && (
                <div className="mb-4 rounded-lg bg-green-50 px-4 py-2 text-sm text-green-700">{flash.success}</div>
            )}

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-gray-600">
                    Send in-app alerts to one buyer, selected buyers, or every buyer.
                </p>
                <Link href={route('admin.buyer-announcements.create')}>
                    <Button className="bg-orange-500 hover:bg-orange-600">
                        <Plus className="mr-1.5 h-4 w-4" />
                        New message
                    </Button>
                </Link>
            </div>

            {announcements.data.length === 0 ? (
                <div className="rounded-xl bg-white p-12 text-center shadow-sm">
                    <Megaphone className="mx-auto h-10 w-10 text-gray-300" />
                    <p className="mt-3 text-gray-500">No buyer messages sent yet.</p>
                    <Link href={route('admin.buyer-announcements.create')} className="mt-4 inline-block text-sm text-orange-500 hover:underline">
                        Send your first message
                    </Link>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl bg-white shadow-sm">
                    <ul className="divide-y">
                        {announcements.data.map((row) => (
                            <li key={row.id} className="px-5 py-4">
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <p className="font-semibold text-gray-900">{row.title}</p>
                                        <p className="mt-1 line-clamp-2 text-sm text-gray-600">{row.body}</p>
                                        <div className="mt-2 flex flex-wrap gap-2 text-xs text-gray-500">
                                            <span className="rounded-full bg-gray-100 px-2 py-0.5">
                                                {audienceLabels[row.audience] ?? row.audience}
                                            </span>
                                            <span>{row.recipients_count} recipient{row.recipients_count === 1 ? '' : 's'}</span>
                                            {row.send_email && <span>Email/SMS too</span>}
                                            {row.admin && <span>By {row.admin.name}</span>}
                                            {row.created_at && <span>{new Date(row.created_at).toLocaleString()}</span>}
                                        </div>
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </AdminLayout>
    );
}
