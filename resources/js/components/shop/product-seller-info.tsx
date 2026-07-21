import { Link } from '@inertiajs/react';
import { ChevronRight, Star } from 'lucide-react';

import MessageSellerButton from '@/components/shop/message-seller-button';
import ReportSellerButton from '@/components/shop/report-seller-button';
import { storePageUrl } from '@/components/shop/seller-store-link';
import { formatCompactCount, productImageUrl, SellerProfile } from '@/types/marketplace';

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
    const storeUrl = profile.slug ? storePageUrl(profile.slug) : null;
    const rating = Number(profile.rating) || 0;
    const sales = Number(profile.total_sales) || 0;

    const storeRow = (
        <div className="flex min-w-0 items-center gap-3">
            <div className="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-gradient-to-br from-blue-500 to-orange-500 text-lg font-bold text-white">
                {profile.shop_photo ? (
                    <img src={productImageUrl(profile.shop_photo)} alt="" className="h-full w-full object-cover" />
                ) : (
                    storeName.charAt(0).toUpperCase()
                )}
            </div>

            <div className="min-w-0 flex-1">
                <p className="truncate font-semibold text-gray-900 group-hover:text-orange-500">{storeName}</p>
                <div className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-gray-500">
                    {rating > 0 ? (
                        <span className="inline-flex items-center gap-0.5">
                            <Star className="h-3 w-3 fill-amber-400 text-amber-400" />
                            <span className="font-medium text-gray-700">{rating.toFixed(1)}</span>
                        </span>
                    ) : (
                        <span>New seller</span>
                    )}
                    {sales > 0 && (
                        <>
                            <span className="text-gray-300" aria-hidden>
                                ·
                            </span>
                            <span>{formatCompactCount(sales)} sales</span>
                        </>
                    )}
                </div>
            </div>

            {storeUrl && (
                <span className="inline-flex shrink-0 items-center gap-0.5 rounded-full bg-orange-50 px-2.5 py-1.5 text-xs font-semibold text-orange-600 ring-1 ring-orange-100 group-hover:bg-orange-100">
                    Visit
                    <ChevronRight className="h-3.5 w-3.5" />
                </span>
            )}
        </div>
    );

    return (
        <div className="mt-4 rounded-xl border border-gray-100 bg-gray-50 p-4">
            <h3 className="text-sm font-semibold text-gray-900">Seller information</h3>

            <div className="mt-3">
                {storeUrl ? (
                    <Link href={storeUrl} className="group block rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-orange-400">
                        {storeRow}
                    </Link>
                ) : (
                    storeRow
                )}
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
