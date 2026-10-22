// Composa discount fallback, used only when a store can't run the Cart Transform (cart mode
// "discount"). The base variant is listed at its highest price; for every correctly signed line
// this lowers the unit price to the configured price. It never raises a price.
import { message, verify } from './signature.js';

const NO_CHANGES = { operations: [] };
const attr = (line, key) => (line[key] && line[key].value) || null;
const intOf = (v) => (/^\d+$/.test(String(v || '')) ? Number(v) : null);

export function cartLinesDiscountsGenerateRun(input) {
  const config = input?.discount?.metafield?.jsonValue;
  if (!config || !config.secret || !config.shop) return NO_CHANGES;
  if (!(input.discount.discountClasses || []).includes('PRODUCT')) return NO_CHANGES;
  const rate = Number(input.presentmentCurrencyRate) || 1;
  const candidates = [];

  for (const line of input.cart?.lines || []) {
    const configId = attr(line, 'configId');
    const price = intOf(attr(line, 'price'));
    const base = intOf(attr(line, 'base'));
    const currency = attr(line, 'currency');
    if (!configId || price === null || base === null || !currency) continue;
    if (!verify(config.secret, message(config.shop, configId, price, base, currency), attr(line, 'signature'))) continue;

    const listed = Number(line.cost?.amountPerQuantity?.amount || 0);
    const target = (price / 100) * rate;
    const off = Math.round((listed - target) * 100) / 100;
    if (off <= 0) continue;
    candidates.push({
      message: config.message || 'Configured price',
      targets: [{ cartLine: { id: line.id } }],
      value: { fixedAmount: { amount: off.toFixed(2), appliesToEachItem: true } },
    });
  }

  if (!candidates.length) return NO_CHANGES;
  return { operations: [{ productDiscountsAdd: { candidates, selectionStrategy: 'ALL' } }] };
}
