// Loads the shared engine fixtures (tests/engine/fixtures/*.json) and resolves "@id" references
// so a case can reuse another case's definition or context.
import { readdirSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

export const FIXTURE_DIR = join(dirname(fileURLToPath(import.meta.url)), '../../../tests/engine/fixtures');

export function loadCases() {
  const cases = [];
  for (const file of readdirSync(FIXTURE_DIR).filter((f) => f.endsWith('.json')).sort()) {
    for (const c of JSON.parse(readFileSync(join(FIXTURE_DIR, file), 'utf8'))) cases.push({ ...c, file });
  }
  const byId = Object.fromEntries(cases.filter((c) => c.id).map((c) => [c.id, c]));
  const resolve = (value, key) => {
    if (typeof value !== 'string' || !value.startsWith('@')) return value;
    const ref = byId[value.slice(1)];
    if (!ref) throw new Error('Unknown fixture reference ' + value);
    return resolve(ref[key], key);
  };
  return cases.map((c) => ({ ...c, definition: resolve(c.definition, 'definition'), context: resolve(c.context ?? {}, 'context') }));
}
