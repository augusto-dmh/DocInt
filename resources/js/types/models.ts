import type { User } from './auth';

export type Client = {
    id: number;
    tenant_id: string;
    name: string;
    email: string | null;
    phone: string | null;
    company: string | null;
    notes: string | null;
    created_at: string;
    updated_at: string;
    matters_count?: number;
    matters?: Matter[];
};

export type MatterStatus = 'open' | 'closed' | 'on_hold';

export type Matter = {
    id: number;
    tenant_id: string;
    client_id: number;
    title: string;
    description: string | null;
    reference_number: string | null;
    status: MatterStatus;
    created_at: string;
    updated_at: string;
    documents_count?: number;
    client?: Client;
    documents?: Document[];
};

export type DocumentStatus =
    | 'uploaded'
    | 'scanning'
    | 'scan_passed'
    | 'extracting'
    | 'classifying'
    | 'ready_for_review'
    | 'reviewed'
    | 'approved'
    | 'rejected'
    | 'scan_failed'
    | 'extraction_failed'
    | 'classification_failed';

export type Document = {
    id: number;
    tenant_id: string;
    matter_id: number;
    uploaded_by: number | null;
    assigned_to?: number | null;
    title: string;
    file_path: string;
    file_name: string;
    mime_type: string | null;
    file_size: number;
    status: DocumentStatus;
    processing_trace_id: string | null;
    created_at: string;
    updated_at: string;
    matter?: Matter;
    uploader?: User | null;
    assignee?: Pick<User, 'id' | 'name'> | null;
};

export type DocumentPreview = {
    supported: boolean;
    url: string | null;
    mimeType: string | null;
    fileName: string;
};

export type DocumentAnnotationType = 'highlight' | 'comment' | 'note';

export type DocumentAnnotationCoordinates = {
    x: number;
    y: number;
    width: number;
    height: number;
};

export type DocumentAnnotation = {
    id: number;
    type: DocumentAnnotationType;
    page_number: number;
    coordinates: DocumentAnnotationCoordinates;
    content: string | null;
    created_at: string;
    updated_at: string;
    user: Pick<User, 'id' | 'name'> | null;
    is_owner: boolean;
};

export type DocumentComment = {
    id: number;
    parent_id: number | null;
    body: string;
    created_at: string;
    updated_at: string;
    user: Pick<User, 'id' | 'name'> | null;
};

export type DocumentReviewerOption = Pick<User, 'id' | 'name'>;

export type DocumentReviewWorkspace = {
    preview: DocumentPreview;
    annotations: DocumentAnnotation[];
    comments: DocumentComment[];
    availableReviewers: DocumentReviewerOption[];
    permissions: {
        canAnnotate: boolean;
        canAssignReviewer: boolean;
        canComment: boolean;
        canModerateComments: boolean;
    };
};

export type DocumentBulkReview = {
    availableReviewers: DocumentReviewerOption[];
    permissions: {
        canBulkApprove: boolean;
        canBulkReject: boolean;
        canBulkReassign: boolean;
    };
};

export type DocumentBulkActionSkipped = {
    document_id: number;
    title: string | null;
    reason: string;
};

export type DocumentBulkActionResult = {
    action: 'approved' | 'rejected' | 'reassign';
    attempted_count: number;
    processed_count: number;
    skipped_count: number;
    processed_ids: number[];
    skipped: DocumentBulkActionSkipped[];
    message: string;
};

export type DocumentExtractedData = {
    provider: string;
    extracted_text: string | null;
    payload: Record<string, unknown> | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;
};

export type DocumentClassificationSummary = {
    provider: string;
    type: string;
    confidence: number | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;
};

export type DocumentActivity = {
    id: number;
    action: string;
    details: string | null;
    created_at: string;
    user: Pick<User, 'id' | 'name'> | null;
    ip_address: string | null;
};

export type DocumentProcessingActivity = {
    id: number;
    consumer_name: string;
    status_from: string | null;
    status_to: string | null;
    event: string;
    created_at: string;
};

export type DocumentStatusUpdatedPayload = {
    tenant_id: string;
    document_id: number;
    from_status: DocumentStatus | null;
    to_status: DocumentStatus;
    trace_id: string | null;
    occurred_at: string;
    document: {
        title: string;
        matter_title: string | null;
    } | null;
};

export type DocumentCommentUpdatedPayload = {
    tenant_id: string;
    document_id: number;
    action: 'created' | 'updated' | 'deleted';
    comment: DocumentComment | null;
    comment_id: number | null;
    activity: DocumentActivity | null;
    occurred_at: string;
};

export type DashboardStats = {
    processed_today: number;
    pending_review: number;
    failed: number;
};

export type DashboardPipelineDocument = {
    id: number;
    title: string;
    status: DocumentStatus;
    matter_title: string | null;
    updated_at: string;
};

export type DashboardFailureDocument = DashboardPipelineDocument;

export type PaginatedData<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
};

export type QueueHealthQueue = {
    name: string;
    pipeline: string;
    messages: number;
    messages_ready: number;
    messages_unacknowledged: number;
    consumers: number;
    state: string;
    is_dead_letter: boolean;
};

export type QueueHealthSummary = {
    total_messages: number;
    total_ready: number;
    total_unacked: number;
    total_consumers: number;
    dead_letter_messages: number;
};

export type QueueHealthSnapshot = {
    available: boolean;
    generated_at: string;
    queues: QueueHealthQueue[];
    summary: QueueHealthSummary;
    error: string | null;
};
