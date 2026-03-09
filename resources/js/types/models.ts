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

export type DocumentStatus = 'uploaded' | 'ready_for_review' | 'approved';

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
    created_at: string;
    updated_at: string;
    matter?: Matter;
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
