// Storefront helpers: App Proxy calls, money in the shopper's currency, translations, analytics.

export const proxyBase = (settings) => (settings && settings.proxy) || '/apps/composa';

export async function proxy(settings, path, body) {
  const res = await fetch(proxyBase(settings) + '/' + path, {
    method: body ? 'POST' : 'GET',
    headers: { Accept: 'application/json', ...(body ? { 'Content-Type': 'application/json' } : {}) },
    body: body ? JSON.stringify(body) : undefined,
    credentials: 'same-origin',
  });
  const data = res.headers.get('content-type')?.includes('json') ? await res.json() : null;
  if (!res.ok) {
    const msg = data?.message || Object.values(data?.errors || {}).flat()[0] || 'Something went wrong. Please try again.';
    throw new Error(msg);
  }
  return data;
}

/** Shopify Markets: prices are defined in shop currency and shown in the shopper's currency. */
export function currencyInfo(currency) {
  const rate = Number(currency?.rate) || 1;
  const code = currency?.active || 'USD';
  return { rate, code };
}

export function formatMoney(cents, info, locale) {
  const major = ((Number(cents) || 0) * info.rate) / 100;
  try {
    return new Intl.NumberFormat(locale || undefined, { style: 'currency', currency: info.code }).format(major);
  } catch (e) {
    return info.code + ' ' + major.toFixed(2);
  }
}

const UI = {
  'ui.next': 'Next', 'ui.back': 'Back', 'ui.add_to_cart': 'Add to cart', 'ui.review': 'Review', 'ui.price_breakdown': 'Price breakdown',
  'ui.total': 'Total', 'ui.quantity': 'Quantity', 'ui.edit': 'Edit', 'ui.required': 'Please choose an option', 'ui.sold_out': 'Sold out',
  'ui.unavailable': 'Unavailable', 'ui.added': 'Added to your cart', 'ui.upload': 'Upload a file', 'ui.add_another': 'Add another',
  'ui.remove': 'Remove', 'ui.customize': 'Customize', 'ui.view_cart': 'View cart', 'ui.design_another': 'Design another', 'ui.close': 'Close',
  'ui.step_of': 'Step {n} of {total}', 'ui.included': 'Included', 'ui.uploading': 'Uploading…',
};

export const ERRORS = {
  required: 'Please complete this.', invalid_option: 'Choose one of the options.', max_length: 'Too long.', min_length: 'Too short.', pattern: 'Contains characters that aren’t allowed.',
  min: 'Too small.', max: 'Too large.', min_w: 'Width is too small.', max_w: 'Width is too large.', min_h: 'Height is too small.', max_h: 'Height is too large.',
  min_choices: 'Choose more options.', max_choices: 'Choose fewer options.', variant_unavailable: 'This combination is unavailable.', formula_error: 'This combination can’t be priced.', price_table_missing: 'This combination can’t be priced.',
};

/** Translation lookup with fallback to the definition's default text. */
export function translator(definition, locale) {
  const table = (definition.translations && definition.translations[locale]) || {};
  return (key, fallback, vars) => {
    let s = table[key] || fallback || UI[key] || key;
    if (vars) Object.entries(vars).forEach(([k, v]) => (s = s.replace('{' + k + '}', v)));
    return s;
  };
}

/** Batched analytics beacon: opens, steps reached, choices, load time. */
export function tracker(settings, configuratorId) {
  const session = (crypto.randomUUID && crypto.randomUUID()) || String(Math.random()).slice(2);
  let queue = [];
  let timer = null;
  const flush = () => {
    if (!queue.length) return;
    const body = JSON.stringify({ configurator_id: configuratorId, session, events: queue.splice(0, 50) });
    const url = proxyBase(settings) + '/events';
    if (!(navigator.sendBeacon && navigator.sendBeacon(url, new Blob([body], { type: 'application/json' })))) {
      fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body, keepalive: true }).catch(() => {});
    }
  };
  addEventListener('pagehide', flush);
  return {
    session,
    track(type, step, value, meta) {
      queue.push({ type, step, value, meta });
      clearTimeout(timer);
      timer = setTimeout(flush, 1500);
    },
    flush,
  };
}

/** Product JSON from Liquid (presentment cents) → engine variants (shop cents). */
export function engineVariants(product, rate) {
  if (!product || !product.variants || product.variants.length < 2) return null;
  const names = (product.options || []).map((o) => (typeof o === 'string' ? o : o.name));
  return product.variants.map((v) => ({
    id: 'gid://shopify/ProductVariant/' + v.id,
    price_cents: Math.round(Number(v.price) / (rate || 1)),
    available: v.available !== false,
    options: Object.fromEntries(names.map((n, i) => [n, v['option' + (i + 1)]])),
  }));
}

export function numericId(gid) {
  return String(gid || '').split('/').pop();
}
