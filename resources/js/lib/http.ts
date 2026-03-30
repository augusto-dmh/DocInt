type JsonRequestOptions = {
    method?: 'POST' | 'DELETE';
    body?: unknown;
};

export class HttpError extends Error {
    status: number;
    errors: Record<string, string[]> | null;

    constructor(
        message: string,
        status: number,
        errors: Record<string, string[]> | null = null,
    ) {
        super(message);
        this.name = 'HttpError';
        this.status = status;
        this.errors = errors;
    }
}

function csrfToken(): string | null {
    const token = document
        .querySelector('meta[name=\"csrf-token\"]')
        ?.getAttribute('content');

    return token ?? null;
}

export async function requestJson<T>(
    url: string,
    options: JsonRequestOptions = {},
): Promise<T> {
    const headers = new Headers({
        Accept: 'application/json',
    });

    const token = csrfToken();

    if (token !== null) {
        headers.set('X-CSRF-TOKEN', token);
    }

    if (options.body !== undefined) {
        headers.set('Content-Type', 'application/json');
    }

    const response = await fetch(url, {
        method: options.method ?? 'POST',
        headers,
        credentials: 'same-origin',
        body:
            options.body !== undefined
                ? JSON.stringify(options.body)
                : undefined,
    });

    const contentType = response.headers.get('content-type') ?? '';
    const payload = contentType.includes('application/json')
        ? await response.json()
        : null;

    if (!response.ok) {
        const errors =
            payload !== null &&
            typeof payload === 'object' &&
            'errors' in payload &&
            typeof payload.errors === 'object'
                ? (payload.errors as Record<string, string[]>)
                : null;

        const message =
            payload !== null &&
            typeof payload === 'object' &&
            'message' in payload &&
            typeof payload.message === 'string'
                ? payload.message
                : `Request failed with status ${response.status}.`;

        throw new HttpError(message, response.status, errors);
    }

    return payload as T;
}
