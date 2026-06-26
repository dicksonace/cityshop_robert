import { Link } from '@inertiajs/react';
import { Mail, MapPin, MessageCircle, Phone, Store } from 'lucide-react';

import MessageSellerButton from '@/components/shop/message-seller-button';
import { storePageUrl } from '@/components/shop/seller-store-link';
import { productImageUrl, SellerProfile } from '@/types/marketplace';

export interface ProductSeller {
    id: number;
    name: string;
    email?: string;
    mobile?: string;
    whatsapp?: string;
    region?: string;
    city?: string;
    digital_address?: string;
    residential_address?: string;
    seller_profile?: SellerProfile;
}

interface ProductSellerInfoProps {
    seller: ProductSeller;
    productId: number;
    showChatButton?: boolean;
    currentUserId?: number;
}

function whatsAppUrl(number: string): string {
    return `https://wa.me/${number.replace(/\D/g, '')}`;
}

export default function ProductSellerInfo({ seller, productId, showChatButton = true, currentUserId }: ProductSellerInfoProps) {
    const profile = seller.seller_profile;
    if (!profile) return null;

    const storeName = profile.business_name ?? profile.store_name ?? seller.name;
    const location = [seller.city, seller.region].filter(Boolean).join(', ');
    const shopAddress = profile.business_address ?? seller.residential_address;
    const isOwnProduct = currentUserId === seller.id;

    return (
        <div className="mt-4 rounded-xl border border-gray-100 bg-gray-50 p-4">
            <h3 className="text-sm font-semibold text-gray-900">Seller information</h3>

            <div className="mt-3 flex items-start gap-3">
                <div className="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-gradient-to-br from-blue-500 to-orange-500 text-lg font-bold text-white">
                    {profile.shop_photo ? (
                        <img src={productImageUrl(profile.shop_photo)} alt="" className="h-full w-full object-cover" />
                    ) : (
                        storeName.charAt(0).toUpperCase()
                    )}
                </div>
                <div className="min-w-0 flex-1">
                    {profile.slug ? (
                        <Link href={storePageUrl(profile.slug)} className="font-semibold text-gray-900 hover:text-orange-500">
                            {storeName}
                        </Link>
                    ) : (
                        <p className="font-semibold text-gray-900">{storeName}</p>
                    )}
                    <p className="text-sm text-gray-500">Contact: {seller.name}</p>
                </div>
            </div>

            <dl className="mt-4 space-y-2.5 text-sm">
                {location && (
                    <div className="flex items-start gap-2.5">
                        <MapPin className="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                        <div>
                            <dt className="font-medium text-gray-800">Location</dt>
                            <dd className="text-gray-600">{location}</dd>
                            {seller.digital_address && (
                                <dd className="text-gray-500">Digital address: {seller.digital_address}</dd>
                            )}
                        </div>
                    </div>
                )}

                {shopAddress && (
                    <div className="flex items-start gap-2.5">
                        <Store className="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                        <div>
                            <dt className="font-medium text-gray-800">Shop address</dt>
                            <dd className="text-gray-600">{shopAddress}</dd>
                        </div>
                    </div>
                )}

                {seller.mobile && (
                    <div className="flex items-start gap-2.5">
                        <Phone className="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                        <div>
                            <dt className="font-medium text-gray-800">Phone</dt>
                            <dd>
                                <a href={`tel:${seller.mobile.replace(/\s/g, '')}`} className="text-orange-500 hover:underline">
                                    {seller.mobile}
                                </a>
                            </dd>
                        </div>
                    </div>
                )}

                {seller.whatsapp && (
                    <div className="flex items-start gap-2.5">
                        <MessageCircle className="mt-0.5 h-4 w-4 shrink-0 text-green-500" />
                        <div>
                            <dt className="font-medium text-gray-800">WhatsApp</dt>
                            <dd>
                                <a
                                    href={whatsAppUrl(seller.whatsapp)}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-green-600 hover:underline"
                                >
                                    {seller.whatsapp}
                                </a>
                            </dd>
                        </div>
                    </div>
                )}

                {seller.email && (
                    <div className="flex items-start gap-2.5">
                        <Mail className="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                        <div>
                            <dt className="font-medium text-gray-800">Email</dt>
                            <dd>
                                <a href={`mailto:${seller.email}`} className="text-orange-500 hover:underline">
                                    {seller.email}
                                </a>
                            </dd>
                        </div>
                    </div>
                )}
            </dl>

            {showChatButton && !isOwnProduct && (
                <MessageSellerButton
                    sellerId={seller.id}
                    productId={productId}
                    label="Chat with Seller"
                    className="mt-4 w-full py-5 text-sm sm:py-6 sm:text-base"
                />
            )}
        </div>
    );
}
