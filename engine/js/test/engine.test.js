import { test } from 'node:test';
import assert from 'node:assert/strict';
import { evaluate } from '../src/engine.js';
import { loadCases } from './fixtures.js';

const PRICE_KEYS = ['unit_cents', 'total_cents', 'options_cents', 'formula_cents', 'markup_percent', 'base_cents'];

for (const c of loadCases()) {
  test(`${c.file}: ${c.name}`, () => {
    const r = evaluate(c.definition, c.answers || {}, c.context || {});
    const e = c.expect || {};
    if ('valid' in e) assert.equal(r.valid, e.valid, 'valid ' + JSON.stringify(r.errors));
    for (const k of PRICE_KEYS) if (k in e) assert.equal(r.price[k], e[k], k);
    if (e.errors) assert.deepEqual(r.errors.map((x) => x.code), e.errors);
    if ('variant_id' in e) assert.equal(r.variant_id, e.variant_id);
    if (e.visible_steps) assert.deepEqual(r.visible_steps, e.visible_steps);
    for (const [k, v] of Object.entries(e.answers || {})) assert.deepEqual(r.answers[k], v, 'answer ' + k);
    for (const k of e.absent_answers || []) assert.ok(!(k in r.answers), 'answer ' + k + ' should be absent');
    for (const [f, opts] of Object.entries(e.hidden_options || {})) for (const o of opts) assert.equal(r.fields[f].options[o].visible, false, f + ':' + o);
    for (const [f, opts] of Object.entries(e.disabled_options || {})) for (const o of opts) assert.equal(r.fields[f].options[o].disabled, true, f + ':' + o + ' disabled');
    for (const [f, opts] of Object.entries(e.enabled_options || {})) for (const o of opts) assert.equal(r.fields[f].options[o].disabled, false, f + ':' + o + ' enabled');
    if (e.layers) assert.deepEqual(r.layers.map((l) => l.id), e.layers);
    for (const [id, img] of Object.entries(e.layer_images || {})) assert.equal(r.layers.find((l) => l.id === id)?.image, img);
  });
}
