import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { evaluate, parse, variablesOf, FormulaError } from '../src/formula.js';
import { FIXTURE_DIR } from './fixtures.js';

const cases = JSON.parse(readFileSync(join(FIXTURE_DIR, '../formula-cases.json'), 'utf8'));

for (const c of cases) {
  test('formula: ' + c.expr, () => {
    if (c.error) {
      assert.throws(() => evaluate(c.expr, c.vars || {}), FormulaError);
    } else {
      assert.equal(evaluate(c.expr, c.vars || {}), c.value);
    }
  });
}

test('formula: lists the variables it reads', () => {
  assert.deepEqual([...variablesOf(parse('round(weight_g * metal_rate, 2) + if(rush, 10, 0)'))].sort(), ['metal_rate', 'rush', 'weight_g']);
});
