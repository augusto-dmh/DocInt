import { readonly, ref } from 'vue';
import type { DocumentStatusUpdatedPayload } from '@/types';

export type WorkspaceNotificationTone = 'info' | 'success' | 'failure';

export type WorkspaceNotification = {
    id: string;
    dedupeKey: string;
    documentId: number;
    title: string;
    message: string;
    tone: WorkspaceNotificationTone;
    occurredAt: string;
    autoDismiss: boolean;
    remainingMs: number | null;
    lastResumedAt: number | null;
    isPaused: boolean;
};

const visibleStatuses = new Set([
    'uploaded',
    'ready_for_review',
    'reviewed',
    'approved',
    'scan_failed',
    'extraction_failed',
    'classification_failed',
]);

const failureStatuses = new Set([
    'scan_failed',
    'extraction_failed',
    'classification_failed',
]);

const notifications = ref<WorkspaceNotification[]>([]);
const dismissalTimers = new Map<string, ReturnType<typeof setTimeout>>();

function documentLabel(payload: DocumentStatusUpdatedPayload): string | null {
    if (payload.document === null) {
        return null;
    }

    return payload.document.matter_title
        ? `${payload.document.title} · ${payload.document.matter_title}`
        : payload.document.title;
}

function failureStageLabel(
    status: DocumentStatusUpdatedPayload['to_status'],
): string {
    if (status === 'scan_failed') {
        return 'virus scan';
    }

    if (status === 'extraction_failed') {
        return 'extraction';
    }

    return 'classification';
}

function buildNotification(
    payload: DocumentStatusUpdatedPayload,
): WorkspaceNotification | null {
    if (!visibleStatuses.has(payload.to_status)) {
        return null;
    }

    const label = documentLabel(payload);

    if (label === null) {
        return null;
    }

    if (failureStatuses.has(payload.to_status)) {
        return {
            id: `${payload.document_id}:${payload.to_status}:${payload.occurred_at}`,
            dedupeKey: `${payload.document_id}:${payload.to_status}`,
            documentId: payload.document_id,
            title: `Attention required · ${label}`,
            message: `The document stopped during ${failureStageLabel(payload.to_status)} and needs review.`,
            tone: 'failure',
            occurredAt: payload.occurred_at,
            autoDismiss: false,
            remainingMs: null,
            lastResumedAt: null,
            isPaused: false,
        };
    }

    if (payload.to_status === 'uploaded') {
        return {
            id: `${payload.document_id}:${payload.to_status}:${payload.occurred_at}`,
            dedupeKey: `${payload.document_id}:${payload.to_status}`,
            documentId: payload.document_id,
            title: `Upload received · ${label}`,
            message: 'The document entered the tenant processing queue.',
            tone: 'info',
            occurredAt: payload.occurred_at,
            autoDismiss: true,
            remainingMs: 7000,
            lastResumedAt: null,
            isPaused: false,
        };
    }

    if (payload.to_status === 'ready_for_review') {
        return {
            id: `${payload.document_id}:${payload.to_status}:${payload.occurred_at}`,
            dedupeKey: `${payload.document_id}:${payload.to_status}`,
            documentId: payload.document_id,
            title: `Ready for review · ${label}`,
            message:
                'Extraction and classification completed. A reviewer can inspect the result now.',
            tone: 'success',
            occurredAt: payload.occurred_at,
            autoDismiss: true,
            remainingMs: 9000,
            lastResumedAt: null,
            isPaused: false,
        };
    }

    if (payload.to_status === 'reviewed') {
        return {
            id: `${payload.document_id}:${payload.to_status}:${payload.occurred_at}`,
            dedupeKey: `${payload.document_id}:${payload.to_status}`,
            documentId: payload.document_id,
            title: `Review logged · ${label}`,
            message:
                'The document was marked as reviewed and is ready for approval.',
            tone: 'success',
            occurredAt: payload.occurred_at,
            autoDismiss: true,
            remainingMs: 9000,
            lastResumedAt: null,
            isPaused: false,
        };
    }

    return {
        id: `${payload.document_id}:${payload.to_status}:${payload.occurred_at}`,
        dedupeKey: `${payload.document_id}:${payload.to_status}`,
        documentId: payload.document_id,
        title: `Approved · ${label}`,
        message: 'The document completed the review cycle and is now approved.',
        tone: 'success',
        occurredAt: payload.occurred_at,
        autoDismiss: true,
        remainingMs: 9000,
        lastResumedAt: null,
        isPaused: false,
    };
}

function clearDismissalTimer(id: string): void {
    const timer = dismissalTimers.get(id);

    if (timer !== undefined) {
        clearTimeout(timer);
        dismissalTimers.delete(id);
    }
}

function scheduleDismissal(id: string, delayMs: number): void {
    clearDismissalTimer(id);

    dismissalTimers.set(
        id,
        setTimeout(() => {
            dismissRealtimeNotification(id);
        }, delayMs),
    );
}

export function dismissRealtimeNotification(id: string): void {
    clearDismissalTimer(id);
    notifications.value = notifications.value.filter(
        (notification) => notification.id !== id,
    );
}

export function publishRealtimeNotification(
    payload: DocumentStatusUpdatedPayload,
): void {
    const notification = buildNotification(payload);

    if (notification === null) {
        return;
    }

    if (notifications.value.some((item) => item.id === notification.id)) {
        return;
    }

    const existingIndex = notifications.value.findIndex(
        (item) => item.dedupeKey === notification.dedupeKey,
    );

    if (existingIndex >= 0) {
        const existing = notifications.value[existingIndex];

        if (
            new Date(existing.occurredAt).getTime() >=
            new Date(notification.occurredAt).getTime()
        ) {
            return;
        }

        clearDismissalTimer(existing.id);
        notifications.value.splice(existingIndex, 1);
    }

    const nextNotifications = [notification, ...notifications.value];
    const removedNotifications = nextNotifications.slice(5);

    removedNotifications.forEach((removedNotification) => {
        clearDismissalTimer(removedNotification.id);
    });

    notifications.value = nextNotifications.slice(0, 5);

    if (notification.autoDismiss && notification.remainingMs !== null) {
        notification.lastResumedAt = Date.now();
        scheduleDismissal(notification.id, notification.remainingMs);
    }
}

export function useRealtimeNotifications() {
    return readonly(notifications);
}

export function pauseRealtimeNotification(id: string): void {
    const notification = notifications.value.find((item) => item.id === id);

    if (
        notification === undefined ||
        !notification.autoDismiss ||
        notification.isPaused ||
        notification.remainingMs === null
    ) {
        return;
    }

    const now = Date.now();

    if (notification.lastResumedAt !== null) {
        notification.remainingMs = Math.max(
            0,
            notification.remainingMs - (now - notification.lastResumedAt),
        );
    }

    notification.lastResumedAt = null;
    notification.isPaused = true;
    clearDismissalTimer(id);
}

export function resumeRealtimeNotification(id: string): void {
    const notification = notifications.value.find((item) => item.id === id);

    if (
        notification === undefined ||
        !notification.autoDismiss ||
        !notification.isPaused ||
        notification.remainingMs === null
    ) {
        return;
    }

    notification.isPaused = false;
    notification.lastResumedAt = Date.now();
    scheduleDismissal(id, notification.remainingMs);
}
