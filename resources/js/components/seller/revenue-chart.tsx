import { formatPrice } from '@/types/marketplace';

interface ChartPoint {
    date: string;
    revenue: number;
    orders: number;
}

interface RevenueChartProps {
    data: ChartPoint[];
}

export default function RevenueChart({ data }: RevenueChartProps) {
    const maxRevenue = Math.max(...data.map((d) => d.revenue), 1);
    const recent = data.slice(-14);

    return (
        <div className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="font-semibold text-gray-900">Revenue (30 days)</h3>
                    <p className="text-sm text-gray-500">Daily seller earnings</p>
                </div>
                <p className="text-lg font-bold text-orange-500">
                    {formatPrice(recent.reduce((s, d) => s + d.revenue, 0))}
                    <span className="ml-1 text-xs font-normal text-gray-400">last 14d</span>
                </p>
            </div>
            <div className="mt-6 flex h-32 items-end gap-1">
                {recent.map((point) => {
                    const height = Math.max(4, (point.revenue / maxRevenue) * 100);
                    return (
                        <div key={point.date} className="group relative flex flex-1 flex-col items-center justify-end">
                            <div
                                className="w-full rounded-t bg-gradient-to-t from-orange-500 to-orange-400 transition-all group-hover:from-orange-600"
                                style={{ height: `${height}%` }}
                                title={`${point.date}: ${formatPrice(point.revenue)}`}
                            />
                        </div>
                    );
                })}
            </div>
            <div className="mt-2 flex justify-between text-[10px] text-gray-400">
                <span>{recent[0]?.date.slice(5)}</span>
                <span>{recent[recent.length - 1]?.date.slice(5)}</span>
            </div>
        </div>
    );
}
