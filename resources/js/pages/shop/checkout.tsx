import { Head, Link, router, useForm } from '@inertiajs/react';
import { ChevronRight, LoaderCircle, MapPin, Pencil } from 'lucide-react';
import { FormEventHandler, useEffect, useMemo, useState } from 'react';

import InputError from '@/components/input-error';
import DirectPaymentDetails from '@/components/shop/direct-payment-details';
import PaymentMethodIcon from '@/components/shop/payment-method-icon';
import SellerPaymentMethodPicker from '@/components/shop/seller-payment-method-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ShopLayout from '@/layouts/shop-layout';
import {
    checkoutCartKey,
    clearCheckoutDraft,
    loadCheckoutDraft,
    saveCheckoutDraft,
} from '@/lib/checkout-draft';
import { isBankPaymentMethod } from '@/lib/payment-method-display';
import { CartItem, formatPrice, productImageUrl, Wallet } from '@/types/marketplace';
import { BuyerAddress } from '@/types/buyer-address';

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
    store_slug?: string | null;
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
    wallet: Wallet;
    addresses: BuyerAddress[];
    selectedAddressId: number | null;
}

export default function Checkout({
    sellerGroups,
    subtotal,
    shippingTotal,
    grandTotal,
    wallet,
    addresses,
    selectedAddressId,
}: CheckoutProps) {
    const cartKey = useMemo(() => checkoutCartKey(sellerGroups), [sellerGroups]);

    const initialForm = useMemo(() => {
        const sellerPayments: Record<string, { channel: string; method_id?: number }> = {};
        sellerGroups.forEach((group) => {
            if (group.accept_direct_payments && !group.accept_marketplace_payments) {
                sellerPayments[String(group.seller_id)] = {
                    channel: 'direct',
                    method_id: group.payment_methods[0]?.id,
                };
            } else {
                sellerPayments[String(group.seller_id)] = { channel: 'marketplace' };
            }
        });

        const draft = loadCheckoutDraft(cartKey);
        const addressIds = new Set(addresses.map((a) => a.id));
        const restoredAddressId =
            draft?.address_id != null && addressIds.has(draft.address_id)
                ? draft.address_id
                : selectedAddressId;

        const allowedMethods = new Set(['momo', 'card', 'cash', 'wallet']);
        const paymentMethod =
            draft?.payment_method && allowedMethods.has(draft.payment_method)
                ? draft.payment_method
                : 'momo';

        if (draft?.seller_payments) {
            sellerGroups.forEach((group) => {
                const key = String(group.seller_id);
                const saved = draft.seller_payments[key];
                if (!saved) return;

                if (saved.channel === 'direct' && group.accept_direct_payments) {
                    const methodId =
                        saved.method_id && group.payment_methods.some((m) => m.id === saved.method_id)
                            ? saved.method_id
                            : group.payment_methods[0]?.id;
                    sellerPayments[key] = { channel: 'direct', method_id: methodId };
                } else if (saved.channel === 'marketplace' && group.accept_marketplace_payments) {
                    sellerPayments[key] = { channel: 'marketplace' };
                }
            });
        }

        const sellerCoupons: Record<string, string> = {};
        if (draft?.seller_coupons) {
            sellerGroups.forEach((group) => {
                const key = String(group.seller_id);
                const code = draft.seller_coupons[key];
                if (typeof code === 'string' && code.trim() !== '') {
                    sellerCoupons[key] = code;
                }
            });
        }

        return {
            address_id: restoredAddressId,
            payment_method: paymentMethod,
            seller_payments: sellerPayments,
            seller_coupons: sellerCoupons,
        };
    }, [addresses, cartKey, selectedAddressId, sellerGroups]);

    const [pickingAddress, setPickingAddress] = useState(false);
    const [activeAddressId, setActiveAddressId] = useState<number | null>(initialForm.address_id);

    const { data, setData, errors } = useForm(initialForm);
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        saveCheckoutDraft({
            cartKey,
            address_id: data.address_id,
            payment_method: data.payment_method,
            seller_payments: data.seller_payments,
            seller_coupons: data.seller_coupons,
        });
    }, [cartKey, data.address_id, data.payment_method, data.seller_coupons, data.seller_payments]);

    const selected =
        addresses.find((a) => a.id === (activeAddressId ?? data.address_id))
        ?? addresses.find((a) => a.is_default)
        ?? addresses[0]
        ?? null;

    const setSellerChannel = (sellerId: number, channel: string, methodId?: number) => {
        setData('seller_payments', {
            ...data.seller_payments,
            [String(sellerId)]: { channel, method_id: methodId },
        });
    };

    const chooseAddress = (id: number) => {
        setActiveAddressId(id);
        setData('address_id', id);
        setPickingAddress(false);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (! selected) {
            router.visit(route('addresses.create', { return: 'checkout' }));
            return;
        }
        setSubmitting(true);
        router.post(
            route('checkout.store'),
            {
                address_id: selected.id,
                payment_method: data.payment_method,
                seller_payments: data.seller_payments,
                seller_coupons: data.seller_coupons,
            },
            {
                onSuccess: () => clearCheckoutDraft(),
                onFinish: () => setSubmitting(false),
            },
        );
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

    useEffect(() => {
        if (data.payment_method === 'wallet' && !canUseWallet) {
            setData('payment_method', 'momo');
        }
    }, [canUseWallet, data.payment_method, setData]);

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
                        <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                            <div className="flex items-center justify-between gap-2">
                                <h2 className="flex items-center gap-2 font-semibold text-gray-900">
                                    <MapPin className="h-4 w-4 text-orange-500" />
                                    Ship to
                                </h2>
                                {addresses.length > 0 && (
                                    <button
                                        type="button"
                                        className="text-sm font-medium text-orange-600 hover:underline"
                                        onClick={() => setPickingAddress((v) => !v)}
                                    >
                                        {pickingAddress ? 'Done' : 'Change address'}
                                    </button>
                                )}
                            </div>

                            {! selected ? (
                                <div className="mt-4 text-center">
                                    <p className="text-sm text-gray-500">Add a delivery address to continue.</p>
                                    <Button asChild className="mt-4 bg-orange-500 hover:bg-orange-600">
                                        <Link href={route('addresses.create', { return: 'checkout' })}>Add address</Link>
                                    </Button>
                                </div>
                            ) : pickingAddress ? (
                                <ul className="mt-4 space-y-2">
                                    {addresses.map((address) => (
                                        <li key={address.id}>
                                            <button
                                                type="button"
                                                onClick={() => chooseAddress(address.id)}
                                                className={`w-full rounded-lg border p-3 text-left text-sm transition ${
                                                    selected.id === address.id
                                                        ? 'border-orange-400 bg-orange-50'
                                                        : 'border-gray-100 hover:border-orange-200'
                                                }`}
                                            >
                                                <p className="font-medium text-gray-900">
                                                    {address.full_name}
                                                    {address.is_default ? ' · Default' : ''}
                                                </p>
                                                <p className="mt-0.5 text-gray-600">{address.address_line}</p>
                                                <p className="text-gray-500">
                                                    {address.city}, {address.region} · {address.phone}
                                                </p>
                                            </button>
                                        </li>
                                    ))}
                                    <li>
                                        <Link
                                            href={route('addresses.create', { return: 'checkout' })}
                                            className="block rounded-lg border border-dashed border-orange-200 p-3 text-center text-sm font-medium text-orange-600 hover:bg-orange-50"
                                        >
                                            + Add new address
                                        </Link>
                                    </li>
                                </ul>
                            ) : (
                                <div className="mt-4 space-y-1 text-sm text-gray-700">
                                    <p>
                                        <span className="text-gray-500">Name:</span> {selected.full_name}
                                    </p>
                                    <p>
                                        <span className="text-gray-500">Zone:</span> {selected.region}
                                    </p>
                                    <p>
                                        <span className="text-gray-500">Town:</span> {selected.city}
                                    </p>
                                    <p>
                                        <span className="text-gray-500">Address:</span> {selected.address_line}
                                    </p>
                                    {selected.additional_details && (
                                        <p>
                                            <span className="text-gray-500">Details:</span> {selected.additional_details}
                                        </p>
                                    )}
                                    <p>
                                        <span className="text-gray-500">Mobile:</span> {selected.phone}
                                    </p>
                                    {selected.secondary_phone && (
                                        <p>
                                            <span className="text-gray-500">Secondary mobile:</span> {selected.secondary_phone}
                                        </p>
                                    )}
                                    <Button asChild size="sm" variant="outline" className="mt-3">
                                        <Link href={route('addresses.edit', { address: selected.id, return: 'checkout' })}>
                                            <Pencil className="mr-1 h-3.5 w-3.5" />
                                            Edit address
                                        </Link>
                                    </Button>
                                </div>
                            )}
                            <InputError message={errors.address_id} className="mt-2" />
                        </div>

                        <div className="rounded-xl bg-white p-6 shadow-sm">
                            <h2 className="font-semibold text-gray-900">Payment</h2>
                            <p className="mt-1 text-sm text-gray-500">
                                Pay with your wallet or via Paystack. Direct seller payments stay on the next step.
                            </p>
                            <div className="mt-3 space-y-2">
                                {hasMarketplaceOrders && (
                                    <label className={`flex cursor-pointer items-start gap-3 rounded-xl border p-3 hover:bg-gray-50 ${data.payment_method === 'wallet' ? 'border-orange-300 bg-orange-50/40' : ''} ${!canUseWallet ? 'opacity-70' : ''}`}>
                                        <PaymentMethodIcon method="wallet" />
                                        <div className="min-w-0 flex-1">
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
                                        <input
                                            type="radio"
                                            name="payment_method"
                                            value="wallet"
                                            checked={data.payment_method === 'wallet'}
                                            onChange={() => setData('payment_method', 'wallet')}
                                            disabled={!canUseWallet}
                                            className="mt-1"
                                        />
                                    </label>
                                )}
                                {([
                                    { value: 'momo' as const, label: 'Mobile Money', hint: 'MTN MoMo via Paystack' },
                                    { value: 'card' as const, label: 'Visa / Mastercard', hint: 'Pay securely via Paystack' },
                                ]).map((method) => (
                                    <label
                                        key={method.value}
                                        className={`flex cursor-pointer items-center gap-3 rounded-xl border p-3 hover:bg-gray-50 ${
                                            data.payment_method === method.value ? 'border-orange-300 bg-orange-50/40' : ''
                                        }`}
                                    >
                                        <PaymentMethodIcon method={method.value} />
                                        <div className="min-w-0 flex-1">
                                            <span className="font-medium text-gray-900">{method.label}</span>
                                            <p className="text-xs text-gray-500">{method.hint}</p>
                                        </div>
                                        <input
                                            type="radio"
                                            name="payment_method"
                                            value={method.value}
                                            checked={data.payment_method === method.value}
                                            onChange={() => setData('payment_method', method.value)}
                                        />
                                    </label>
                                ))}
                                <label
                                    className={`flex cursor-pointer items-start gap-3 rounded-xl border p-3 hover:bg-gray-50 ${
                                        data.payment_method === 'cash' ? 'border-orange-300 bg-orange-50/40' : ''
                                    }`}
                                >
                                    <PaymentMethodIcon method="cash" />
                                    <div className="min-w-0 flex-1">
                                        <span className="font-medium text-gray-900">Cash on Delivery</span>
                                        <p className="mt-0.5 text-xs text-gray-500">
                                            Pay when the seller brings the item.
                                        </p>
                                        <p className="mt-1 text-xs text-gray-500">
                                            Note: Select a seller near your area, or check store information if near you.
                                        </p>
                                        {sellerGroups.some((g) => g.store_slug) && (
                                            <div
                                                className="mt-2 flex flex-wrap gap-1.5"
                                                onClick={(e) => e.preventDefault()}
                                            >
                                                {sellerGroups
                                                    .filter((g) => g.store_slug)
                                                    .map((g) => (
                                                        <Link
                                                            key={g.seller_id}
                                                            href={route('store.show', g.store_slug!)}
                                                            className="inline-flex items-center gap-0.5 rounded-full bg-orange-50 px-2.5 py-1 text-[11px] font-semibold text-orange-600 ring-1 ring-orange-100 hover:bg-orange-100"
                                                        >
                                                            Visit {g.seller_name}
                                                            <ChevronRight className="h-3 w-3" />
                                                        </Link>
                                                    ))}
                                            </div>
                                        )}
                                    </div>
                                    <input
                                        type="radio"
                                        name="payment_method"
                                        value="cash"
                                        checked={data.payment_method === 'cash'}
                                        onChange={() => setData('payment_method', 'cash')}
                                        className="mt-1"
                                    />
                                </label>
                            </div>
                            <InputError message={errors.payment_method} />
                        </div>
                    </div>

                    <div className="space-y-4">
                        {sellerGroups.map((group) => {
                            const choice = data.seller_payments[String(group.seller_id)] ?? { channel: 'marketplace' };

                            return (
                                <div key={group.seller_id} className="rounded-xl bg-white p-6 shadow-sm">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0 flex-1">
                                            <p className="text-xs font-medium uppercase tracking-wide text-gray-400">
                                                Package · {sellerGroups.findIndex((g) => g.seller_id === group.seller_id) + 1} of {sellerGroups.length}
                                            </p>
                                            <h2 className="font-semibold text-gray-900">{group.seller_name}</h2>
                                            {data.payment_method === 'cash' && (
                                                <p className="mt-0.5 text-xs text-teal-700">Cash on delivery · pay this seller on arrival</p>
                                            )}
                                        </div>
                                        <div className="flex shrink-0 flex-col items-end gap-2">
                                            <span className="text-sm font-medium text-orange-500">{formatPrice(group.package_total)}</span>
                                            {group.store_slug && (
                                                <Link
                                                    href={route('store.show', group.store_slug)}
                                                    className="inline-flex items-center gap-0.5 rounded-full bg-orange-50 px-2.5 py-1.5 text-xs font-semibold text-orange-600 ring-1 ring-orange-100 hover:bg-orange-100"
                                                >
                                                    Visit
                                                    <ChevronRight className="h-3.5 w-3.5" />
                                                </Link>
                                            )}
                                        </div>
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
                                                    <label
                                                        className={`flex cursor-pointer items-center gap-3 rounded-lg border p-2.5 text-sm ${
                                                            choice.channel === 'marketplace' ? 'border-orange-300 bg-orange-50/40' : ''
                                                        }`}
                                                    >
                                                        <PaymentMethodIcon method="card" />
                                                        <span className="min-w-0 flex-1 font-medium text-gray-900">Pay via CityShop (secure)</span>
                                                        <input type="radio" checked={choice.channel === 'marketplace'} onChange={() => setSellerChannel(group.seller_id, 'marketplace')} />
                                                    </label>
                                                )}
                                                {group.accept_direct_payments && (
                                                    <label
                                                        className={`flex cursor-pointer items-center gap-3 rounded-lg border p-2.5 text-sm ${
                                                            choice.channel === 'direct' ? 'border-orange-300 bg-orange-50/40' : ''
                                                        }`}
                                                    >
                                                        {group.payment_methods.every((m) => isBankPaymentMethod(m)) && group.payment_methods.length > 0 ? (
                                                            <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-800 text-[10px] font-black text-white shadow-sm">
                                                                BANK
                                                            </span>
                                                        ) : (
                                                            <PaymentMethodIcon method="momo" />
                                                        )}
                                                        <span className="min-w-0 flex-1 font-medium text-gray-900">Pay seller directly</span>
                                                        <input type="radio" checked={choice.channel === 'direct'} onChange={() => setSellerChannel(group.seller_id, 'direct', group.payment_methods[0]?.id)} />
                                                    </label>
                                                )}
                                                {choice.channel === 'direct' && group.payment_methods.length > 0 && (() => {
                                                    const selectedId = choice.method_id ?? group.payment_methods[0]?.id;
                                                    const selectedMethod =
                                                        group.payment_methods.find((m) => m.id === selectedId)
                                                        ?? group.payment_methods[0];
                                                    const selectedIsBank = isBankPaymentMethod(selectedMethod);

                                                    return (
                                                        <div className="ml-1 space-y-3 border-l-2 border-sky-100 pl-3">
                                                            <SellerPaymentMethodPicker
                                                                methods={group.payment_methods}
                                                                selectedId={selectedId}
                                                                onSelect={(methodId) => setSellerChannel(group.seller_id, 'direct', methodId)}
                                                            />
                                                            {selectedMethod?.account_number && (
                                                                <DirectPaymentDetails
                                                                    accountNumber={selectedMethod.account_number}
                                                                    accountName={selectedMethod.account_name}
                                                                    network={selectedIsBank ? null : selectedMethod.network}
                                                                    isBank={selectedIsBank}
                                                                    bankName={selectedMethod.bank_name}
                                                                    hint={
                                                                        selectedIsBank
                                                                            ? 'After you continue, send the payment to this bank account. You’ll confirm on the next step.'
                                                                            : 'After you continue, send the payment to this number. You’ll confirm on the next step — no MoMo reference needed.'
                                                                    }
                                                                />
                                                            )}
                                                        </div>
                                                    );
                                                })()}
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
                            <Button
                                type="submit"
                                disabled={submitting || !selected}
                                className="mt-6 w-full bg-orange-500 py-6 hover:bg-orange-600"
                            >
                                {submitting && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
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
