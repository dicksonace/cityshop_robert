import { Head, Link, router, usePage } from '@inertiajs/react';
import { MapPin, Share2, Star, Store, User, Verified } from 'lucide-react';
import { useState } from 'react';

import ProductCard from '@/components/shop/product-card';
import MessageSellerButton from '@/components/shop/message-seller-button';
import SellerProfileSheet from '@/components/shop/seller-profile-sheet';
import ShopLayout from '@/layouts/shop-layout';
import { useToast } from '@/contexts/toast-context';
import { addProductToCart, copyToClipboard } from '@/lib/shop-actions';
import { Paginated, Product, SellerProfile, productImageUrl } from '@/types/marketplace';
import { SharedData } from '@/types';

interface StorePageProps {
    store: SellerProfile & {
        user_id: number;
        user?: { name: string; city?: string; region?: string };
        store_description?: string | null;
        total_sales?: number;
        shop_photo?: string | null;
        business_address?: string | null;
        is_business_registered?: boolean;
        approved_at?: string | null;
    };
    products: Paginated<Product>;
    productCount: number;
    storeUrl: string;
    sellerReviewCount: number;
}

export default function StorePage({ store, products, productCount, storeUrl, sellerReviewCount }: StorePageProps) {
    const { auth } = usePage<SharedData>().props;
    const toast = useToast();
    const [profileOpen, setProfileOpen] = useState(false);
    const storeName = store.business_name ?? store.store_name ?? 'Store';

    const handleAddToCart = (productId: number) => {
        if (!auth.user) {
            router.visit(route('login'));
            return;
        }
        addProductToCart(productId);
    };

    const shareWhatsApp = () => {
        const text = encodeURIComponent(`Check out ${storeName} on CityShop! ${storeUrl}`);
        window.open(`https://wa.me/?text=${text}`, '_blank');
        toast.info('Opening WhatsApp to share...');
    };

    const copyLink = async () => {
        try {
            await copyToClipboard(storeUrl);
            toast.success('Store link copied to clipboard!');
        } catch {
            toast.error('Could not copy link. Please try again.');
        }
    };

    return (
        <ShopLayout>
            <Head title={storeName} />

            <SellerProfileSheet
                open={profileOpen}
                onOpenChange={setProfileOpen}
                sellerId={store.user_id}
                store={store}
                productCount={productCount}
                sellerReviewCount={sellerReviewCount}
                onMessageOpen={() => setProfileOpen(false)}
            />

            {/* Store banner */}
            <div className="bg-gradient-to-r from-slate-900 via-blue-900 to-orange-900">
                <div className="mx-auto max-w-7xl px-3 py-6 sm:px-4 sm:py-10">
                    <div className="flex flex-col gap-5 sm:gap-6 md:flex-row md:items-center md:justify-between">
                        <div className="flex items-center gap-3 sm:gap-5">
                            <div className="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-white/10 text-2xl font-bold text-white backdrop-blur-sm ring-2 ring-white/20 sm:h-20 sm:w-20 sm:text-3xl">
                                {store.shop_photo ? (
                                    <img src={productImageUrl(store.shop_photo)} alt="" className="h-full w-full object-cover" />
                                ) : (
                                    storeName.charAt(0).toUpperCase()
                                )}
                            </div>
                            <div className="min-w-0 text-white">
                                <div className="flex items-center gap-2">
                                    <h1 className="truncate text-xl font-bold sm:text-2xl md:text-3xl">{storeName}</h1>
                                    <Verified className="h-4 w-4 shrink-0 text-blue-300 sm:h-5 sm:w-5" />
                                </div>
                                <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-white/70 sm:text-sm">
                                    <span className="flex items-center gap-1">
                                        <Star className="h-4 w-4 fill-amber-400 text-amber-400" />
                                        {sellerReviewCount > 0
                                            ? `${Number(store.rating).toFixed(1)} rating · ${sellerReviewCount} review${sellerReviewCount !== 1 ? 's' : ''}`
                                            : 'No reviews yet'}
                                    </span>
                                    <span>{store.total_sales ?? 0} sales</span>
                                    <span>{productCount} products</span>
                                    {store.user?.city && (
                                        <span className="flex items-center gap-1">
                                            <MapPin className="h-3.5 w-3.5" />
                                            {store.user.city}, {store.user.region}
                                        </span>
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                            <button
                                type="button"
                                onClick={() => setProfileOpen(true)}
                                className="inline-flex items-center justify-center gap-1.5 rounded-xl bg-white/10 px-3 py-2 text-xs font-medium text-white backdrop-blur-sm transition-colors hover:bg-white/20 sm:gap-2 sm:px-4 sm:py-2.5 sm:text-sm"
                            >
                                <User className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                <span className="truncate">Profile</span>
                            </button>
                            <button
                                type="button"
                                onClick={copyLink}
                                className="inline-flex items-center justify-center gap-1.5 rounded-xl bg-white/10 px-3 py-2 text-xs font-medium text-white backdrop-blur-sm transition-colors hover:bg-white/20 sm:gap-2 sm:px-4 sm:py-2.5 sm:text-sm"
                            >
                                <Share2 className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                <span className="truncate">Copy Link</span>
                            </button>
                            {auth.user && auth.user.id !== store.user_id && (
                                <MessageSellerButton sellerId={store.user_id} variant="banner" label="Message" className="col-span-2 sm:col-span-1" />
                            )}
                            <button
                                type="button"
                                onClick={shareWhatsApp}
                                className="col-span-2 inline-flex items-center justify-center gap-1.5 rounded-xl bg-green-500 px-3 py-2 text-xs font-medium text-white transition-colors hover:bg-green-600 sm:col-span-1 sm:gap-2 sm:px-4 sm:py-2.5 sm:text-sm"
                            >
                                WhatsApp
                            </button>
                        </div>
                    </div>

                    {store.store_description && (
                        <p className="mt-4 max-w-2xl text-sm text-white/80 line-clamp-2 md:line-clamp-none">{store.store_description}</p>
                    )}
                </div>
            </div>

            <div className="mx-auto max-w-7xl px-3 py-6 sm:px-4 sm:py-8">
                <div className="mb-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-lg font-bold text-gray-900">
                            <Store className="mr-2 inline h-5 w-5 text-orange-500" />
                            All Products
                        </h2>
                        <p className="text-sm text-gray-500">{products.total} items from this store</p>
                    </div>
                    <Link href={route('home')} className="text-sm text-orange-500 hover:underline">
                        ← Back to Shop
                    </Link>
                </div>

                {products.data.length === 0 ? (
                    <div className="rounded-2xl border border-dashed border-gray-200 py-16 text-center text-gray-500">
                        This store has no products listed yet.
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-3 min-[480px]:grid-cols-2 sm:gap-4 md:grid-cols-3 lg:grid-cols-4">
                        {products.data.map((product) => (
                            <ProductCard key={product.id} product={product} onAddToCart={handleAddToCart} />
                        ))}
                    </div>
                )}

                {products.last_page > 1 && (
                    <div className="mt-8 overflow-x-auto pb-2">
                        <div className="flex min-w-min justify-center gap-1 px-1">
                        {products.links.map((link, i) =>
                            link.url ? (
                                <Link
                                    key={i}
                                    href={link.url}
                                    className={`min-w-[2.5rem] rounded-xl px-3 py-2 text-sm font-medium ${
                                        link.active ? 'bg-orange-500 text-white' : 'bg-white text-gray-600 shadow-sm hover:bg-gray-50'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : null,
                        )}
                        </div>
                    </div>
                )}
            </div>
        </ShopLayout>
    );
}
