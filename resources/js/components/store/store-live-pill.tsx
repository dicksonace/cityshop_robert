import { productImageUrl } from '@/types/marketplace';

interface StoreLivePillProps {
    storeName: string;
    shopPhotoUrl?: string | null;
    title?: string | null;
    onClick?: () => void;
    href?: string;
    className?: string;
}

export default function StoreLivePill({
    storeName,
    shopPhotoUrl,
    title,
    onClick,
    href,
    className = '',
}: StoreLivePillProps) {
    const label = title?.trim() || storeName;
    const photo = shopPhotoUrl ? productImageUrl(shopPhotoUrl) : null;
    const inner = (
        <>
            <span className="relative shrink-0">
                <span className="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full bg-orange-50 text-sm font-bold text-orange-600 ring-2 ring-orange-100">
                    {photo ? <img src={photo} alt="" className="h-full w-full object-cover" /> : storeName.charAt(0).toUpperCase()}
                </span>
                <span className="absolute -right-0.5 -top-0.5 h-3 w-3 rounded-full border-2 border-white bg-red-600" aria-hidden />
            </span>
            <span className="min-w-0 flex-1 truncate text-sm font-bold text-gray-900">{label}</span>
            <span className="shrink-0 rounded-md bg-red-600 px-2 py-1 text-[11px] font-black tracking-wide text-white">LIVE</span>
        </>
    );

    const shellClass =
        `inline-flex w-full max-w-full items-center gap-3 rounded-full border-2 border-orange-200 bg-white px-3 py-2.5 shadow-sm transition hover:border-orange-300 hover:shadow ${className}`;

    if (href) {
        return (
            <a href={href} className={shellClass}>
                {inner}
            </a>
        );
    }

    return (
        <button type="button" onClick={onClick} className={shellClass}>
            {inner}
        </button>
    );
}
