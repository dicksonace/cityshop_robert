import { ReactNode } from 'react';

import { AdminNavKey, adminNav } from '@/lib/admin-nav';
import PanelLayout from '@/layouts/panel-layout';

interface AdminLayoutProps {
    children: ReactNode;
    title: string;
    active: AdminNavKey;
}

export default function AdminLayout({ children, title, active }: AdminLayoutProps) {
    return (
        <PanelLayout title={title} panelTitle="Admin Panel" nav={adminNav(active)}>
            {children}
        </PanelLayout>
    );
}
