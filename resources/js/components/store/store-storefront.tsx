import { Link, router } from '@inertiajs/react';
import { Globe, Mail, MapPin, MessageCircle, Phone, Share2, Star, Store, User, Verified } from 'lucide-react';
import { useMemo, useState } from 'react';

import StoreHero from '@/components/store/store-hero';
import InfiniteProductGrid from '@/components/shop/infinite-product-grid';
import MessageSellerButton from '@/components/shop/message-seller-button';
import ReportSellerButton from '@/components/shop/report-seller-button';
import ProductCard from '@/components/shop/product-card';
import SearchBox from '@/components/shop/search-box';
import SellerProfileSheet from '@/components/shop/seller-profile-sheet';
import { Paginated, Product, SellerProfile, productImageUrl } from '@/types/marketplace';
import {
    buttonRadiusClass,
    cardRadiusClass,
    productGridClass,
    StoreCustomizationSettings,
} from '@/types/store-customization';

interface StoreUser {
    name: string;
    email?: string;
    mobile?: string;
    whatsapp?: string;
    city?: string;
    region?: string;
    digital_address?: string;
    residential_address?: string;
}

interface StoreStorefrontProps {
    store: SellerProfile & {
        user_id: number;
        user?: StoreUser;
        store_description?: string | null;
        total_sales?: number;
        shop_photo?: string | null;
        business_address?: string | null;
        is_business_registered?: boolean;
        approved_at?: string | null;
    };
    customization: StoreCustomizationSettings;
    sections: string[];
    products: Paginated<Product>;
    featuredProducts?: Product[];
    onSaleProducts?: Product[];
    productCount: number;
    storeUrl: string;
    sellerReviewCount: number;
    promoActive?: boolean;
    previewMode?: boolean;
    currentUserId?: number;
    onAddToCart?: (productId: number) => void;
    search?: string;
}

export default function StoreStorefront({
    store,
    customization,
    sections,
    products,
    featuredProducts = [],
    onSaleProducts = [],
    productCount,
    storeUrl,
    sellerReviewCount,
    promoActive = false,
    previewMode = false,
    currentUserId,
    onAddToCart,
    search = '',
}: StoreStorefrontProps) {
    const [profileOpen, setProfileOpen] = useState(false);
    const storeName = store.business_name ?? store.store_name ?? 'Store';
    const theme = customization.theme;
    const branding = customization.branding;
    const display = customization.product_display;
    const isSearching = search.trim().length > 0;

    const productsResetKey = useMemo(
        () => `${store.slug}-${search}-${products.total}-${products.current_page}`,
        [store.slug, search, products.total, products.current_page],
    );

    const handleAddToCart = (productId: number) => {
        if (onAddToCart) {
            onAddToCart(productId);
            return;
        }
        if (!previewMode) {
            router.visit(route('login'));
        }
    };

    const description = branding.description || store.store_description || '';
    const slogan = branding.slogan || '';

    const renderProducts = (items: Product[], title: string) => (
        <section className="mb-10">
            <h2 className="text-lg font-bold" style={{ color: theme.text_color }}>{title}</h2>
            <div className={`mt-4 ${productGridClass(display)}`}>
                {items.map((product) => (
                    <div key={product.id} className={cardRadiusClass(display.border_radius)}>
                        <ProductCard
                            product={product}
                            onAddToCart={previewMode ? undefined : handleAddToCart}
                            variant={display.layout === 'list' ? 'list' : 'grid'}
                        />
                    </div>
                ))}
            </div>
        </section>
    );

    const sectionContent: Record<string, React.ReactNode> = {
        announcement: customization.announcement.enabled && customization.announcement.text ? (
            <div
                className="px-4 py-2 text-center text-sm font-medium"
                style={{ backgroundColor: customization.announcement.background_color, color: customization.announcement.text_color }}
            >
                {customization.announcement.text}
            </div>
        ) : null,
        hero: (
            <StoreHero
                hero={customization.hero}
                theme={theme}
                storeName={storeName}
                slogan={slogan}
                description={description}
                logoUrl={branding.store_logo}
                coverUrl={branding.cover_image}
                shopPhotoUrl={store.shop_photo}
            />
        ),
        promo: promoActive && customization.promo_banner.enabled ? (
            <div
                className="relative mx-auto max-w-7xl overflow-hidden px-4 py-6 sm:px-6"
                style={{ backgroundColor: customization.promo_banner.background_color, color: customization.promo_banner.text_color }}
            >
                <div className="flex items-center justify-between gap-4">
                    <p className="text-lg font-bold">{customization.promo_banner.text}</p>
                    {customization.promo_banner.image && (
                        <img src={productImageUrl(customization.promo_banner.image)} alt="" className="hidden h-16 w-16 rounded-lg object-cover sm:block" />
                    )}
                </div>
            </div>
        ) : null,
        featured: featuredProducts.length > 0 ? renderProducts(featuredProducts, 'Featured Products') : null,
        products: (
            <section>
                <div className="mb-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-lg font-bold" style={{ color: theme.text_color }}>
                            <Store className="mr-2 inline h-5 w-5" style={{ color: theme.secondary_color }} />
                            {isSearching ? `Results for “${search.trim()}”` : 'All Products'}
                        </h2>
                        <p className="text-sm text-gray-500">
                            {isSearching
                                ? `${products.total} ${products.total === 1 ? 'match' : 'matches'} in this store`
                                : `${products.total} items from this store`}
                        </p>
                    </div>
                    {!previewMode && (
                        <div className="flex flex-wrap items-center gap-3">
                            {isSearching && (
                                <Link
                                    href={route('store.show', store.slug)}
                                    className="text-sm text-gray-500 hover:underline"
                                >
                                    Clear search
                                </Link>
                            )}
                            <Link href={route('home')} className="text-sm hover:underline" style={{ color: theme.secondary_color }}>
                                ← Back to Shop
                            </Link>
                        </div>
                    )}
                </div>
                {products.data.length === 0 ? (
                    <div className="rounded-2xl border border-dashed border-gray-200 py-16 text-center text-gray-500">
                        {isSearching
                            ? `No products in this store match “${search.trim()}”.`
                            : 'This store has no products listed yet.'}
                    </div>
                ) : previewMode ? (
                    <div className={productGridClass(display)}>
                        {products.data.map((product) => (
                            <ProductCard
                                key={product.id}
                                product={product}
                                onAddToCart={undefined}
                                variant={display.layout === 'list' ? 'list' : 'grid'}
                            />
                        ))}
                    </div>
                ) : (
                    <InfiniteProductGrid
                        initial={products}
                        resetKey={productsResetKey}
                        onAddToCart={handleAddToCart}
                        variant={display.layout === 'list' ? 'list' : 'grid'}
                        gridClassName={productGridClass(display)}
                        replaceGridClass
                    />
                )}
            </section>
        ),
        about: customization.sections.enabled.about ? (
            <section className="mb-10 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 className="text-lg font-bold" style={{ color: theme.text_color }}>About {storeName}</h2>
                <p className="mt-3 text-sm leading-relaxed text-gray-600">{description || 'Welcome to our store on CityShop.'}</p>
                {branding.business_category && (
                    <p className="mt-2 text-sm text-gray-500">Category: {branding.business_category}</p>
                )}
            </section>
        ) : null,
        contact: customization.sections.enabled.contact ? (
            <section className="mb-10 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 className="text-lg font-bold" style={{ color: theme.text_color }}>Contact</h2>
                <ul className="mt-4 space-y-3 text-sm text-gray-600">
                    {store.user?.city && (
                        <li className="flex items-start gap-2">
                            <MapPin className="mt-0.5 h-4 w-4 shrink-0" />
                            {[store.user.city, store.user.region].filter(Boolean).join(', ')}
                            {store.user.digital_address && ` · ${store.user.digital_address}`}
                        </li>
                    )}
                    {store.user?.mobile && (
                        <li className="flex items-center gap-2">
                            <Phone className="h-4 w-4 shrink-0" />
                            <a href={`tel:${store.user.mobile}`} className="hover:underline">{store.user.mobile}</a>
                        </li>
                    )}
                    {store.user?.whatsapp && (
                        <li className="flex items-center gap-2">
                            <MessageCircle className="h-4 w-4 shrink-0 text-green-600" />
                            <a href={`https://wa.me/${store.user.whatsapp.replace(/\D/g, '')}`} target="_blank" rel="noreferrer" className="text-green-600 hover:underline">
                                WhatsApp
                            </a>
                        </li>
                    )}
                    {store.user?.email && (
                        <li className="flex items-center gap-2">
                            <Mail className="h-4 w-4 shrink-0" />
                            <a href={`mailto:${store.user.email}`} className="hover:underline">{store.user.email}</a>
                        </li>
                    )}
                    {branding.website && (
                        <li className="flex items-center gap-2">
                            <Globe className="h-4 w-4 shrink-0" />
                            <a href={branding.website} target="_blank" rel="noreferrer" className="hover:underline">{branding.website}</a>
                        </li>
                    )}
                </ul>
            </section>
        ) : null,
    };

    return (
        <div style={{ backgroundColor: theme.background_color, color: theme.text_color }}>
            {!previewMode && (
                <SellerProfileSheet
                    open={profileOpen}
                    onOpenChange={setProfileOpen}
                    sellerId={store.user_id}
                    store={store}
                    productCount={productCount}
                    sellerReviewCount={sellerReviewCount}
                    onMessageOpen={() => setProfileOpen(false)}
                />
            )}

            {sections.map((section) => {
                const content = sectionContent[section];
                if (!content) return null;

                if (section === 'announcement' || section === 'hero' || section === 'promo') {
                    return <div key={section}>{content}</div>;
                }

                return null;
            })}

            {/* Store actions bar under hero for static/slideshow */}
            <div className="border-b border-gray-100 bg-white/80">
                <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6">
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 sm:text-sm">
                        <span className="flex items-center gap-1">
                            <Star className="h-4 w-4 fill-amber-400 text-amber-400" />
                            {sellerReviewCount > 0 ? `${Number(store.rating).toFixed(1)} · ${sellerReviewCount} reviews` : 'No reviews yet'}
                        </span>
                        <span>{store.total_sales ?? 0} sales</span>
                        <span>{productCount} products</span>
                        <Verified className="h-4 w-4 text-blue-500" />
                    </div>
                    {!previewMode && (
                        <div className="flex flex-wrap gap-2">
                            <button type="button" onClick={() => setProfileOpen(true)} className={`inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium text-white sm:text-sm ${buttonRadiusClass(theme.button_style)}`} style={{ backgroundColor: theme.primary_color }}>
                                <User className="h-4 w-4" /> Profile
                            </button>
                            <button type="button" onClick={() => navigator.clipboard.writeText(storeUrl)} className={`inline-flex items-center gap-1.5 border px-3 py-2 text-xs font-medium sm:text-sm ${buttonRadiusClass(theme.button_style)}`} style={{ borderColor: theme.primary_color, color: theme.primary_color }}>
                                <Share2 className="h-4 w-4" /> Copy Link
                            </button>
                            {currentUserId !== store.user_id && (
                                <>
                                    <MessageSellerButton sellerId={store.user_id} variant="banner" label="Message" className={buttonRadiusClass(theme.button_style)} />
                                    <ReportSellerButton
                                        sellerId={store.user_id}
                                        storeName={storeName}
                                        className={`inline-flex items-center gap-1.5 border border-red-200 bg-white px-3 py-2 text-xs font-medium text-red-600 hover:bg-red-50 sm:text-sm ${buttonRadiusClass(theme.button_style)}`}
                                    />
                                </>
                            )}
                        </div>
                    )}
                </div>
            </div>

            {!previewMode && (
                <div className="border-b border-orange-50 bg-white">
                    <div className="mx-auto max-w-7xl px-4 py-4 sm:px-6">
                        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Search in {storeName}
                        </p>
                        <SearchBox
                            initialQuery={search}
                            target="store"
                            sellerId={store.user_id}
                            storeSlug={store.slug}
                            storeName={storeName}
                            className="w-full"
                        />
                    </div>
                </div>
            )}

            <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
                {sections.map((section) => {
                    if (['announcement', 'hero', 'promo'].includes(section)) return null;
                    if (isSearching && section === 'featured') return null;
                    const content = sectionContent[section];
                    return content ? <div key={section}>{content}</div> : null;
                })}
            </div>
        </div>
    );
}
