import { isFailureDocumentStatus } from '@/lib/document-pipeline';
import type {
    DashboardFailureDocument,
    DashboardPipelineDocument,
    DashboardStats,
    DocumentStatus,
    DocumentStatusUpdatedPayload,
} from '@/types';

type DocumentRealtimeState = {
    status: DocumentStatus;
    occurred_at: string;
};

const pendingReviewStatuses = new Set<DocumentStatus>([
    'ready_for_review',
    'reviewed',
]);

export function seedRealtimeDocumentState(
    documents: DashboardPipelineDocument[],
): Map<number, DocumentRealtimeState> {
    return new Map(
        documents.map((document) => [
            document.id,
            {
                status: document.status,
                occurred_at: document.updated_at,
            },
        ]),
    );
}

export function shouldApplyRealtimeUpdate(
    knownState: DocumentRealtimeState | undefined,
    payload: DocumentStatusUpdatedPayload,
): boolean {
    if (knownState === undefined) {
        return true;
    }

    const knownTimestamp = new Date(knownState.occurred_at).getTime();
    const incomingTimestamp = new Date(payload.occurred_at).getTime();

    if (incomingTimestamp < knownTimestamp) {
        return false;
    }

    return !(
        incomingTimestamp === knownTimestamp &&
        knownState.status === payload.to_status
    );
}

function isPendingReviewStatus(status: DocumentStatus): boolean {
    return pendingReviewStatuses.has(status);
}

export function applyDashboardStatsDelta(
    stats: DashboardStats,
    fromStatus: DocumentStatus | null,
    toStatus: DocumentStatus,
): DashboardStats {
    const nextStats = { ...stats };

    if (fromStatus !== 'approved' && toStatus === 'approved') {
        nextStats.processed_today += 1;
    }

    if (
        fromStatus !== null &&
        isPendingReviewStatus(fromStatus) &&
        !isPendingReviewStatus(toStatus) &&
        nextStats.pending_review > 0
    ) {
        nextStats.pending_review -= 1;
    }

    if (
        fromStatus === null ||
        (!isPendingReviewStatus(fromStatus) && isPendingReviewStatus(toStatus))
    ) {
        nextStats.pending_review += 1;
    }

    if (
        fromStatus !== null &&
        isFailureDocumentStatus(fromStatus) &&
        !isFailureDocumentStatus(toStatus) &&
        nextStats.failed > 0
    ) {
        nextStats.failed -= 1;
    }

    if (
        fromStatus === null ||
        (!isFailureDocumentStatus(fromStatus) &&
            isFailureDocumentStatus(toStatus))
    ) {
        nextStats.failed += 1;
    }

    return nextStats;
}

export function upsertPipelineDocument(
    documents: DashboardPipelineDocument[],
    payload: DocumentStatusUpdatedPayload,
): DashboardPipelineDocument[] {
    const nextDocuments = [...documents];
    const documentIndex = nextDocuments.findIndex(
        (document) => document.id === payload.document_id,
    );

    const snapshotTitle =
        payload.document?.title ?? `Document #${payload.document_id}`;
    const snapshotMatterTitle = payload.document?.matter_title ?? null;

    if (documentIndex >= 0) {
        nextDocuments[documentIndex] = {
            ...nextDocuments[documentIndex],
            title: snapshotTitle,
            matter_title: snapshotMatterTitle,
            status: payload.to_status,
            updated_at: payload.occurred_at,
        };
    } else {
        nextDocuments.unshift({
            id: payload.document_id,
            title: snapshotTitle,
            status: payload.to_status,
            matter_title: snapshotMatterTitle,
            updated_at: payload.occurred_at,
        });
    }

    return nextDocuments
        .sort(
            (firstDocument, secondDocument) =>
                new Date(secondDocument.updated_at).getTime() -
                new Date(firstDocument.updated_at).getTime(),
        )
        .slice(0, 8);
}

export function syncRecentFailures(
    failures: DashboardFailureDocument[],
    payload: DocumentStatusUpdatedPayload,
): DashboardFailureDocument[] {
    if (payload.document === null) {
        return failures;
    }

    const nextFailures = [...failures];
    const failureIndex = nextFailures.findIndex(
        (failure) => failure.id === payload.document_id,
    );

    if (isFailureDocumentStatus(payload.to_status)) {
        const failureDocument: DashboardFailureDocument = {
            id: payload.document_id,
            title: payload.document.title,
            status: payload.to_status,
            matter_title: payload.document.matter_title,
            updated_at: payload.occurred_at,
        };

        if (failureIndex >= 0) {
            nextFailures[failureIndex] = failureDocument;
        } else {
            nextFailures.unshift(failureDocument);
        }

        return nextFailures
            .sort(
                (firstFailure, secondFailure) =>
                    new Date(secondFailure.updated_at).getTime() -
                    new Date(firstFailure.updated_at).getTime(),
            )
            .slice(0, 5);
    }

    if (failureIndex >= 0) {
        nextFailures.splice(failureIndex, 1);
    }

    return nextFailures;
}
