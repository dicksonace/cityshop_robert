import { cn } from '@/lib/utils';

const CHANNELS = [
    {
        id: 'alipay',
        label: 'Alipay',
        mark: '支',
        color: '#1677FF',
    },
    {
        id: 'wechat',
        label: 'WeChat Pay',
        mark: '微',
        color: '#07C160',
    },
] as const;

/**
 * Transfer to China — Alipay / WeChat Pay. Disabled until CityShop enables CN payouts.
 */
export default function ChinaTransferChannels({ className }: { className?: string }) {
    return (
        <div className={cn('space-y-3', className)}>
            <div>
                <p className="text-[15px] font-extrabold text-gray-900">Choose how to receive in China</p>
                <p className="mt-1 text-[13px] leading-snug text-gray-500">
                    Send wallet funds to Alipay or WeChat Pay. Currently not available.
                </p>
            </div>
            <div className="space-y-2">
                {CHANNELS.map((channel) => (
                    <div
                        key={channel.id}
                        className="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 opacity-80"
                    >
                        <span
                            className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-sm font-black text-white"
                            style={{ backgroundColor: channel.color }}
                        >
                            {channel.mark}
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="font-extrabold text-gray-900">{channel.label}</p>
                            <p className="text-xs font-semibold text-amber-700">Currently not available</p>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
