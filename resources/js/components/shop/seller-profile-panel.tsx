import { Building2, Calendar, Mail, MapPin, MessageCircle, Package, Phone, ShieldCheck, Star, Store, Verified } from 'lucide-react';

import MessageSellerButton from '@/components/shop/message-seller-button';
import { productImageUrl, SellerProfile } from '@/types/marketplace';

interface SellerProfilePanelProps {
    sellerId: number;
    store: SellerProfile & {
        user?: {
            name?: string;
            email?: string;
            mobile?: string;
            whatsapp?: string;
            city?: string;
            region?: string;
            digital_address?: string;
            residential_address?: string;
        };
        store_description?: string | null;
        total_sales?: number;
        shop_photo?: string | null;
        business_address?: string | null;
        is_business_registered?: boolean;
        approved_at?: string | null;
    };
    productCount: number;
    sellerReviewCount?: number;
    id?: string;
    className?: string;
    onMessageOpen?: () => void;
}

function formatMemberSince(value?: string | null): string | null {
    if (!value) return null;
    return new Date(value).toLocaleDateString('en-GH', { month: 'long', year: 'numeric' });
}

export default function SellerProfilePanel({ sellerId, store, productCount, sellerReviewCount = 0, id = 'seller-profile', className, onMessageOpen }: SellerProfilePanelProps) {
    const storeName = store.business_name ?? store.store_name ?? 'Store';
    const memberSince = formatMemberSince(store.approved_at);
    const location = [store.user?.city, store.user?.region].filter(Boolean).join(', ');

    return (
        <section id={id} className={className}>
            <div className="overflow-hidden bg-white">
                {store.shop_photo && (
                    <div className="aspect-[16/7] overflow-hidden bg-gray-100">
                        <img
                            src={productImageUrl(store.shop_photo)}
                            alt={`${storeName} shop`}
                            className="h-full w-full object-cover"
                        />
                    </div>
                )}

                <div className="p-5">
                    <div className="flex items-start gap-4">
                        <div className="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-gradient-to-br from-blue-500 to-orange-500 text-xl font-bold text-white">
                            {store.shop_photo ? (
                                <img src={productImageUrl(store.shop_photo)} alt="" className="h-full w-full object-cover" />
                            ) : (
                                storeName.charAt(0).toUpperCase()
                            )}
                        </div>
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <h2 className="text-lg font-bold text-gray-900">{storeName}</h2>
                                <Verified className="h-4 w-4 shrink-0 text-blue-500" aria-label="Verified seller" />
                            </div>
                            {store.user?.name && (
                                <p className="text-sm text-gray-500">Run by {store.user.name}</p>
                            )}
                        </div>
                    </div>

                    <div className="mt-4 flex flex-wrap gap-2">
                        <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">
                            <Star className="h-3.5 w-3.5 fill-amber-400 text-amber-400" />
                            {sellerReviewCount > 0 ? (
                                <>{Number(store.rating).toFixed(1)} rating · {sellerReviewCount} review{sellerReviewCount !== 1 ? 's' : ''}</>
                            ) : (
                                'No reviews yet'
                            )}
                        </span>
                        {store.is_business_registered && (
                            <span className="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">
                                <Building2 className="h-3.5 w-3.5" />
                                Registered business
                            </span>
                        )}
                        <span className="inline-flex items-center gap-1 rounded-full bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700">
                            <ShieldCheck className="h-3.5 w-3.5" />
                            Verified seller
                        </span>
                    </div>

                    <dl className="mt-5 space-y-3 border-t border-gray-100 pt-5 text-sm">
                        <div className="flex items-start gap-3">
                            <Package className="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                            <div>
                                <dt className="font-medium text-gray-900">{store.total_sales ?? 0} sales</dt>
                                <dd className="text-gray-500">{productCount} products listed</dd>
                            </div>
                        </div>

                        {location && (
                            <div className="flex items-start gap-3">
                                <MapPin className="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                                <div>
                                    <dt className="font-medium text-gray-900">Location</dt>
                                    <dd className="text-gray-500">{location}</dd>
                                </div>
                            </div>
                        )}

                        {store.business_address && (
                            <div className="flex items-start gap-3">
                                <Store className="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                                <div>
                                    <dt className="font-medium text-gray-900">Shop address</dt>
                                    <dd className="text-gray-500">{store.business_address}</dd>
                                </div>
                            </div>
                        )}

                        {store.user?.digital_address && (
                            <div className="flex items-start gap-3">
                                <MapPin className="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                                <div>
                                    <dt className="font-medium text-gray-900">Digital address</dt>
                                    <dd className="text-gray-500">{store.user.digital_address}</dd>
                                </div>
                            </div>
                        )}

                        {store.user?.residential_address && !store.business_address && (
                            <div className="flex items-start gap-3">
                                <Store className="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                                <div>
                                    <dt className="font-medium text-gray-900">Address</dt>
                                    <dd className="text-gray-500">{store.user.residential_address}</dd>
                                </div>
                            </div>
                        )}

                        {store.user?.mobile && (
                            <div className="flex items-start gap-3">
                                <Phone className="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                                <div>
                                    <dt className="font-medium text-gray-900">Phone</dt>
                                    <dd>
                                        <a href={`tel:${store.user.mobile.replace(/\s/g, '')}`} className="text-orange-500 hover:underline">
                                            {store.user.mobile}
                                        </a>
                                    </dd>
                                </div>
                            </div>
                        )}

                        {store.user?.whatsapp && (
                            <div className="flex items-start gap-3">
                                <MessageCircle className="mt-0.5 h-4 w-4 shrink-0 text-green-500" />
                                <div>
                                    <dt className="font-medium text-gray-900">WhatsApp</dt>
                                    <dd>
                                        <a
                                            href={`https://wa.me/${store.user.whatsapp.replace(/\D/g, '')}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="text-green-600 hover:underline"
                                        >
                                            {store.user.whatsapp}
                                        </a>
                                    </dd>
                                </div>
                            </div>
                        )}

                        {store.user?.email && (
                            <div className="flex items-start gap-3">
                                <Mail className="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                                <div>
                                    <dt className="font-medium text-gray-900">Email</dt>
                                    <dd>
                                        <a href={`mailto:${store.user.email}`} className="text-orange-500 hover:underline">
                                            {store.user.email}
                                        </a>
                                    </dd>
                                </div>
                            </div>
                        )}

                        {memberSince && (
                            <div className="flex items-start gap-3">
                                <Calendar className="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                                <div>
                                    <dt className="font-medium text-gray-900">Member since</dt>
                                    <dd className="text-gray-500">{memberSince}</dd>
                                </div>
                            </div>
                        )}
                    </dl>

                    {store.store_description && (
                        <div className="mt-5 border-t border-gray-100 pt-5">
                            <h3 className="text-sm font-semibold text-gray-900">About this store</h3>
                            <p className="mt-2 text-sm leading-relaxed text-gray-600">{store.store_description}</p>
                        </div>
                    )}

                    <MessageSellerButton sellerId={sellerId} onOpen={onMessageOpen} className="mt-5 w-full py-2.5" label="Message Seller" />
                </div>
            </div>
        </section>
    );
}
