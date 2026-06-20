import { Phone, PhoneIncoming, PhoneMissed, PhoneOff } from 'lucide-react';

import { getCallLogLabel } from '@/lib/call-log';
import { cn } from '@/lib/utils';
import type { ChatCallLog } from '@/types/chat';

interface ChatCallLogItemProps {
    log: ChatCallLog;
    viewerId: number;
    otherName: string;
    createdAt?: string;
}

function formatTime(value?: string): string {
    if (!value) return '';
    return new Date(value).toLocaleTimeString('en-GH', { hour: '2-digit', minute: '2-digit' });
}

export default function ChatCallLogItem({ log, viewerId, otherName, createdAt }: ChatCallLogItemProps) {
    const { label, sublabel, tone } = getCallLogLabel(log, viewerId, otherName);
    const isCaller = log.caller_id === viewerId;

    const Icon =
        tone === 'missed' ? PhoneMissed : log.status === 'completed' ? (isCaller ? Phone : PhoneIncoming) : PhoneOff;

    return (
        <div className="my-3 flex justify-center">
            <div
                className={cn(
                    'flex max-w-[90%] items-center gap-2.5 rounded-2xl border px-3 py-2 text-xs shadow-sm',
                    tone === 'missed'
                        ? 'border-red-100 bg-red-50 text-red-700'
                        : tone === 'success'
                          ? 'border-green-100 bg-green-50 text-green-800'
                          : 'border-gray-200 bg-white text-gray-600',
                )}
            >
                <div
                    className={cn(
                        'flex h-8 w-8 shrink-0 items-center justify-center rounded-full',
                        tone === 'missed' ? 'bg-red-100' : tone === 'success' ? 'bg-green-100' : 'bg-gray-100',
                    )}
                >
                    <Icon className="h-4 w-4" />
                </div>
                <div className="min-w-0">
                    <p className="font-semibold">{label}</p>
                    {sublabel && <p className="truncate text-[11px] opacity-80">{sublabel}</p>}
                    {createdAt && <p className="text-[10px] opacity-60">{formatTime(createdAt)}</p>}
                </div>
            </div>
        </div>
    );
}
