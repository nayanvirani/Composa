import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { cartLinesDiscountsGenerateRun as run } from '../src/cart_lines_discounts_generate_run.js';
import { message, sign } from '../src/signature.js';

const SHOP = 's.myshopify.com';
const line = (listed, price, sig) => ({
  id: 'gid://shopify/CartLine/1',
  quantity: 1,
  cost: { amountPerQuantity: { amount: String(listed) } },
  configId: { value: 'cfg_1' },
  price: { value: String(price) },
  base: { value: '1000' },
  currency: { value: 'USD' },
  signature: { value: sig ?? sign('k', message(SHOP, 'cfg_1', price, 1000, 'USD')) },
});
const input = (lines, rate = '1') => ({ presentmentCurrencyRate: rate, cart: { lines }, discount: { discountClasses: ['PRODUCT'], metafield: { jsonValue: { shop: SHOP, secret: 'k' } } } });

test('lowers a listed price to the signed price', () => {
  const out = run(input([line(500, 32050)]));
  assert.equal(out.operations[0].productDiscountsAdd.candidates[0].value.fixedAmount.amount, '179.50');
});

test('converts to the presentment currency', () => {
  const out = run(input([line(250, 32050)], '0.5'));
  assert.equal(out.operations[0].productDiscountsAdd.candidates[0].value.fixedAmount.amount, '89.75');
});

test('never raises a price and ignores bad signatures', () => {
  assert.deepEqual(run(input([line(100, 32050)])).operations, []);
  assert.deepEqual(run(input([line(500, 32050, 'bad')])).operations, []);
});

test('signature code is identical to the cart transform copy', () => {
  for (const f of ['sha256.js', 'signature.js']) {
    assert.equal(readFileSync(new URL('../src/' + f, import.meta.url), 'utf8'), readFileSync(new URL('../../composa-cart-transform/src/' + f, import.meta.url), 'utf8'), f);
  }
});
