import { usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, toValue, watch } from 'vue';
import type { MaybeRefOrGetter } from 'vue';
import { getEcho } from '@/lib/echo';
import type {
    DocumentCommentUpdatedPayload,
    DocumentStatusUpdatedPayload,
} from '@/types';

type UseDocumentChannelOptions = {
    tenantId?: MaybeRefOrGetter<string | null | undefined>;
    documentId?: MaybeRefOrGetter<number | null | undefined>;
    onStatusUpdated: (payload: DocumentStatusUpdatedPayload) => void;
    onCommentUpdated?: (payload: DocumentCommentUpdatedPayload) => void;
};

function isDocumentStatusUpdatedPayload(
    value: unknown,
): value is DocumentStatusUpdatedPayload {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const payload = value as Record<string, unknown>;

    return (
        typeof payload.tenant_id === 'string' &&
        typeof payload.document_id === 'number' &&
        (typeof payload.from_status === 'string' ||
            payload.from_status === null) &&
        typeof payload.to_status === 'string' &&
        (typeof payload.trace_id === 'string' || payload.trace_id === null) &&
        typeof payload.occurred_at === 'string'
    );
}

function isDocumentCommentUpdatedPayload(
    value: unknown,
): value is DocumentCommentUpdatedPayload {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const payload = value as Record<string, unknown>;

    return (
        typeof payload.tenant_id === 'string' &&
        typeof payload.document_id === 'number' &&
        ['created', 'updated', 'deleted'].includes(
            typeof payload.action === 'string' ? payload.action : '',
        ) &&
        (typeof payload.comment_id === 'number' ||
            payload.comment_id === null) &&
        typeof payload.occurred_at === 'string'
    );
}

function channelNameFromPattern(
    pattern: string,
    key: string,
    value: string | number,
): string {
    return pattern.replace(`{${key}}`, String(value));
}

export function useDocumentChannel(options: UseDocumentChannelOptions): void {
    const page = usePage();
    const echo = getEcho(page.props.realtime);

    if (!echo) {
        return;
    }

    const subscribedChannels = new Set<string>();

    const listenForUpdates = (channelName: string): void => {
        if (subscribedChannels.has(channelName)) {
            return;
        }

        echo.private(channelName).listen(
            '.document.status.updated',
            (payload: unknown): void => {
                if (!isDocumentStatusUpdatedPayload(payload)) {
                    return;
                }

                options.onStatusUpdated(payload);
            },
        );

        echo.private(channelName).listen(
            '.document.comment.updated',
            (payload: unknown): void => {
                if (
                    options.onCommentUpdated === undefined ||
                    !isDocumentCommentUpdatedPayload(payload)
                ) {
                    return;
                }

                options.onCommentUpdated(payload);
            },
        );

        subscribedChannels.add(channelName);
    };

    const leaveChannel = (channelName: string): void => {
        if (!subscribedChannels.has(channelName)) {
            return;
        }

        echo.leave(channelName);
        subscribedChannels.delete(channelName);
    };

    const stopWatching = watch(
        () => {
            const channelNames: string[] = [];
            const tenantId = toValue(options.tenantId);
            const documentId = toValue(options.documentId);

            if (typeof tenantId === 'string' && tenantId !== '') {
                channelNames.push(
                    channelNameFromPattern(
                        page.props.realtime.channels.tenantDocumentsPattern,
                        'tenantId',
                        tenantId,
                    ),
                );
            }

            if (typeof documentId === 'number') {
                channelNames.push(
                    channelNameFromPattern(
                        page.props.realtime.channels.documentPattern,
                        'documentId',
                        documentId,
                    ),
                );
            }

            return channelNames;
        },
        (channelNames) => {
            const nextChannels = new Set(channelNames);

            for (const channelName of subscribedChannels) {
                if (!nextChannels.has(channelName)) {
                    leaveChannel(channelName);
                }
            }

            channelNames.forEach((channelName) => {
                listenForUpdates(channelName);
            });
        },
        { immediate: true },
    );

    onBeforeUnmount((): void => {
        stopWatching();

        for (const channelName of subscribedChannels) {
            leaveChannel(channelName);
        }
    });
}
