export function money(cents, currency = 'USD') {
    try {
        return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format((Number(cents) || 0) / 100);
    } catch {
        return ((Number(cents) || 0) / 100).toFixed(2) + ' ' + currency;
    }
}

export function date(iso, opts = { month: 'short', day: 'numeric', year: 'numeric' }) {
    if (!iso) return '—';
    return new Intl.DateTimeFormat(undefined, opts).format(new Date(iso));
}

export function relative(iso) {
    if (!iso) return '';
    const s = Math.round((Date.now() - new Date(iso).getTime()) / 1000);
    if (s < 60) return 'just now';
    if (s < 3600) return Math.round(s / 60) + ' min ago';
    if (s < 86400) return Math.round(s / 3600) + ' h ago';
    return date(iso);
}

export const slug = (s) => String(s || '').toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 40) || 'item';

export function uniqueKey(base, taken) {
    let key = /^[a-z]/.test(base) ? base : 'f_' + base;
    for (let i = 2; taken.includes(key); i++) key = base + '_' + i;
    return key;
}

export const LIMIT_LABELS = {
    configurators: 'Published configurators',
    drafts: 'Draft configurators',
    steps: 'Steps (largest configurator)',
    options: 'Options per field (largest)',
    rules: 'Rules (largest configurator)',
    products: 'Assigned products',
    storage_mb: 'File storage (MB)',
    languages: 'Languages',
};
