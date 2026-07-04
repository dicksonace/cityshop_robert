import { Link } from '@inertiajs/react';

import MessageSellerButton from '@/components/shop/message-seller-button';
import ReportSellerButton from '@/components/shop/report-seller-button';
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

export default function ProductSellerInfo({ seller, productId, showChatButton = true, currentUserId }: ProductSellerInfoProps) {
    const profile = seller.seller_profile;
    if (!profile) return null;

    const storeName = profile.business_name ?? profile.store_name ?? seller.name;
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
                </div>
            </div>

            {showChatButton && !isOwnProduct && (
                <MessageSellerButton
                    sellerId={seller.id}
                    productId={productId}
                    label="Chat with Seller"
                    className="mt-4 w-full py-5 text-sm sm:py-6 sm:text-base"
                />
            )}

            {!isOwnProduct && (
                <div className="mt-3 flex justify-end">
                    <ReportSellerButton sellerId={seller.id} productId={productId} storeName={storeName} />
                </div>
            )}
        </div>
    );
}
