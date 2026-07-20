import { Head, router, usePage } from '@inertiajs/react';

import StoreStorefront from '@/components/store/store-storefront';
import ShopLayout from '@/layouts/shop-layout';
import { addProductToCart } from '@/lib/shop-actions';
import { Paginated, Product, SellerProfile } from '@/types/marketplace';
import { StoreCustomizationSettings } from '@/types/store-customization';
import { SharedData } from '@/types';

interface StorePageProps {
    store: SellerProfile & {
        user_id: number;
        user?: { name: string; city?: string; region?: string; email?: string; mobile?: string; whatsapp?: string; digital_address?: string; residential_address?: string };
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
    featuredProducts: Product[];
    onSaleProducts: Product[];
    productCount: number;
    storeUrl: string;
    sellerReviewCount: number;
    promoActive: boolean;
    search: string;
}

export default function StorePage({
    store,
    customization,
    sections,
    products,
    featuredProducts,
    onSaleProducts,
    productCount,
    storeUrl,
    sellerReviewCount,
    promoActive,
    search,
}: StorePageProps) {
    const { auth } = usePage<SharedData>().props;
    const storeName = store.business_name ?? store.store_name ?? 'Store';

    const handleAddToCart = (productId: number) => {
        if (!auth.user) {
            router.visit(route('login'));
            return;
        }
        addProductToCart(productId);
    };

    return (
        <ShopLayout>
            <Head title={search ? `${search} · ${storeName}` : storeName} />
            <StoreStorefront
                store={store}
                customization={customization}
                sections={sections}
                products={products}
                featuredProducts={featuredProducts}
                onSaleProducts={onSaleProducts}
                productCount={productCount}
                storeUrl={storeUrl}
                sellerReviewCount={sellerReviewCount}
                promoActive={promoActive}
                currentUserId={auth.user?.id}
                onAddToCart={handleAddToCart}
                search={search}
            />
        </ShopLayout>
    );
}
