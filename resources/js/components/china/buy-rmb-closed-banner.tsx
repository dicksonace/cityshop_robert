type TransferHours = {
    configured?: boolean;
    in_processing_window?: boolean;
    open_time_label?: string | null;
    close_time_label?: string | null;
    processing_note?: string | null;
    closed_message?: string | null;
};

type Props = {
    transferHours?: TransferHours | null;
    className?: string;
};

export default function BuyRmbProcessingNote({ transferHours, className = '' }: Props) {
    const message =
        transferHours?.processing_note?.trim() ||
        transferHours?.closed_message?.trim() ||
        null;

    if (!transferHours?.configured || !message) {
        return null;
    }

    return (
        <div
            className={`rounded-2xl border border-blue-200 bg-[#EFF6FF] px-4 py-3.5 ${className}`.trim()}
        >
            <div className="flex items-start gap-3">
                <span className="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-700">
                    <svg viewBox="0 0 24 24" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2">
                        <circle cx="12" cy="12" r="9" />
                        <path d="M12 7v5l3 2" />
                    </svg>
                </span>
                <div className="min-w-0">
                    <p className="text-sm font-semibold leading-snug text-blue-950">{message}</p>
                    {(transferHours.open_time_label || transferHours.close_time_label) && (
                        <div className="mt-2 text-xs font-semibold text-blue-900/80">
                            <p>Admin processing window</p>
                            {transferHours.open_time_label && <p>From {transferHours.open_time_label}</p>}
                            {transferHours.close_time_label && <p>Until {transferHours.close_time_label}</p>}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
