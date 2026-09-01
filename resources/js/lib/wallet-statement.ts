import { formatPrice, formatWalletTransactionType } from '@/types/marketplace';

export type StatementPeriod = {
    id: string;
    label: string;
    days: number | null;
};

export const STATEMENT_PERIODS: StatementPeriod[] = [
    { id: '30d', label: 'Last 30 days', days: 30 },
    { id: '90d', label: 'Last 3 months', days: 90 },
    { id: '365d', label: 'Last 12 months', days: 365 },
    { id: 'all', label: 'All transactions', days: null },
];

export type StatementTransaction = {
    id: number;
    type: string;
    type_label?: string | null;
    amount: number;
    currency?: string;
    description?: string | null;
    reference?: string | null;
    created_at?: string | null;
    balance_before?: number | null;
    balance_after?: number | null;
    rmb_before?: number | null;
    rmb_after?: number | null;
};

function formatStatementDate(value?: string | null): string {
    if (!value) return '—';
    return new Date(value).toLocaleString('en-GH', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatStatementAmount(tx: StatementTransaction): string {
    const currency = (tx.currency ?? 'GHS').toUpperCase();
    const abs = Math.abs(tx.amount);
    const sign = tx.amount >= 0 ? '+' : '-';

    if (currency === 'RMB') {
        return `${sign}¥${abs.toFixed(2)}`;
    }

    return `${sign}${formatPrice(abs)}`;
}

function formatStatementBalance(tx: StatementTransaction, which: 'before' | 'after'): string {
    const currency = (tx.currency ?? 'GHS').toUpperCase();
    const value =
        currency === 'RMB'
            ? which === 'before'
                ? tx.rmb_before ?? tx.balance_before
                : tx.rmb_after ?? tx.balance_after
            : which === 'before'
              ? tx.balance_before
              : tx.balance_after;

    if (value == null) return '—';
    if (currency === 'RMB') return `¥${Number(value).toFixed(2)}`;
    return formatPrice(value);
}

function escapeHtml(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function txDetails(tx: StatementTransaction): string {
    const parts = [
        tx.description?.trim(),
        tx.reference?.trim() ? `Ref: ${tx.reference.trim()}` : '',
    ].filter(Boolean);

    return parts.join('<br>') || '—';
}

export async function fetchStatementTransactions(
    since: Date | null,
    currencyFilter: 'all' | 'GHS' | 'RMB' = 'all',
): Promise<StatementTransaction[]> {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const collected: StatementTransaction[] = [];
    const perPage = 50;
    const maxPages = 20;
    let page = 1;
    let lastPage = 1;

    while (page <= lastPage && page <= maxPages) {
        const params = new URLSearchParams({
            page: String(page),
            per_page: String(perPage),
        });
        if (currencyFilter !== 'all') {
            params.set('currency', currencyFilter);
        }

        const res = await fetch(`/api/v1/wallet/transactions?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            throw new Error(data.message ?? 'Could not load transactions for statement.');
        }

        const payload = await res.json();
        const rows = (payload.data ?? []) as StatementTransaction[];
        lastPage = payload.meta?.last_page ?? page;

        let reachedCutoff = false;
        for (const tx of rows) {
            if (since && tx.created_at) {
                const at = new Date(tx.created_at);
                if (!Number.isNaN(at.getTime()) && at < since) {
                    reachedCutoff = true;
                    break;
                }
            }
            collected.push(tx);
        }

        if (reachedCutoff) break;
        page += 1;
    }

    return collected;
}

export function printWalletStatement(options: {
    accountName: string;
    accountMobile?: string | null;
    periodLabel: string;
    transactions: StatementTransaction[];
    closingBalance?: number | null;
}): void {
    const { accountName, accountMobile, periodLabel, transactions, closingBalance } = options;

    let moneyIn = 0;
    let moneyOut = 0;
    for (const tx of transactions) {
        if ((tx.currency ?? 'GHS').toUpperCase() === 'RMB') continue;
        if (tx.amount >= 0) moneyIn += tx.amount;
        else moneyOut += -tx.amount;
    }

    const rows = transactions
        .map(
            (tx) => `
            <tr>
                <td>${escapeHtml(formatStatementDate(tx.created_at))}</td>
                <td>${escapeHtml(formatWalletTransactionType(tx.type, tx.type_label))}</td>
                <td>${txDetails(tx)}</td>
                <td class="num">${escapeHtml(formatStatementAmount(tx))}</td>
                <td class="num">${escapeHtml(formatStatementBalance(tx, 'before'))}</td>
                <td class="num">${escapeHtml(formatStatementBalance(tx, 'after'))}</td>
            </tr>`,
        )
        .join('');

    const html = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>CityShop wallet statement</title>
<style>
  body { font-family: system-ui, -apple-system, sans-serif; color: #111; margin: 32px; }
  h1 { margin: 0; color: #c2410c; font-size: 24px; }
  h2 { margin: 4px 0 0; font-size: 13px; color: #666; font-weight: 600; }
  .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 18px 0 12px; }
  .box { border: 1px solid #ddd; border-radius: 8px; padding: 12px; }
  .box h3 { margin: 0 0 6px; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
  .box p { margin: 0 0 4px; font-size: 11px; }
  .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 16px; }
  .tile { background: #f9fafb; border-radius: 8px; padding: 10px; }
  .tile span { display: block; font-size: 9px; color: #666; }
  .tile strong { display: block; margin-top: 4px; font-size: 11px; }
  table { width: 100%; border-collapse: collapse; font-size: 10px; }
  th, td { border: 1px solid #ddd; padding: 6px; vertical-align: top; text-align: left; }
  th { background: #f3f4f6; }
  td.num { text-align: right; white-space: nowrap; }
  footer { margin-top: 18px; font-size: 8px; color: #666; }
  @media print { body { margin: 16px; } }
</style>
</head>
<body>
  <h1>CityShop</h1>
  <h2>Wallet statement</h2>
  <div class="grid">
    <div class="box">
      <h3>Account holder</h3>
      <p>${escapeHtml(accountName)}</p>
      ${accountMobile?.trim() ? `<p>Mobile: ${escapeHtml(accountMobile.trim())}</p>` : ''}
    </div>
    <div class="box">
      <h3>Statement details</h3>
      <p>Period: ${escapeHtml(periodLabel)}</p>
      <p>Generated: ${escapeHtml(formatStatementDate(new Date().toISOString()))}</p>
      <p>Entries: ${transactions.length}</p>
    </div>
  </div>
  <div class="summary">
    <div class="tile"><span>Money in</span><strong style="color:#166534">${escapeHtml(formatPrice(moneyIn))}</strong></div>
    <div class="tile"><span>Money out</span><strong style="color:#b91c1c">${escapeHtml(formatPrice(moneyOut))}</strong></div>
    <div class="tile"><span>Net</span><strong>${escapeHtml(formatPrice(moneyIn - moneyOut))}</strong></div>
    ${
        closingBalance != null
            ? `<div class="tile"><span>Closing balance</span><strong style="color:#c2410c">${escapeHtml(formatPrice(closingBalance))}</strong></div>`
            : ''
    }
  </div>
  ${
      transactions.length === 0
          ? '<p>No transactions in this period.</p>'
          : `<table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Type</th>
          <th>Details</th>
          <th>Amount</th>
          <th>Before balance</th>
          <th>After balance</th>
        </tr>
      </thead>
      <tbody>${rows}</tbody>
    </table>`
  }
  <footer>Computer generated statement — no signature required.</footer>
  <script>window.onload = () => { window.print(); };</script>
</body>
</html>`;

    const win = window.open('', '_blank', 'noopener,noreferrer,width=960,height=720');
    if (!win) {
        throw new Error('Pop-up blocked. Allow pop-ups to print or save the statement.');
    }
    win.document.open();
    win.document.write(html);
    win.document.close();
}
