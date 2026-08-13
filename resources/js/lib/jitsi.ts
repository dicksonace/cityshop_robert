export interface JitsiRoom {
    domain: string;
    room_name: string;
}

export function jitsiScriptUrl(domain: string): string {
    const host = (domain || 'jitsi.riot.im').replace(/[^a-zA-Z0-9.-]/g, '');

    return `https://${host}/external_api.js`;
}

export function jitsiConfigOverwrite(isHost: boolean): Record<string, unknown> {
    return {
        defaultLanguage: 'en',
        prejoinPageEnabled: false,
        startWithAudioMuted: !isHost,
        startWithVideoMuted: !isHost,
        disableDeepLinking: true,
        disableInviteFunctions: true,
        enableWelcomePage: false,
        enableClosePage: false,
        enableLobby: false,
        hideLobbyButton: true,
        lobby: { autoKnock: false },
        requireDisplayName: false,
        disableModeratorIndicator: true,
        disableProfile: true,
        analytics: { disabled: true },
        p2p: { enabled: false },
    };
}

export function jitsiInterfaceConfigOverwrite(isHost: boolean): Record<string, unknown> {
    return {
        LANG_DETECTION: false,
        SHOW_JITSI_WATERMARK: false,
        SHOW_WATERMARK_FOR_GUESTS: false,
        DISABLE_JOIN_LEAVE_NOTIFICATIONS: true,
        MOBILE_APP_PROMO: false,
        AUTHENTICATION_ENABLE: false,
        TOOLBAR_BUTTONS: isHost
            ? ['microphone', 'camera', 'hangup', 'tileview', 'fullscreen']
            : ['tileview', 'fullscreen'],
    };
}
