export type CallLogStatus = 'completed' | 'missed' | 'declined' | 'cancelled';

export interface ChatCallLog {
    status: CallLogStatus;
    caller_id: number;
    caller_name: string;
    ended_by_id: number;
    duration_seconds: number;
}

export function formatCallDuration(seconds: number): string {
    if (seconds <= 0) return '';
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

export function getCallLogLabel(
    log: ChatCallLog,
    viewerId: number,
    otherName: string,
): { label: string; sublabel?: string; tone: 'default' | 'missed' | 'success' } {
    const isCaller = log.caller_id === viewerId;
    const duration = formatCallDuration(log.duration_seconds);

    switch (log.status) {
        case 'completed':
            return {
                label: isCaller ? 'Outgoing voice call' : 'Incoming voice call',
                sublabel: duration ? `Duration ${duration}` : undefined,
                tone: 'success',
            };
        case 'missed':
            return isCaller
                ? { label: 'No answer', sublabel: `Called ${otherName}`, tone: 'default' }
                : { label: 'Missed voice call', sublabel: `From ${log.caller_name}`, tone: 'missed' };
        case 'declined':
            return isCaller
                ? { label: 'Call declined', sublabel: `${otherName} declined`, tone: 'default' }
                : { label: 'Declined voice call', sublabel: `From ${log.caller_name}`, tone: 'default' };
        case 'cancelled':
        default:
            return isCaller
                ? { label: 'Cancelled call', sublabel: `Called ${otherName}`, tone: 'default' }
                : { label: 'Missed voice call', sublabel: `From ${log.caller_name}`, tone: 'missed' };
    }
}

export function getCallLogListPreview(log: ChatCallLog, viewerId: number): string {
    const isCaller = log.caller_id === viewerId;
    switch (log.status) {
        case 'completed':
            return isCaller ? 'Outgoing call' : 'Incoming call';
        case 'missed':
            return isCaller ? 'No answer' : 'Missed call';
        case 'declined':
            return isCaller ? 'Call declined' : 'Declined call';
        default:
            return isCaller ? 'Cancelled call' : 'Missed call';
    }
}
