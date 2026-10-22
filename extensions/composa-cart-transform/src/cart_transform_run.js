// Composa Cart Transform. For every cart line carrying a Composa configuration it checks the HMAC
// signature written by the app server and, only if it matches, sets the configured price:
//   update  → lineUpdate (Shopify Plus and development stores)
//   expand  → lineExpand into the line's own variant, plus linked add-on variants (all plans)
//   discount→ no operation; the Composa discount function lowers the price instead
// Unsigned or tampered lines are left at the variant's normal price.
import { decodeComponents, message, verify } from './signature.js';

const NO_CHANGES = { operations: [] };

const attr = (line, key) => (line[key] && line[key].value) || null;

/** Shop-currency cents → presentment amount string. */
function amount(cents, rate) {
  return ((cents / 100) * rate).toFixed(2);
}

function intOf(v) {
  return /^\d+$/.test(String(v || '')) ? Number(v) : null;
}

/** @param {import('../generated/api').CartTransformRunInput} input */
export function cartTransformRun(input) {
  const config = input?.cartTransform?.metafield?.jsonValue;
  if (!config || !config.secret || !config.shop || config.mode === 'discount') return NO_CHANGES;
  const rate = Number(input.presentmentCurrencyRate) || 1;
  const operations = [];

  for (const line of input.cart?.lines || []) {
    const configId = attr(line, 'configId');
    if (!configId || line.merchandise?.__typename !== 'ProductVariant') continue;
    const price = intOf(attr(line, 'price'));
    const base = intOf(attr(line, 'base'));
    const currency = attr(line, 'currency');
    if (price === null || base === null || !currency) continue;

    const components = config.mode === 'expand' ? decodeComponents(attr(line, 'linked')) : [];
    const msg = message(config.shop, configId, price, base, currency, components);
    if (!verify(config.secret, msg, attr(line, 'signature'))) continue;

    const title = attr(line, 'title');
    const image = attr(line, 'image');
    const extras = {};
    if (title) extras.title = title.slice(0, 255);
    if (image && /^https:\/\//.test(image)) extras.image = { url: image };

    if (config.mode === 'update') {
      operations.push({
        lineUpdate: { cartLineId: line.id, price: { adjustment: { fixedPricePerUnit: { amount: amount(price, rate) } } }, ...extras },
      });
      continue;
    }

    const linked = components.reduce((sum, c) => sum + c.cents, 0);
    if (linked > price) continue;
    const items = [
      { merchandiseId: line.merchandise.id, quantity: 1, price: { adjustment: { fixedPricePerUnit: { amount: amount(price - linked, rate) } } } },
      ...components.map((c) => ({
        merchandiseId: 'gid://shopify/ProductVariant/' + c.variant_id,
        quantity: 1,
        price: { adjustment: { fixedPricePerUnit: { amount: amount(c.cents, rate) } } },
      })),
    ];
    operations.push({ lineExpand: { cartLineId: line.id, expandedCartItems: items, ...extras } });
  }

  return { operations };
}
