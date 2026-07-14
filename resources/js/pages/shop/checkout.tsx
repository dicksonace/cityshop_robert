import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ShopLayout from '@/layouts/shop-layout';
import { CartItem, formatPrice, productImageUrl, Wallet } from '@/types/marketplace';
import { User } from '@/types';

interface SellerPaymentMethod {
    id: number;
    type: string;
    label: string | null;
    account_name: string;
    account_number: string | null;
    network: string | null;
    bank_name: string | null;
    instructions: string | null;
    display_label?: string;
}

interface SellerGroup {
    seller_id: number;
    seller_name: string;
    accept_marketplace_payments: boolean;
    accept_direct_payments: boolean;
    payment_methods: SellerPaymentMethod[];
    items: CartItem[];
    subtotal: number;
    shipping_cost: number;
    shipping_label: string;
    shipping_note?: string | null;
    package_total: number;
}

interface CheckoutProps {
    sellerGroups: SellerGroup[];
    subtotal: number;
    shippingTotal: number;
    grandTotal: number;
    user: User;
    wallet: Wallet;
}

export default function Checkout({ sellerGroups, subtotal, shippingTotal, grandTotal, user, wallet }: CheckoutProps) {
    const initialSellerPayments: Record<string, { channel: string; method_id?: number }> = {};
    sellerGroups.forEach((group) => {
        if (group.accept_direct_payments && !group.accept_marketplace_payments) {
            initialSellerPayments[String(group.seller_id)] = {
                channel: 'direct',
                method_id: group.payment_methods[0]?.id,
            };
        } else {
            initialSellerPayments[String(group.seller_id)] = { channel: 'marketplace' };
        }
    });

    const { data, setData, post, processing, errors } = useForm({
        receiver_name: user.name || '',
        receiver_phone: (user.mobile as string) || '',
        region: (user.region as string) || '',
        city: (user.city as string) || '',
        digital_address: (user.digital_address as string) || '',
        delivery_notes: '',
        payment_method: 'momo',
        seller_payments: initialSellerPayments,
        seller_coupons: {} as Record<string, string>,
    });

    const setSellerChannel = (sellerId: number, channel: string, methodId?: number) => {
        setData('seller_payments', {
            ...data.seller_payments,
            [String(sellerId)]: { channel, method_id: methodId },
        });
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('checkout.store'));
    };

    const marketplaceTotal = sellerGroups.reduce((sum, group) => {
        const choice = data.seller_payments[String(group.seller_id)] ?? { channel: 'marketplace' };
        const usesMarketplace = choice.channel === 'marketplace' && group.accept_marketplace_payments;
        return usesMarketplace ? sum + group.package_total : sum;
    }, 0);

    const hasMarketplaceOrders = marketplaceTotal > 0;
    const walletBalance = Number(wallet.available_balance);
    const walletCoversMarketplace = walletBalance >= marketplaceTotal;
    const canUseWallet = hasMarketplaceOrders && walletCoversMarketplace;

    return (
        <ShopLayout>
            <Head title="Checkout" />
            <div className="mx-auto max-w-4xl px-3 py-4 sm:px-4 sm:py-8">
                <h1 className="text-2xl font-bold text-gray-900">Checkout</h1>
                <p className="mt-1 text-sm text-gray-500">
                    One purchase — {sellerGroups.length} package{sellerGroups.length === 1 ? '' : 's'} (one per store). Each store ships separately.
                </p>

                <form onSubmit={submit} className="mt-6 grid gap-8 lg:grid-cols-2">
                    <div className="space-y-6">
                        <div className="space-y-4 rounded-xl bg-white p-6 shadow-sm">
                            <h2 className="font-semibold text-gray-900">Delivery Details</h2>
                            <div>
                                <Label>Receiver Name</Label>
                                <Input value={data.receiver_name} onChange={(e) => setData('receiver_name', e.target.value)} required className="mt-1" />
                                <InputError message={errors.receiver_name} />
                            </div>
                            <div>
                                <Label>Phone Number</Label>
                                <Input value={data.receiver_phone} onChange={(e) => setData('receiver_phone', e.target.value)} required className="mt-1" />
                                <InputError message={errors.receiver_phone} />
                            </div>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <Label>Region</Label>
                                    <Input value={data.region} onChange={(e) => setData('region', e.target.value)} required className="mt-1" />
                                </div>
                                <div>
                                    <Label>City</Label>
                                    <Input value={data.city} onChange={(e) => setData('city', e.target.value)} required className="mt-1" />
                                </div>
                            </div>
                            <div>
                                <Label>Digital Address</Label>
                                <Input value={data.digital_address} onChange={(e) => setData('digital_address', e.target.value)} className="mt-1" />
                            </div>
                            <div>
                                <Label>Delivery Notes</Label>
                                <Input value={data.delivery_notes} onChange={(e) => setData('delivery_notes', e.target.value)} className="mt-1" />
                            </div>
                        </div>

                        <div className="rounded-xl bg-white p-6 shadow-sm">
                            <h2 className="font-semibold text-gray-900">Payment</h2>
                            <p className="mt-1 text-sm text-gray-500">
                                Pay with your wallet or via Paystack. Direct seller payments stay on the next step.
                            </p>
                            <div className="mt-3 space-y-2">
                                {hasMarketplaceOrders && (
                                    <label className={`flex cursor-pointer items-start gap-2 rounded-lg border p-3 hover:bg-gray-50 ${!canUseWallet ? 'opacity-70' : ''}`}>
                                        <input
                                            type="radio"
                                            name="payment_method"
                                            value="wallet"
                                            checked={data.payment_method === 'wallet'}
                                            onChange={() => setData('payment_method', 'wallet')}
                                            disabled={!canUseWallet}
                                            className="mt-1"
                                        />
                                        <div className="flex-1">
                                            <span className="font-medium">My Wallet</span>
                                            <p className="text-sm text-gray-500">
                                                Balance: {formatPrice(walletBalance)}
                                                {marketplaceTotal > 0 && (
                                                    <> · CityShop portion: {formatPrice(marketplaceTotal)}</>
                                                )}
                                            </p>
                                            {!walletCoversMarketplace && (
                                                <p className="mt-1 text-xs text-amber-600">
                                                    Insufficient balance.{' '}
                                                    <Link href={route('wallet.index')} className="underline">Add funds</Link>
                                                </p>
                                            )}
                                        </div>
                                    </label>
                                )}
                                {[
                                    { value: 'momo', label: 'MTN MoMo (Paystack)' },
                                    { value: 'card', label: 'Visa / Mastercard (Paystack)' },
                                    { value: 'cash', label: 'Cash on Delivery' },
                                ].map((method) => (
                                    <label key={method.value} className="flex cursor-pointer items-center gap-2 rounded-lg border p-3 hover:bg-gray-50">
                                        <input type="radio" name="payment_method" value={method.value} checked={data.payment_method === method.value} onChange={() => setData('payment_method', method.value)} />
                                        {method.label}
                                    </label>
                                ))}
                            </div>
                            <InputError message={errors.payment_method} />
                        </div>
                    </div>

                    <div className="space-y-4">
                        {sellerGroups.map((group) => {
                            const choice = data.seller_payments[String(group.seller_id)] ?? { channel: 'marketplace' };

                            return (
                                <div key={group.seller_id} className="rounded-xl bg-white p-6 shadow-sm">
                                    <div className="flex items-center justify-between gap-2">
                                        <div>
                                            <p className="text-xs font-medium uppercase tracking-wide text-gray-400">
                                                Package · {sellerGroups.findIndex((g) => g.seller_id === group.seller_id) + 1} of {sellerGroups.length}
                                            </p>
                                            <h2 className="font-semibold text-gray-900">{group.seller_name}</h2>
                                        </div>
                                        <span className="text-sm font-medium text-orange-500">{formatPrice(group.package_total)}</span>
                                    </div>
                                    <div className="mt-3 space-y-2">
                                        {group.items.map((item) => (
                                            <div key={item.id} className="flex gap-3 text-sm">
                                                <img src={productImageUrl(item.product.images?.[0]?.path)} alt="" className="h-10 w-10 rounded object-contain" />
                                                <div className="flex-1">
                                                    <p className="line-clamp-1 font-medium">{item.product.name}</p>
                                                    <p className="text-gray-500">Qty: {item.quantity}</p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                    <div className="mt-3 space-y-1 border-t pt-3 text-sm">
                                        <div className="flex justify-between text-gray-600">
                                            <span>Items</span>
                                            <span>{formatPrice(group.subtotal)}</span>
                                        </div>
                                        <div className="flex justify-between text-gray-600">
                                            <span>
                                                {group.shipping_label}
                                                {group.shipping_note ? (
                                                    <span className="mt-0.5 block text-xs text-gray-400">{group.shipping_note}</span>
                                                ) : null}
                                            </span>
                                            <span>{group.shipping_cost > 0 ? formatPrice(group.shipping_cost) : group.shipping_label === 'Arrange with seller' ? '—' : formatPrice(0)}</span>
                                        </div>
                                    </div>

                                    {data.payment_method !== 'cash' &&
                                        (data.payment_method !== 'wallet' || group.accept_direct_payments) &&
                                        (group.accept_marketplace_payments || group.accept_direct_payments) && (
                                        <div className="mt-4 border-t pt-4">
                                            <p className="text-sm font-medium text-gray-700">How to pay this seller</p>
                                            <div className="mt-2 space-y-2">
                                                {group.accept_marketplace_payments && (
                                                    <label className="flex cursor-pointer items-center gap-2 rounded-lg border p-2 text-sm">
                                                        <input type="radio" checked={choice.channel === 'marketplace'} onChange={() => setSellerChannel(group.seller_id, 'marketplace')} />
                                                        Pay via CityShop (secure)
                                                    </label>
                                                )}
                                                {group.accept_direct_payments && (
                                                    <label className="flex cursor-pointer items-center gap-2 rounded-lg border p-2 text-sm">
                                                        <input type="radio" checked={choice.channel === 'direct'} onChange={() => setSellerChannel(group.seller_id, 'direct', group.payment_methods[0]?.id)} />
                                                        Pay seller directly
                                                    </label>
                                                )}
                                                {choice.channel === 'direct' && group.payment_methods.length > 0 && (
                                                    <select
                                                        className="w-full rounded-md border px-3 py-2 text-sm"
                                                        value={choice.method_id ?? group.payment_methods[0]?.id}
                                                        onChange={(e) => setSellerChannel(group.seller_id, 'direct', Number(e.target.value))}
                                                    >
                                                        {group.payment_methods.map((m) => (
                                                            <option key={m.id} value={m.id}>
                                                                {m.network ? `${m.network} — ${m.account_number}` : m.bank_name ? `${m.bank_name} — ${m.account_number}` : m.label ?? m.type}
                                                            </option>
                                                        ))}
                                                    </select>
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    <div className="mt-4 border-t pt-4">
                                        <Label className="text-sm">Coupon code (optional)</Label>
                                        <Input
                                            className="mt-1 font-mono uppercase"
                                            placeholder="SAVE10"
                                            value={data.seller_coupons[String(group.seller_id)] ?? ''}
                                            onChange={(e) => setData('seller_coupons', {
                                                ...data.seller_coupons,
                                                [String(group.seller_id)]: e.target.value.toUpperCase(),
                                            })}
                                        />
                                    </div>
                                    <InputError message={errors.coupon} />
                                </div>
                            );
                        })}

                        <div className="rounded-xl bg-white p-6 shadow-sm">
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between text-gray-600">
                                    <span>Items ({sellerGroups.length} package{sellerGroups.length === 1 ? '' : 's'})</span>
                                    <span>{formatPrice(subtotal)}</span>
                                </div>
                                <div className="flex justify-between text-gray-600">
                                    <span>Delivery</span>
                                    <span>{shippingTotal > 0 ? formatPrice(shippingTotal) : formatPrice(0)}</span>
                                </div>
                                <div className="flex justify-between border-t pt-2 text-lg font-bold">
                                    <span>Total</span>
                                    <span className="text-orange-500">{formatPrice(grandTotal)}</span>
                                </div>
                            </div>
                            <Button type="submit" disabled={processing} className="mt-6 w-full bg-orange-500 py-6 hover:bg-orange-600">
                                {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                {data.payment_method === 'cash'
                                    ? 'Place Order'
                                    : data.payment_method === 'wallet'
                                      ? 'Pay with Wallet'
                                      : 'Continue to Payment'}
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </ShopLayout>
    );
}
