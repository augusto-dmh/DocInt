import type { DocumentStatus } from '@/types';

export type PipelineStageId =
    | 'upload'
    | 'scan'
    | 'extract'
    | 'classify'
    | 'review';

export type RealtimeConnectionStatus =
    | 'disabled'
    | 'connecting'
    | 'connected'
    | 'reconnecting'
    | 'disconnected'
    | 'failed';

export type PipelineStageMeta = {
    id: PipelineStageId;
    label: string;
    description: string;
};

export const pipelineStages: PipelineStageMeta[] = [
    {
        id: 'upload',
        label: 'Upload',
        description: 'New files enter the tenant processing queue.',
    },
    {
        id: 'scan',
        label: 'Scan',
        description: 'Files are checked before extraction continues.',
    },
    {
        id: 'extract',
        label: 'Extract',
        description: 'Text and structure are pulled into reviewable data.',
    },
    {
        id: 'classify',
        label: 'Classify',
        description: 'Document type signals are resolved for routing.',
    },
    {
        id: 'review',
        label: 'Review',
        description: 'Human validation and approval complete the flow.',
    },
];

export function pipelineStageForStatus(
    status: DocumentStatus,
): PipelineStageId {
    if (status === 'uploaded') {
        return 'upload';
    }

    if (
        status === 'scanning' ||
        status === 'scan_passed' ||
        status === 'scan_failed'
    ) {
        return 'scan';
    }

    if (status === 'extracting' || status === 'extraction_failed') {
        return 'extract';
    }

    if (status === 'classifying' || status === 'classification_failed') {
        return 'classify';
    }

    return 'review';
}

export function isFailureDocumentStatus(status: DocumentStatus): boolean {
    return [
        'scan_failed',
        'extraction_failed',
        'classification_failed',
    ].includes(status);
}

export function isTerminalDocumentStatus(status: DocumentStatus): boolean {
    return isFailureDocumentStatus(status) || status === 'approved';
}

export function isActivePipelineDocument(status: DocumentStatus): boolean {
    return !isTerminalDocumentStatus(status);
}
