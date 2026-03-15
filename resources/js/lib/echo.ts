import type Echo from 'laravel-echo';
import LaravelEcho from 'laravel-echo';
import Pusher from 'pusher-js';
import type { RealtimeConfig } from '@/types';

declare global {
    interface Window {
        Echo?: Echo<'reverb'>;
        Pusher?: typeof Pusher;
    }
}

let echoInstance: Echo<'reverb'> | null = null;

function isConfigured(config: RealtimeConfig | null | undefined): boolean {
    return Boolean(
        config?.enabled &&
        config.broadcaster === 'reverb' &&
        config.appKey &&
        config.host &&
        config.port &&
        config.scheme,
    );
}

export function initializeEcho(
    config: RealtimeConfig | null | undefined,
): Echo<'reverb'> | null {
    if (typeof window === 'undefined') {
        return null;
    }

    if (echoInstance) {
        return echoInstance;
    }

    if (!isConfigured(config)) {
        return null;
    }

    const resolvedConfig = config as RealtimeConfig & {
        appKey: string;
        host: string;
        port: number;
        scheme: string;
    };

    window.Pusher = Pusher;

    echoInstance = new LaravelEcho({
        broadcaster: 'reverb',
        key: resolvedConfig.appKey,
        wsHost: resolvedConfig.host,
        wsPort: resolvedConfig.port,
        wssPort: resolvedConfig.port,
        forceTLS: resolvedConfig.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    window.Echo = echoInstance;

    return echoInstance;
}

export function getEcho(
    config: RealtimeConfig | null | undefined,
): Echo<'reverb'> | null {
    return echoInstance ?? initializeEcho(config);
}
