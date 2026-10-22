import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createHmac, createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { sha256, utf8, hmacSha256Hex } from '../src/sha256.js';
import { decodeComponents, message, sign, verify } from '../src/signature.js';

const vectors = JSON.parse(readFileSync(new URL('../../../tests/engine/signing-vectors.json', import.meta.url)));

test('sha256 matches node crypto', () => {
  for (const s of ['', 'abc', 'a'.repeat(55), 'a'.repeat(56), 'a'.repeat(64), 'a'.repeat(1000), 'héllo ✓ 🎉']) {
    const ours = Array.from(sha256(utf8(s)), (b) => b.toString(16).padStart(2, '0')).join('');
    assert.equal(ours, createHash('sha256').update(s, 'utf8').digest('hex'), JSON.stringify(s.slice(0, 20)));
  }
});

test('hmac matches node crypto, including long keys', () => {
  for (const key of ['k', 'test-secret-0123456789', 'x'.repeat(100)]) {
    assert.equal(hmacSha256Hex(key, 'v1|a|b'), createHmac('sha256', key).update('v1|a|b').digest('hex'));
  }
});

test('messages match the shared vectors', () => {
  for (const v of vectors) {
    assert.equal(message(v.shop, v.config_id, v.price_cents, v.base_cents, v.currency, v.components), v.message);
    const sig = createHmac('sha256', v.secret).update(v.message).digest('hex');
    assert.equal(sign(v.secret, v.message), sig);
    assert.ok(verify(v.secret, v.message, sig.toUpperCase()));
    assert.ok(!verify(v.secret, v.message.replace('|', '||'), sig));
    assert.ok(!verify('wrong', v.message, sig));
  }
});

test('decodes linked components and drops junk', () => {
  assert.deepEqual(decodeComponents('4711:78000,99:1250'), [{ variant_id: '4711', cents: 78000 }, { variant_id: '99', cents: 1250 }]);
  assert.deepEqual(decodeComponents('x:1,5:-3,6:1.5,,7:10'), [{ variant_id: '7', cents: 10 }]);
  assert.deepEqual(decodeComponents(null), []);
});
