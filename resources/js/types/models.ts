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
    | 'scan_failed'
    | 'extraction_failed'
    | 'classification_failed';

export type Document = {
    id: number;
    tenant_id: string;
    matter_id: number;
    uploaded_by: number | null;
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
};

export type DocumentActivity = {
    id: number;
    action: string;
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
