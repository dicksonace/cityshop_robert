/** Ghana bank options for wallet withdrawals (buyer + seller). */
export const GHANA_BANKS: { id: string; label: string }[] = [
    { id: 'absa', label: 'ABSA' },
    { id: 'access', label: 'Access Bank' },
    { id: 'adb', label: 'ADB' },
    { id: 'adehyeman', label: 'ADEHYEMAN' },
    { id: 'advans', label: 'ADVANS GHANA' },
    { id: 'affinity', label: 'AFFINITY' },
    { id: 'arb_apex', label: 'ARB APEX BANK' },
    { id: 'bank_of_africa', label: 'BANK of Africa' },
    { id: 'bayport', label: 'Bayport S&L' },
    { id: 'bestpoint', label: 'BESTPOINT' },
    { id: 'bog', label: 'BoG' },
    { id: 'cal', label: 'CAL Bank' },
    { id: 'cbg', label: 'CBG' },
    { id: 'ecobank', label: 'Ecobank' },
    { id: 'fidelity', label: 'Fidelity Bank' },
    { id: 'firstbank', label: 'FirstBank' },
    { id: 'fnb', label: 'FNB' },
    { id: 'gcb', label: 'GCB' },
    { id: 'gtbank', label: 'GT Bank' },
    { id: 'letshego', label: 'LETSHEGO' },
    { id: 'nib', label: 'NIB' },
    { id: 'omnibsic', label: 'OMNIBSIC' },
    { id: 'opportunity', label: 'Opportunity Int. S&L' },
    { id: 'prudential', label: 'Prudential Bank' },
    { id: 'service_integrity', label: 'Service Integrity S&L' },
    { id: 'sinapi_aba', label: 'Sinapi ABA' },
    { id: 'societe_generale', label: 'SOCIETE GENERALE' },
    { id: 'stanbic', label: 'Stanbic' },
    { id: 'standard_chartered', label: 'Standard Chartered' },
    { id: 'transflow', label: 'TransFlow' },
    { id: 'uba', label: 'UBA' },
    { id: 'umb', label: 'UMB' },
    { id: 'zenith', label: 'Zenith Bank' },
];

const BANK_LABELS: Record<string, string> = Object.fromEntries(
    GHANA_BANKS.map((bank) => [bank.id, bank.label]),
);

export function isGhanaBank(code?: string | null): boolean {
    return !!code && code in BANK_LABELS;
}

export function ghanaBankLabel(code?: string | null): string {
    if (!code) return 'Bank';
    return BANK_LABELS[code] ?? code.replace(/_/g, ' ');
}

export function payoutNetworkLabel(network?: string | null): string {
    if (!network) return '—';
    if (network === 'mtn') return 'MTN Mobile Money';
    if (network === 'telecel') return 'Telecel Cash';
    if (network === 'airteltigo') return 'AirtelTigo Money';
    if (isGhanaBank(network)) return ghanaBankLabel(network);
    return network.replace(/_/g, ' ');
}
