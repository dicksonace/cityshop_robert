type TransferHours = {
    configured?: boolean;
    is_open_now?: boolean;
    open_time_label?: string | null;
    close_time_label?: string | null;
    closed_message?: string | null;
};

type Props = {
    transferHours?: TransferHours | null;
    className?: string;
};

export default function BuyRmbClosedBanner({ transferHours, className = '' }: Props) {
    if (!transferHours?.configured || transferHours.is_open_now !== false) {
        return null;
    }

    const message =
        transferHours.closed_message ??
        "Sorry, we're closed. We continue when we reopen.";

    return (
        <div
            className={`rounded-2xl border border-amber-200 bg-[#FFF7ED] px-4 py-3.5 ${className}`.trim()}
        >
            <div className="flex items-start gap-3">
                <span className="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                    <svg viewBox="0 0 24 24" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2">
                        <circle cx="12" cy="12" r="9" />
                        <path d="M12 7v5l3 2" />
                    </svg>
                </span>
                <div className="min-w-0">
                    <p className="text-sm font-semibold leading-snug text-amber-950">{message}</p>
                    {(transferHours.open_time_label || transferHours.close_time_label) && (
                        <div className="mt-2 text-xs font-semibold text-amber-900/80">
                            <p>Transfer time</p>
                            {transferHours.open_time_label && <p>Open time {transferHours.open_time_label}</p>}
                            {transferHours.close_time_label && <p>Close time {transferHours.close_time_label}</p>}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
