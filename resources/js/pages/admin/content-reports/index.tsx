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
    target_type: string;
    target_id: number;
    reporter?: { id: number; name: string; email?: string } | null;
    owner?: { id: number; name: string; email?: string } | null;
}

interface ContentReportsIndexProps {
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

const targetLabels: Record<string, string> = {
    product: 'Product',
    message: 'Chat message',
    status: 'Status',
};

export default function AdminContentReportsIndex({ reports, status }: ContentReportsIndexProps) {
    const [notes, setNotes] = useState<Record<number, string>>({});

    const updateReport = (id: number, nextStatus: string) => {
        router.patch(route('admin.content-reports.update', id), {
            status: nextStatus,
            admin_notes: notes[id] ?? '',
        });
    };

    return (
        <AdminLayout title="Content Reports" active="content-reports">
            <Head title="Content Reports" />

            <p className="mb-4 text-sm text-gray-500">
                Reports of products, chat messages, and statuses. Review each one within 24 hours.
            </p>

            <div className="mb-4 flex flex-wrap gap-2">
                {tabs.map((tab) => (
                    <Link
                        key={tab.value}
                        href={route('admin.content-reports.index', { status: tab.value })}
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
                    reports.data.map((report) => (
                        <div key={report.id} className="rounded-xl bg-white p-5 shadow-sm">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p className="font-semibold text-gray-900">
                                        {targetLabels[report.target_type] ?? report.target_type} #{report.target_id}
                                    </p>
                                    <p className="text-sm text-gray-500">
                                        Posted by {report.owner?.name ?? 'Unknown'}
                                        {report.owner?.email ? ` · ${report.owner.email}` : ''}
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

                            <p className="mt-3 text-sm text-gray-600">
                                Reported by {report.reporter?.name ?? 'Unknown'}
                                {report.created_at ? ` · ${new Date(report.created_at).toLocaleString('en-GH')}` : ''}
                            </p>

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
                                    </div>
                                </div>
                            )}

                            {report.admin_notes && report.status !== 'open' && report.status !== 'reviewing' && (
                                <p className="mt-3 text-xs text-gray-500">Admin notes: {report.admin_notes}</p>
                            )}
                        </div>
                    ))
                )}
            </div>
        </AdminLayout>
    );
}
