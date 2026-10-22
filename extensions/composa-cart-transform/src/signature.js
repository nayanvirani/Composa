// Twin of app/Services/ConfigurationSigner.php. Message:
//   v1|{shop}|{config_id}|{price_cents}|{base_cents}|{CURRENCY}|{variantId:cents,...}
import { hmacSha256Hex } from './sha256.js';

export const numericId = (gid) => String(gid).split('/').pop();

export function message(shop, configId, priceCents, baseCents, currency, components = []) {
  const parts = components.map((c) => numericId(c.variant_id) + ':' + Math.trunc(Number(c.cents)));
  return ['v1', shop, configId, priceCents, baseCents, String(currency).toUpperCase(), parts.join(',')].join('|');
}

export const sign = (secret, msg) => hmacSha256Hex(secret, msg);

/** Constant-time comparison of two hex strings. */
export function verify(secret, msg, signature) {
  const expected = sign(secret, msg);
  const given = String(signature || '').toLowerCase();
  if (given.length !== expected.length) return false;
  let diff = 0;
  for (let i = 0; i < expected.length; i++) diff |= expected.charCodeAt(i) ^ given.charCodeAt(i);
  return diff === 0;
}

/** "_lnk" line property → [{variant_id, cents}] */
export function decodeComponents(value) {
  if (!value) return [];
  return String(value).split(',').filter(Boolean).map((pair) => {
    const [id, cents] = pair.split(':');
    return { variant_id: id, cents: Number(cents) };
  }).filter((c) => /^\d+$/.test(c.variant_id) && Number.isInteger(c.cents) && c.cents > 0);
}
