import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, MessageSquare } from 'lucide-react';

import AdminLayout from '@/layouts/admin-layout';
import { formatPrice, formatWalletTransactionType, Paginated, Wallet, WalletTransaction } from '@/types/marketplace';

interface BuyerShowProps {
    buyer: {
        id: number;
        name: string;
        email: string;
        mobile?: string;
        region?: string;
        city?: string;
        residential_address?: string;
        created_at: string;
        orders_count: number;
    };
    orders: Paginated<{
        id: number;
        order_number: string;
        total: number;
        status: string;
        payment_status: string;
        created_at: string;
        items?: { product_name?: string; product?: { name: string } }[];
    }>;
    conversations: {
        id: number;
        last_message_at?: string;
        seller?: { id: number; name: string; email: string };
        product?: { id: number; name: string; slug: string };
        latest_message?: { body?: string };
    }[];
    wallet: Wallet;
    transactions: Paginated<WalletTransaction>;
}

function formatDate(value?: string): string {
    if (!value) return '—';
    return new Date(value).toLocaleString('en-GH', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function AdminBuyerShow({ buyer, orders, conversations, wallet, transactions }: BuyerShowProps) {
    return (
        <AdminLayout title={buyer.name} active="buyers">
            <Head title={`${buyer.name} — Buyer`} />

            <Link href={route('admin.buyers.index')} className="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-orange-500">
                <ArrowLeft className="h-4 w-4" />
                Back to buyers
            </Link>

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="rounded-xl bg-white p-6 shadow-sm">
                    <h3 className="font-semibold text-gray-900">Profile</h3>
                    <dl className="mt-4 space-y-2 text-sm">
                        <div><dt className="text-gray-500">Name</dt><dd className="font-medium">{buyer.name}</dd></div>
                        <div><dt className="text-gray-500">Email</dt><dd>{buyer.email}</dd></div>
                        <div><dt className="text-gray-500">Mobile</dt><dd>{buyer.mobile ?? '—'}</dd></div>
                        <div><dt className="text-gray-500">Location</dt><dd>{[buyer.city, buyer.region].filter(Boolean).join(', ') || '—'}</dd></div>
                        <div><dt className="text-gray-500">Address</dt><dd>{buyer.residential_address ?? '—'}</dd></div>
                        <div><dt className="text-gray-500">Orders</dt><dd>{buyer.orders_count}</dd></div>
                        <div>
                            <dt className="text-gray-500">Joined</dt>
                            <dd>{new Date(buyer.created_at).toLocaleString('en-GH')}</dd>
                        </div>
                    </dl>
                </div>

                <div className="rounded-xl bg-gradient-to-br from-slate-900 to-blue-900 p-6 text-white shadow-sm">
                    <h3 className="font-semibold">Wallet</h3>
                    <p className="mt-1 text-sm text-white/70">Available balance</p>
                    <p className="mt-2 text-3xl font-bold">{formatPrice(wallet.available_balance)}</p>
                    <p className="mt-4 text-xs text-white/60">
                        Buyers fund their wallet to shop on CityShop. Refunds from approved returns are credited here.
                    </p>
                </div>

                <div className="rounded-xl bg-white p-6 shadow-sm">
                    <div className="flex items-center justify-between">
                        <h3 className="font-semibold text-gray-900">Chats with sellers</h3>
                        <Link href={route('admin.chats.index', { search: buyer.email })} className="text-sm text-blue-500 hover:underline">
                            All chats
                        </Link>
                    </div>
                    <div className="mt-4 space-y-3">
                        {conversations.length === 0 ? (
                            <p className="text-sm text-gray-500">No chats yet.</p>
                        ) : (
                            conversations.map((chat) => (
                                <Link
                                    key={chat.id}
                                    href={route('admin.chats.show', chat.id)}
                                    className="block rounded-lg border border-gray-100 p-3 hover:bg-gray-50"
                                >
                                    <div className="flex items-start gap-2">
                                        <MessageSquare className="mt-0.5 h-4 w-4 text-orange-500" />
                                        <div className="min-w-0 flex-1">
                                            <p className="font-medium text-gray-900">{chat.seller?.name ?? 'Seller'}</p>
                                            <p className="truncate text-xs text-gray-500">{chat.latest_message?.body ?? 'No messages'}</p>
                                        </div>
                                    </div>
                                </Link>
                            ))
                        )}
                    </div>
                </div>
            </div>

            <div className="mt-6 rounded-xl bg-white p-6 shadow-sm">
                <h3 className="font-semibold text-gray-900">Wallet transactions</h3>
                {transactions.data.length === 0 ? (
                    <p className="mt-4 text-sm text-gray-500">No wallet activity yet.</p>
                ) : (
                    <div className="mt-4 divide-y">
                        {transactions.data.map((tx) => {
                            const isCredit = tx.amount > 0;
                            return (
                                <div key={tx.id} className="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                                            {formatWalletTransactionType(tx.type)}
                                        </span>
                                        <p className="mt-1 text-sm text-gray-600">{tx.description}</p>
                                        <p className="text-xs text-gray-400">{formatDate(tx.created_at)}</p>
                                    </div>
                                    <p className={`font-semibold ${isCredit ? 'text-green-600' : 'text-red-600'}`}>
                                        {isCredit ? '+' : ''}{formatPrice(tx.amount)}
                                    </p>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>

            <div className="mt-6 rounded-xl bg-white p-6 shadow-sm">
                <h3 className="font-semibold text-gray-900">Orders</h3>
                <div className="mt-4 overflow-x-auto">
                    <table className="min-w-[640px] w-full text-sm">
                        <thead className="border-b bg-gray-50">
                            <tr>
                                <th className="px-3 py-2 text-left font-medium text-gray-500">Order</th>
                                <th className="px-3 py-2 text-left font-medium text-gray-500">Total</th>
                                <th className="px-3 py-2 text-left font-medium text-gray-500">Status</th>
                                <th className="px-3 py-2 text-left font-medium text-gray-500">Payment</th>
                                <th className="px-3 py-2 text-left font-medium text-gray-500">Date</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {orders.data.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-3 py-8 text-center text-gray-500">No orders yet.</td>
                                </tr>
                            ) : (
                                orders.data.map((order) => (
                                    <tr key={order.id} className="hover:bg-gray-50">
                                        <td className="px-3 py-2">
                                            <Link href={route('admin.orders.show', order.id)} className="font-medium text-orange-600 hover:underline">
                                                {order.order_number}
                                            </Link>
                                        </td>
                                        <td className="px-3 py-2 text-orange-500">{formatPrice(order.total)}</td>
                                        <td className="px-3 py-2 capitalize">{order.status}</td>
                                        <td className="px-3 py-2 capitalize">{order.payment_status}</td>
                                        <td className="px-3 py-2 text-gray-500">
                                            {new Date(order.created_at).toLocaleDateString('en-GH')}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AdminLayout>
    );
}
