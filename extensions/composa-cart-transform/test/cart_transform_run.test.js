import { test } from 'node:test';
import assert from 'node:assert/strict';
import { cartTransformRun } from '../src/cart_transform_run.js';
import { message, sign } from '../src/signature.js';

const SHOP = 'maison-oro.myshopify.com';
const SECRET = 'test-secret';

function line({ id = 'gid://shopify/CartLine/1', price = 210050, base = 95000, cur = 'USD', lnk = null, tamper = false, title = 'Custom ring', img = 'https://cdn.example.com/x.png' } = {}) {
  const components = lnk ? lnk.split(',').map((p) => ({ variant_id: p.split(':')[0], cents: Number(p.split(':')[1]) })) : [];
  const sig = sign(SECRET, message(SHOP, 'cfg_1', price, base, cur, components));
  return {
    id,
    quantity: 2,
    merchandise: { __typename: 'ProductVariant', id: 'gid://shopify/ProductVariant/500' },
    configId: { value: 'cfg_1' },
    price: { value: String(tamper ? price - 100000 : price) },
    base: { value: String(base) },
    currency: { value: cur },
    signature: { value: sig },
    linked: lnk ? { value: lnk } : null,
    title: { value: title },
    image: { value: img },
  };
}

const input = (mode, lines, rate = '1.0') => ({
  presentmentCurrencyRate: rate,
  cart: { lines },
  cartTransform: { metafield: { jsonValue: { v: 1, shop: SHOP, secret: SECRET, mode } } },
});

test('update mode sets the signed price', () => {
  const out = cartTransformRun(input('update', [line()]));
  assert.deepEqual(out.operations, [{
    lineUpdate: { cartLineId: 'gid://shopify/CartLine/1', price: { adjustment: { fixedPricePerUnit: { amount: '2100.50' } } }, title: 'Custom ring', image: { url: 'https://cdn.example.com/x.png' } },
  }]);
});

test('expand mode uses the own variant and linked add-ons, converted to presentment currency', () => {
  const out = cartTransformRun(input('expand', [line({ price: 100000, lnk: '4711:20000' })], '0.5'));
  const items = out.operations[0].lineExpand.expandedCartItems;
  assert.deepEqual(items.map((i) => [i.merchandiseId, i.price.adjustment.fixedPricePerUnit.amount]), [
    ['gid://shopify/ProductVariant/500', '400.00'],
    ['gid://shopify/ProductVariant/4711', '100.00'],
  ]);
});

test('tampered, unsigned and foreign lines are left alone', () => {
  const plain = { id: 'gid://shopify/CartLine/9', quantity: 1, merchandise: { __typename: 'ProductVariant', id: 'gid://shopify/ProductVariant/1' } };
  const out = cartTransformRun(input('update', [line({ tamper: true }), plain]));
  assert.deepEqual(out.operations, []);
});

test('linked components can not be added after signing', () => {
  const l = line({ price: 100000 });
  l.linked = { value: '4711:1' };
  assert.deepEqual(cartTransformRun(input('expand', [l])).operations, []);
});

test('discount mode and missing config do nothing', () => {
  assert.deepEqual(cartTransformRun(input('discount', [line()])).operations, []);
  assert.deepEqual(cartTransformRun({ cart: { lines: [line()] }, cartTransform: { metafield: null } }).operations, []);
});

test('non-https images are not passed to checkout', () => {
  const out = cartTransformRun(input('update', [line({ img: 'javascript:alert(1)' })]));
  assert.equal(out.operations[0].lineUpdate.image, undefined);
});
