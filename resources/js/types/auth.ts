export type User = {
    id: number;
    tenant_id: string | null;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Tenant = {
    id: string;
    name: string;
    slug: string;
    logo_url: string | null;
};

export type TenantContext = {
    canSelect: boolean;
    activeTenantId: string | null;
    activeTenant: Pick<Tenant, 'id' | 'name' | 'slug'> | null;
};

export type RealtimeConfig = {
    enabled: boolean;
    broadcaster: string;
    appKey: string | null;
    host: string | null;
    port: number | null;
    scheme: string | null;
    channels: {
        tenantDocumentsPattern: string;
        documentPattern: string;
    };
};

export type DocumentStatusUpdatedPayload = {
    tenant_id: string;
    document_id: number;
    from_status: string | null;
    to_status: string;
    trace_id: string | null;
    occurred_at: string;
};

export type Auth = {
    user: User;
    roles: string[];
    permissions: string[];
    isSuperAdmin: boolean;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
