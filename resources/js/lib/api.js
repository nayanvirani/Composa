// JSON calls to /app/api/*. App Bridge adds the session token to same-origin fetch requests;
// we also attach it explicitly so calls work in every browser.

export class ApiError extends Error {
    constructor(message, status, data) {
        super(message);
        this.status = status;
        this.data = data;
    }
}

async function token() {
    try {
        return window.shopify?.idToken ? await window.shopify.idToken() : null;
    } catch {
        return null;
    }
}

export async function api(path, { method = 'GET', body, query, raw = false } = {}) {
    const url = new URL('/app/api/' + path.replace(/^\//, ''), window.location.origin);
    Object.entries(query || {}).forEach(([k, v]) => v !== undefined && v !== null && v !== '' && url.searchParams.set(k, v));
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    const t = await token();
    if (t) headers.Authorization = 'Bearer ' + t;
    let payload = body;
    if (body && !(body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
        payload = JSON.stringify(body);
    }
    const res = await fetch(url, { method, headers, body: payload });
    if (raw) return res;
    const data = res.headers.get('content-type')?.includes('json') ? await res.json() : null;
    if (!res.ok) {
        const message = data?.message || Object.values(data?.errors || {})[0]?.[0] || 'Something went wrong (' + res.status + ')';
        throw new ApiError(message, res.status, data);
    }
    return data;
}

export function toast(message, isError = false) {
    if (window.shopify?.toast) window.shopify.toast.show(message, { isError, duration: isError ? 5000 : 3000 });
}

/** Downloads a file returned by the API (PDFs, CSV). */
export async function download(path, filename) {
    const res = await api(path, { raw: true });
    if (!res.ok) throw new ApiError('Download failed', res.status);
    const blob = await res.blob();
    const a = Object.assign(document.createElement('a'), { href: URL.createObjectURL(blob), download: filename });
    a.click();
    URL.revokeObjectURL(a.href);
}

/** Opens a page in the top-level Shopify admin (theme editor, plan page, orders). */
export function openTop(url) {
    window.open(url, '_top');
}
