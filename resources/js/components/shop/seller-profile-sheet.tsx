import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import SellerProfilePanel from '@/components/shop/seller-profile-panel';
import { SellerProfile } from '@/types/marketplace';

interface SellerProfileSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
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
    onMessageOpen?: () => void;
}

export default function SellerProfileSheet({
    open,
    onOpenChange,
    sellerId,
    store,
    productCount,
    sellerReviewCount = 0,
    onMessageOpen,
}: SellerProfileSheetProps) {
    const storeName = store.business_name ?? store.store_name ?? 'Store';

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="right"
                className="flex h-svh w-full max-w-full flex-col gap-0 border-l p-0 sm:max-w-md md:max-w-lg [&>button]:z-20 [&>button]:rounded-full [&>button]:bg-white/90 [&>button]:p-2 [&>button]:shadow-md"
            >
                <SheetHeader className="sr-only">
                    <SheetTitle>{storeName} profile</SheetTitle>
                    <SheetDescription>Seller store details and contact options</SheetDescription>
                </SheetHeader>

                <div className="flex h-full flex-col overflow-y-auto pt-14 pb-8">
                    <SellerProfilePanel
                        sellerId={sellerId}
                        store={store}
                        productCount={productCount}
                        sellerReviewCount={sellerReviewCount}
                        onMessageOpen={onMessageOpen}
                        className="h-full"
                    />
                </div>
            </SheetContent>
        </Sheet>
    );
}
