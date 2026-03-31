import { usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, ref } from 'vue';
import type { Ref } from 'vue';
import type { RealtimeConnectionStatus } from '@/lib/document-pipeline';
import { getEcho } from '@/lib/echo';

function normalizeConnectionStatus(status: string): RealtimeConnectionStatus {
    if (
        status === 'connecting' ||
        status === 'connected' ||
        status === 'reconnecting' ||
        status === 'disconnected' ||
        status === 'failed'
    ) {
        return status;
    }

    return 'disconnected';
}

export function useEchoConnectionStatus(): Ref<RealtimeConnectionStatus> {
    const page = usePage();
    const echo = getEcho(page.props.realtime);

    if (echo === null) {
        return ref('disabled');
    }

    const connectionStatus = ref<RealtimeConnectionStatus>(
        normalizeConnectionStatus(echo.connectionStatus()),
    );

    const unsubscribe = echo.connector.onConnectionChange((status): void => {
        connectionStatus.value = normalizeConnectionStatus(status);
    });

    onBeforeUnmount((): void => {
        unsubscribe();
    });

    return connectionStatus;
}
