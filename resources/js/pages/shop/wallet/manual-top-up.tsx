import { Head } from '@inertiajs/react';

import ManualTopUpForm, {
    FundingAccount,
    TopUpHistoryItem,
} from '@/components/wallet/manual-top-up-form';
import ShopLayout from '@/layouts/shop-layout';

interface Props {
    settings: {
        enabled: boolean;
        instructions: string;
        accounts: FundingAccount[];
    };
    requests: TopUpHistoryItem[];
    walletRoute: string;
}

export default function BuyerManualTopUp(props: Props) {
    return (
        <ShopLayout>
            <Head title="Manual top-up" />
            <div className="px-4 py-8">
                <ManualTopUpForm {...props} submitRoute={route('wallet.manual-top-up.store')} />
            </div>
        </ShopLayout>
    );
}
