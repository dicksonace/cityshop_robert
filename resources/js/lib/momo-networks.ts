export interface MomoNetwork {
    id: string;
    label: string;
    shortLabel: string;
    accent: string;
    selectedClass: string;
    badgeClass: string;
}

/** Ghana mobile money networks — MTN MoMo listed first as the default. */
export const MOMO_NETWORKS: MomoNetwork[] = [
    {
        id: 'mtn',
        label: 'MTN Mobile Money',
        shortLabel: 'MTN MoMo',
        accent: 'text-yellow-700',
        selectedClass: 'border-yellow-500 bg-yellow-50 ring-2 ring-yellow-400/60',
        badgeClass: 'bg-yellow-100 text-yellow-800',
    },
    {
        id: 'telecel',
        label: 'Telecel Cash',
        shortLabel: 'Telecel',
        accent: 'text-red-700',
        selectedClass: 'border-red-500 bg-red-50 ring-2 ring-red-400/60',
        badgeClass: 'bg-red-100 text-red-800',
    },
    {
        id: 'airteltigo',
        label: 'AirtelTigo Money',
        shortLabel: 'AirtelTigo',
        accent: 'text-blue-700',
        selectedClass: 'border-blue-500 bg-blue-50 ring-2 ring-blue-400/60',
        badgeClass: 'bg-blue-100 text-blue-800',
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
