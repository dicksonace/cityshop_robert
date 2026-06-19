interface OnlineIndicatorProps {
    online: boolean;
    showLabel?: boolean;
    size?: 'sm' | 'md';
}

export default function OnlineIndicator({ online, showLabel = true, size = 'sm' }: OnlineIndicatorProps) {
    const dotSize = size === 'sm' ? 'h-2 w-2' : 'h-2.5 w-2.5';

    return (
        <span className="inline-flex items-center gap-1.5">
            <span className={`${dotSize} rounded-full ${online ? 'bg-green-500' : 'bg-gray-300'}`} />
            {showLabel && (
                <span className={`text-xs font-medium ${online ? 'text-green-600' : 'text-gray-400'}`}>
                    {online ? 'Online' : 'Offline'}
                </span>
            )}
        </span>
    );
}
