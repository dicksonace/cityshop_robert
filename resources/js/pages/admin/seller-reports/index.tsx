import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/admin-layout';
import { Paginated } from '@/types/marketplace';

interface ReportRow {
    id: number;
    reason: string;
    details?: string | null;
    status: string;
    created_at: string;
    admin_notes?: string | null;
    reporter?: { id: number; name: string; email: string; mobile?: string };
    seller?: {
        id: number;
        name: string;
        email: string;
        mobile?: string;
        seller_profile?: { store_name?: string; business_name?: string; slug?: string; status?: string };
    };
    product?: { id: number; name: string; slug: string } | null;
}

interface SellerReportsIndexProps {
    reports: Paginated<ReportRow>;
    status: string;
}

const tabs = [
    { value: 'open', label: 'Open' },
    { value: 'reviewing', label: 'Reviewing' },
    { value: 'resolved', label: 'Resolved' },
    { value: 'dismissed', label: 'Dismissed' },
    { value: 'all', label: 'All' },
];

const reasonLabels: Record<string, string> = {
    scam: 'Scam or fraud',
    counterfeit: 'Counterfeit products',
    harassment: 'Harassment',
    poor_service: 'Poor service',
    prohibited_items: 'Prohibited items',
    fake_listings: 'Fake listings',
    other: 'Other',
};

export default function AdminSellerReportsIndex({ reports, status }: SellerReportsIndexProps) {
    const [notes, setNotes] = useState<Record<number, string>>({});

    const updateReport = (id: number, nextStatus: string, blockSeller = false) => {
        router.patch(route('admin.seller-reports.update', id), {
            status: nextStatus,
            admin_notes: notes[id] ?? '',
            block_seller: blockSeller,
        });
    };

    return (
        <AdminLayout title="Seller Reports" active="seller-reports">
            <Head title="Seller Reports" />

            <p className="mb-4 text-sm text-gray-500">
                Buyers can report seller accounts. Review reports and block sellers when needed.
            </p>

            <div className="mb-4 flex flex-wrap gap-2">
                {tabs.map((tab) => (
                    <Link
                        key={tab.value}
                        href={route('admin.seller-reports.index', { status: tab.value })}
                        className={`rounded-full px-4 py-1.5 text-sm font-medium ${
                            status === tab.value ? 'bg-blue-500 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200'
                        }`}
                    >
                        {tab.label}
                    </Link>
                ))}
            </div>

            <div className="space-y-4">
                {reports.data.length === 0 ? (
                    <div className="rounded-xl bg-white p-10 text-center text-sm text-gray-500 shadow-sm">
                        No reports in this filter.
                    </div>
                ) : (
                    reports.data.map((report) => {
                        const storeName =
                            report.seller?.seller_profile?.business_name ??
                            report.seller?.seller_profile?.store_name ??
                            report.seller?.name ??
                            'Seller';

                        return (
                            <div key={report.id} className="rounded-xl bg-white p-5 shadow-sm">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p className="font-semibold text-gray-900">{storeName}</p>
                                        <p className="text-sm text-gray-500">
                                            {report.seller?.email} · {report.seller?.mobile ?? 'No phone'}
                                        </p>
                                        <p className="mt-1 text-sm font-medium text-red-700">
                                            {reasonLabels[report.reason] ?? report.reason}
                                        </p>
                                    </div>
                                    <span className="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium capitalize text-gray-700">
                                        {report.status}
                                    </span>
                                </div>

                                {report.details && (
                                    <p className="mt-3 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700">{report.details}</p>
                                )}

                                <div className="mt-3 grid gap-2 text-sm text-gray-600 sm:grid-cols-2">
                                    <p>
                                        Reported by:{' '}
                                        {report.reporter ? (
                                            <Link href={route('admin.buyers.show', report.reporter.id)} className="text-blue-500 hover:underline">
                                                {report.reporter.name}
                                            </Link>
                                        ) : (
                                            'Unknown'
                                        )}
                                    </p>
                                    <p>
                                        {new Date(report.created_at).toLocaleString('en-GH')}
                                    </p>
                                    {report.product && (
                                        <p>
                                            Product:{' '}
                                            <Link href={route('products.show', report.product.slug)} className="text-orange-600 hover:underline">
                                                {report.product.name}
                                            </Link>
                                        </p>
                                    )}
                                </div>

                                {(report.status === 'open' || report.status === 'reviewing') && (
                                    <div className="mt-4 space-y-3 border-t border-gray-100 pt-4">
                                        <Input
                                            placeholder="Admin notes..."
                                            value={notes[report.id] ?? report.admin_notes ?? ''}
                                            onChange={(e) => setNotes((prev) => ({ ...prev, [report.id]: e.target.value }))}
                                        />
                                        <div className="flex flex-wrap gap-2">
                                            <Button size="sm" variant="outline" onClick={() => updateReport(report.id, 'reviewing')}>
                                                Mark reviewing
                                            </Button>
                                            <Button size="sm" className="bg-green-600 hover:bg-green-700" onClick={() => updateReport(report.id, 'resolved')}>
                                                Resolve
                                            </Button>
                                            <Button size="sm" variant="outline" onClick={() => updateReport(report.id, 'dismissed')}>
                                                Dismiss
                                            </Button>
                                            <Button size="sm" variant="destructive" onClick={() => updateReport(report.id, 'resolved', true)}>
                                                Resolve & block seller
                                            </Button>
                                        </div>
                                    </div>
                                )}

                                {report.admin_notes && report.status !== 'open' && (
                                    <p className="mt-3 text-xs text-gray-500">Admin notes: {report.admin_notes}</p>
                                )}
                            </div>
                        );
                    })
                )}
            </div>
        </AdminLayout>
    );
}
