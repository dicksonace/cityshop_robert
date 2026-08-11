interface OnlineIndicatorProps {
    online: boolean;
    showLabel?: boolean;
    size?: 'sm' | 'md';
    lastSeenAt?: string | null;
    isGroup?: boolean;
    onlineCount?: number;
}

function formatLastSeen(iso?: string | null): string {
    if (!iso) return 'Offline';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return 'Offline';
    const mins = Math.floor((Date.now() - d.getTime()) / 60000);
    if (mins < 1) return 'Last seen just now';
    if (mins < 60) return `Last seen ${mins} min ago`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `Last seen ${hours}h ago`;
    const days = Math.floor(hours / 24);
    if (days === 1) return 'Last seen yesterday';
    if (days < 7) return `Last seen ${days} days ago`;
    return `Last seen ${d.toLocaleDateString('en-GH', { day: 'numeric', month: 'short' })}`;
}

export default function OnlineIndicator({
    online,
    showLabel = true,
    size = 'sm',
    lastSeenAt,
    isGroup = false,
    onlineCount,
}: OnlineIndicatorProps) {
    const dotSize = size === 'sm' ? 'h-2 w-2' : 'h-2.5 w-2.5';
    const groupOnline = isGroup && (onlineCount ?? 0) > 0;
    const isOnline = isGroup ? groupOnline : online;
    const label = isGroup
        ? groupOnline
            ? `${onlineCount} online`
            : 'Offline'
        : isOnline
          ? 'Online'
          : formatLastSeen(lastSeenAt);

    return (
        <span className="inline-flex items-center gap-1.5">
            <span className={`${dotSize} rounded-full ${isOnline ? 'bg-green-500' : 'bg-gray-300'}`} />
            {showLabel && (
                <span className={`text-xs font-medium ${isOnline ? 'text-green-600' : 'text-gray-400'}`}>
                    {label}
                </span>
            )}
        </span>
    );
}
