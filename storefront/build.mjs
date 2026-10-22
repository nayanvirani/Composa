// Builds the storefront bundles into the theme app extension's assets folder.
//   composa.js     app embed loader, loaded on every page (budget 10 KB, Shopify's limit for embeds)
//   composa-ui.js  configurator UI, loaded on first open (budget 60 KB gzipped)
//   composa.css    styles
import { build } from 'esbuild';
import { gzipSync } from 'node:zlib';
import { readFileSync, statSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.dirname(fileURLToPath(import.meta.url));
const out = path.resolve(root, '../extensions/composa-theme/assets');
const watch = process.argv.includes('--watch');

const common = {
  bundle: true,
  minify: !watch,
  target: ['es2019', 'safari13'],
  legalComments: 'none',
  logLevel: 'info',
};

await build({ ...common, entryPoints: [path.join(root, 'src/loader.js')], outfile: path.join(out, 'composa.js'), format: 'iife' });
await build({
  ...common,
  entryPoints: [path.join(root, 'src/ui/app.jsx')],
  outfile: path.join(out, 'composa-ui.js'),
  format: 'iife',
  jsx: 'automatic',
  jsxImportSource: 'preact',
  alias: { '@engine': path.resolve(root, '../engine/js/src/index.js'), react: 'preact/compat' },
});
await build({ ...common, entryPoints: [path.join(root, 'src/ui/composa.css')], outfile: path.join(out, 'composa.css') });

const budgets = { 'composa.js': { raw: 10 * 1024 }, 'composa-ui.js': { gzip: 60 * 1024 }, 'composa.css': { gzip: 12 * 1024 } };
let failed = false;
for (const [file, budget] of Object.entries(budgets)) {
  const p = path.join(out, file);
  const raw = statSync(p).size;
  const gz = gzipSync(readFileSync(p)).length;
  const over = (budget.raw && raw > budget.raw) || (budget.gzip && gz > budget.gzip);
  console.log(`${over ? '✗' : '✓'} ${file}: ${(raw / 1024).toFixed(1)} KB raw, ${(gz / 1024).toFixed(1)} KB gzip`);
  failed ||= over;
}
if (failed) {
  console.error('Storefront bundle over budget.');
  process.exit(1);
}
