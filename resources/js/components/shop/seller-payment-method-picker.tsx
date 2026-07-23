import MomoNetworkLogo from '@/components/wallet/momo-network-logo';
import { BankPaymentLogo, bankPaymentTitle, isBankPaymentMethod } from '@/lib/payment-method-display';
import { momoNetworkLabel, normalizeMomoNetworkId } from '@/lib/momo-networks';
import { cn } from '@/lib/utils';

export type PickerPaymentMethod = {
    id: number;
    type: string;
    label: string | null;
    account_name: string;
    account_number: string | null;
    network: string | null;
    bank_name: string | null;
    display_label?: string;
};

type SellerPaymentMethodPickerProps = {
    methods: PickerPaymentMethod[];
    selectedId: number | null | undefined;
    onSelect: (methodId: number) => void;
    className?: string;
};

function methodTitle(method: PickerPaymentMethod): string {
    if (isBankPaymentMethod(method)) {
        return bankPaymentTitle(method);
    }
    const networkId = normalizeMomoNetworkId(method.network);
    return networkId ? momoNetworkLabel(networkId) : 'Mobile Money';
}

function methodSubtitle(method: PickerPaymentMethod): string {
    const number = method.account_number?.trim() || '—';
    const name = method.account_name?.trim();
    return name ? `${number} · ${name}` : number;
}

export default function SellerPaymentMethodPicker({
    methods,
    selectedId,
    onSelect,
    className,
}: SellerPaymentMethodPickerProps) {
    if (methods.length === 0) {
        return null;
    }

    return (
        <div className={cn('space-y-2', className)}>
            <p className="text-xs font-medium text-gray-500">
                {methods.length > 1 ? 'Choose where to pay' : 'Pay to this account'}
            </p>
            <div className="space-y-2">
                {methods.map((method) => {
                    const selected = method.id === selectedId;
                    const bank = isBankPaymentMethod(method);
                    const title = methodTitle(method);

                    return (
                        <button
                            key={method.id}
                            type="button"
                            onClick={() => onSelect(method.id)}
                            className={cn(
                                'flex w-full items-center gap-3 rounded-xl border px-3 py-2.5 text-left transition',
                                selected
                                    ? 'border-sky-400 bg-sky-50/70 ring-1 ring-sky-300/60'
                                    : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50',
                            )}
                        >
                            {bank ? (
                                <BankPaymentLogo bankName={method.bank_name || title} />
                            ) : (
                                <MomoNetworkLogo network={method.network} size="md" />
                            )}
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-semibold text-gray-900">{title}</p>
                                <p className="mt-0.5 truncate text-xs text-gray-500">{methodSubtitle(method)}</p>
                            </div>
                            <span
                                className={cn(
                                    'h-4 w-4 shrink-0 rounded-full border-2',
                                    selected ? 'border-sky-500 bg-sky-500 shadow-[inset_0_0_0_2px_#fff]' : 'border-gray-300',
                                )}
                                aria-hidden
                            />
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
