// Writes the browser engine's full output for every fixture to tests/engine/snapshots.json.
// tests/Unit/Engine/EngineParityTest.php checks the PHP engine returns exactly the same, so the
// price shoppers see always matches the price the server signs. Run after changing fixtures:
//   npm --prefix engine/js run snapshot
import { writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { evaluate } from '../src/engine.js';
import { loadCases, FIXTURE_DIR } from '../test/fixtures.js';

const out = {};
for (const c of loadCases()) out[c.file + ' :: ' + c.name] = evaluate(c.definition, c.answers || {}, c.context || {});
writeFileSync(join(FIXTURE_DIR, '../snapshots.json'), JSON.stringify(out, null, 2) + '\n');
console.log('Wrote ' + Object.keys(out).length + ' snapshots');
