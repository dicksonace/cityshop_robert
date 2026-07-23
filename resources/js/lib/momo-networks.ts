export interface MomoNetwork {
    id: string;
    label: string;
    shortLabel: string;
    /** Field label shown above the account / till number */
    numberLabel: string;
    accent: string;
    selectedClass: string;
    badgeClass: string;
    logoClass: string;
}

/** Ghana mobile money networks — MTN MoMo listed first as the default. */
export const MOMO_NETWORKS: MomoNetwork[] = [
    {
        id: 'mtn',
        label: 'MTN Mobile Money',
        shortLabel: 'MTN MoMo',
        numberLabel: 'MoMo number',
        accent: 'text-yellow-700',
        selectedClass: 'border-yellow-500 bg-yellow-50 ring-2 ring-yellow-400/60',
        badgeClass: 'bg-yellow-100 text-yellow-800',
        logoClass: 'from-yellow-400 to-amber-500 text-gray-900',
    },
    {
        id: 'telecel',
        label: 'Telecel Cash',
        shortLabel: 'Telecel',
        numberLabel: 'Till number',
        accent: 'text-red-700',
        selectedClass: 'border-red-500 bg-red-50 ring-2 ring-red-400/60',
        badgeClass: 'bg-red-100 text-red-800',
        logoClass: 'from-red-600 to-rose-700 text-white',
    },
    {
        id: 'airteltigo',
        label: 'AirtelTigo Money',
        shortLabel: 'AirtelTigo',
        numberLabel: 'MoMo number',
        accent: 'text-blue-700',
        selectedClass: 'border-blue-500 bg-blue-50 ring-2 ring-blue-400/60',
        badgeClass: 'bg-blue-100 text-blue-800',
        logoClass: 'from-red-500 to-sky-600 text-white',
    },
];

export const MOMO_NETWORK_LABELS: Record<string, string> = Object.fromEntries(
    MOMO_NETWORKS.map((network) => [network.id, network.label]),
);

export function momoNetworkLabel(network: string): string {
    return MOMO_NETWORK_LABELS[network] ?? network.replace(/_/g, ' ');
}

export function momoNetworkMeta(network: string): MomoNetwork | undefined {
    return MOMO_NETWORKS.find((item) => item.id === network);
}

/** Normalize free-text network names to mtn|telecel|airteltigo. */
export function normalizeMomoNetworkId(network?: string | null): string | null {
    if (!network || !network.trim()) return null;
    const compact = network.toLowerCase().replace(/[\s_-]/g, '');
    if (['mtn', 'telecel', 'airteltigo'].includes(compact)) return compact;
    if (compact.includes('mtn')) return 'mtn';
    if (compact.includes('telecel') || compact.includes('vodafone')) return 'telecel';
    if (compact.includes('airtel') || compact.includes('tigo')) return 'airteltigo';
    return null;
}

/**
 * Label for the payable number — Telecel uses “Till number”; short codes do too.
 */
export function momoNumberFieldLabel(network?: string | null, accountNumber?: string | null): string {
    const id = normalizeMomoNetworkId(network);
    if (id === 'telecel') return 'Till number';
    const digits = (accountNumber ?? '').replace(/\D/g, '');
    if (digits.length >= 4 && digits.length <= 6) return 'Till number';
    if (id) return momoNetworkMeta(id)?.numberLabel ?? 'MoMo number';
    return 'MoMo number';
}
