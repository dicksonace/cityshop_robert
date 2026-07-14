import { Head } from '@inertiajs/react';

import ManualTopUpForm, {
    FundingAccount,
    TopUpHistoryItem,
} from '@/components/wallet/manual-top-up-form';
import SellerLayout from '@/layouts/seller-layout';

interface Props {
    settings: {
        enabled: boolean;
        instructions: string;
        accounts: FundingAccount[];
    };
    requests: TopUpHistoryItem[];
    walletRoute: string;
}

export default function SellerManualTopUp(props: Props) {
    return (
        <SellerLayout title="Manual top-up" active="wallet">
            <Head title="Manual top-up" />
            <ManualTopUpForm {...props} submitRoute={route('seller.wallet.manual-top-up.store')} showFlash />
        </SellerLayout>
    );
}
