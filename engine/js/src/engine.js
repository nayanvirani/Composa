// Composa configurator engine (browser). Evaluates a configurator definition against a shopper's
// answers: rules, defaults, Shopify variant resolution, validation and price. The PHP twin in
// app/Engine/Engine.php signs the final price on the server; both must produce identical output
// for every fixture in tests/engine/fixtures.
//
// Money is handled in integer minor units (cents). Definitions store amounts in major units.

import { evaluate as evalFormula, roundHalfUp, FormulaError } from './formula.js';

const MAX_PASSES = 5;
const CHOICE_TYPES = ['image_tiles', 'swatch', 'buttons', 'dropdown', 'radio'];
const MULTI_TYPES = ['checkbox'];
const own = (o, k) => o != null && Object.prototype.hasOwnProperty.call(o, k);

export const cents = (major) => roundHalfUp(Number(major || 0) * 100);

export function isEmpty(v) {
  return v === undefined || v === null || v === '' || (Array.isArray(v) && v.length === 0);
}

function charCount(text, countSpaces) {
  const s = String(text ?? '');
  const chars = Array.from(countSpaces ? s : s.replace(/\s/gu, ''));
  return chars.length;
}

function toNumber(v) {
  if (typeof v === 'number') return Number.isFinite(v) ? v : 0;
  if (typeof v === 'string' && v.trim() !== '' && !Number.isNaN(Number(v))) return Number(v);
  return 0;
}

function dims(v) {
  const w = toNumber(v && typeof v === 'object' ? v.w : 0);
  const h = toNumber(v && typeof v === 'object' ? v.h : 0);
  return { w, h, area: w * h };
}

// ---- definition helpers -------------------------------------------------------------------

export function fieldsOf(definition) {
  const out = [];
  for (const step of definition.steps || []) {
    for (const field of step.fields || []) out.push({ step, field });
  }
  return out;
}

function findField(definition, key) {
  return fieldsOf(definition).find((x) => x.field.key === key) || null;
}

// ---- conditions ---------------------------------------------------------------------------

function compare(op, actual, expected) {
  const list = Array.isArray(actual) ? actual.map(String) : isEmpty(actual) ? [] : [String(actual)];
  switch (op) {
    case 'eq': return Array.isArray(actual) ? list.includes(String(expected)) : !isEmpty(actual) && String(actual) === String(expected);
    case 'neq': return Array.isArray(actual) ? !list.includes(String(expected)) : isEmpty(actual) || String(actual) !== String(expected);
    case 'in': return (Array.isArray(expected) ? expected : [expected]).map(String).some((e) => list.includes(e));
    case 'not_in': return !(Array.isArray(expected) ? expected : [expected]).map(String).some((e) => list.includes(e));
    case 'contains': return Array.isArray(actual) ? list.includes(String(expected)) : String(actual ?? '').toLowerCase().includes(String(expected).toLowerCase());
    case 'empty': return isEmpty(actual);
    case 'not_empty': return !isEmpty(actual);
    case 'gt': return !isEmpty(actual) && toNumber(actual) > toNumber(expected);
    case 'gte': return !isEmpty(actual) && toNumber(actual) >= toNumber(expected);
    case 'lt': return !isEmpty(actual) && toNumber(actual) < toNumber(expected);
    case 'lte': return !isEmpty(actual) && toNumber(actual) <= toNumber(expected);
    default: return false;
  }
}

export function matches(node, answers, ctx = {}) {
  if (!node) return true;
  if (Array.isArray(node.all)) return node.all.every((n) => matches(n, answers, ctx));
  if (Array.isArray(node.any)) return node.any.some((n) => matches(n, answers, ctx));
  if (node.field === '$quantity') return compare(node.op, ctx.quantity ?? 1, node.value);
  if (node.field === '$country') return compare(node.op, ctx.country ?? '', node.value);
  const value = node.field && own(answers, node.field) ? answers[node.field] : undefined;
  return compare(node.op, value, node.value);
}

// ---- state --------------------------------------------------------------------------------

function baseState(definition) {
  const steps = {};
  const fields = {};
  for (const step of definition.steps || []) {
    steps[step.key] = { visible: !step.hidden_by_default };
    for (const field of step.fields || []) {
      const options = {};
      for (const o of field.options || []) options[String(o.value)] = { visible: !o.hidden, disabled: !!o.disabled };
      fields[field.key] = { visible: !field.hidden_by_default, required: !!field.required, default: field.default ?? null, options };
    }
  }
  return { steps, fields, priceAdds: [], layers: {}, variantSets: {} };
}

function applyAction(state, action) {
  const f = action.field && state.fields[action.field];
  const opt = f && action.option != null ? f.options[String(action.option)] : null;
  switch (action.type) {
    case 'show_step': if (state.steps[action.step]) state.steps[action.step].visible = true; break;
    case 'hide_step': if (state.steps[action.step]) state.steps[action.step].visible = false; break;
    case 'show_field': if (f) f.visible = true; break;
    case 'hide_field': if (f) f.visible = false; break;
    case 'show_option': if (opt) opt.visible = true; break;
    case 'hide_option': if (opt) opt.visible = false; break;
    case 'disable_option': if (opt) opt.disabled = true; break;
    case 'enable_option': if (opt) opt.disabled = false; break;
    case 'require': if (f) f.required = true; break;
    case 'unrequire': if (f) f.required = false; break;
    case 'set_default': if (f) f.default = action.value; break;
    case 'add_price': state.priceAdds.push({ label: action.label || 'Adjustment', amount: action.amount, percent: action.percent }); break;
    case 'set_layer': state.layers[action.layer] = action.image; break;
    case 'set_variant_option': state.variantSets[action.option_name] = action.value; break;
  }
}

function optionUsable(fs, value) {
  const o = fs.options[String(value)];
  return !!o && o.visible && !o.disabled;
}

function clean(definition, raw, state, availability) {
  const out = {};
  for (const step of definition.steps || []) {
    const stepVisible = state.steps[step.key]?.visible;
    if (step.repeatable) {
      if (stepVisible && Array.isArray(raw[step.key])) out[step.key] = raw[step.key].slice(0, step.max_repeats || 10);
      continue;
    }
    for (const field of step.fields || []) {
      const fs = state.fields[field.key];
      if (!stepVisible || !fs.visible) continue;
      let value = own(raw, field.key) ? raw[field.key] : undefined;
      if (field.variant_option && own(state.variantSets, field.variant_option)) value = state.variantSets[field.variant_option];
      if (field.mode === 'fixed') value = field.fixed_value;
      const disabledByStock = (v) => availability[field.key] && availability[field.key][String(v)] === false;
      if (CHOICE_TYPES.includes(field.type)) {
        if (!isEmpty(value) && (!optionUsable(fs, value) || disabledByStock(value))) value = undefined;
      } else if (MULTI_TYPES.includes(field.type)) {
        value = Array.isArray(value) ? value.filter((v) => optionUsable(fs, v)) : isEmpty(value) ? undefined : [value].filter((v) => optionUsable(fs, v));
        if (value && !value.length) value = undefined;
      }
      if (isEmpty(value)) {
        let def = fs.default;
        if (isEmpty(def) && field.options) {
          const flagged = field.options.find((o) => o.default);
          if (flagged) def = flagged.value;
        }
        if (!isEmpty(def)) {
          if (CHOICE_TYPES.includes(field.type) && (!optionUsable(fs, def) || disabledByStock(def))) def = undefined;
          if (MULTI_TYPES.includes(field.type)) def = (Array.isArray(def) ? def : [def]).filter((v) => optionUsable(fs, v));
        }
        value = isEmpty(def) ? undefined : def;
      }
      if (!isEmpty(value)) out[field.key] = value;
    }
  }
  return out;
}

const same = (a, b) => JSON.stringify(sortKeys(a)) === JSON.stringify(sortKeys(b));
function sortKeys(v) {
  if (Array.isArray(v)) return v.map(sortKeys);
  if (v && typeof v === 'object') return Object.keys(v).sort().reduce((o, k) => { o[k] = sortKeys(v[k]); return o; }, {});
  return v;
}

// ---- Shopify variants ---------------------------------------------------------------------

function variantFields(definition) {
  return fieldsOf(definition).filter((x) => x.field.variant_option);
}

// An option value is available when some in-stock variant has it together with the shopper's
// picks for the variant options that come earlier in the flow (like Shopify's own pickers), so a
// sold-out size never knocks out the colour chosen before it.
function variantAvailability(definition, answers, variants) {
  const out = {};
  const vfs = variantFields(definition);
  if (!vfs.length || !Array.isArray(variants) || !variants.length) return out;
  vfs.forEach(({ field }, index) => {
    const earlier = vfs.slice(0, index);
    out[field.key] = {};
    for (const o of field.options || []) {
      out[field.key][String(o.value)] = variants.some((v) => v.available !== false
        && String(v.options?.[field.variant_option]) === String(o.value)
        && earlier.every(({ field: g }) => isEmpty(answers[g.key]) || String(v.options?.[g.variant_option]) === String(answers[g.key])));
    }
  });
  return out;
}

function resolveVariant(definition, answers, variants) {
  const vfs = variantFields(definition);
  if (!vfs.length || !Array.isArray(variants)) return { needed: false, variant: null };
  if (vfs.some(({ field }) => isEmpty(answers[field.key]))) return { needed: true, variant: null };
  const variant = variants.find((v) => vfs.every(({ field }) => String(v.options?.[field.variant_option]) === String(answers[field.key]))) || null;
  return { needed: true, variant };
}

// ---- validation ---------------------------------------------------------------------------

function validateField(field, fs, value, prefix, errors) {
  const key = prefix + field.key;
  const v = field.validation || {};
  if (isEmpty(value)) {
    if (fs.required) errors.push({ field: key, code: 'required' });
    return;
  }
  if (CHOICE_TYPES.includes(field.type) && !(field.options || []).some((o) => String(o.value) === String(value))) errors.push({ field: key, code: 'invalid_option' });
  if (field.type === 'text') {
    const len = Array.from(String(value)).length;
    if (v.max_length != null && len > v.max_length) errors.push({ field: key, code: 'max_length' });
    if (v.min_length != null && len < v.min_length) errors.push({ field: key, code: 'min_length' });
    if (v.pattern) {
      let re = null;
      try { re = new RegExp(v.pattern, 'u'); } catch (e) { re = null; }
      if (re && !re.test(String(value))) errors.push({ field: key, code: 'pattern' });
    }
  }
  if (field.type === 'number') {
    const n = toNumber(value);
    if (v.min != null && n < v.min) errors.push({ field: key, code: 'min' });
    if (v.max != null && n > v.max) errors.push({ field: key, code: 'max' });
  }
  if (field.type === 'dimensions') {
    const d = dims(value);
    for (const side of ['w', 'h']) {
      if (v['min_' + side] != null && d[side] < v['min_' + side]) errors.push({ field: key, code: 'min_' + side });
      if (v['max_' + side] != null && d[side] > v['max_' + side]) errors.push({ field: key, code: 'max_' + side });
    }
  }
  if (MULTI_TYPES.includes(field.type)) {
    const n = Array.isArray(value) ? value.length : 1;
    if (v.min_choices != null && n < v.min_choices) errors.push({ field: key, code: 'min_choices' });
    if (v.max_choices != null && n > v.max_choices) errors.push({ field: key, code: 'max_choices' });
  }
}

// ---- pricing ------------------------------------------------------------------------------

function formulaVars(definition, answers, extra) {
  const vars = { ...(definition.settings?.variables || {}) };
  for (const { field } of fieldsOf(definition)) {
    const value = answers[field.key];
    let n = 0;
    if (field.type === 'number') n = toNumber(value);
    else if (field.type === 'dimensions') { const d = dims(value); n = d.area; vars[field.key + '_w'] = d.w; vars[field.key + '_h'] = d.h; }
    else if (field.type === 'text') n = charCount(value, true);
    else if (MULTI_TYPES.includes(field.type)) n = Array.isArray(value) ? value.length : 0;
    else if (CHOICE_TYPES.includes(field.type)) n = toNumber(value);
    vars[field.key] = n;
    const selected = Array.isArray(value) ? value : isEmpty(value) ? [] : [value];
    for (const o of field.options || []) {
      if (selected.map(String).includes(String(o.value))) Object.assign(vars, o.vars || {});
    }
  }
  return Object.assign(vars, extra);
}

function ruleAmount(rule, field, value, ctx, errors) {
  if (!rule || !rule.type) return { cents: 0, percent: 0 };
  switch (rule.type) {
    case 'fixed': return { cents: cents(rule.amount), percent: 0 };
    case 'percent': return { cents: 0, percent: toNumber(rule.percent) };
    case 'per_unit': {
      const units = rule.basis === 'area' ? dims(value).area : toNumber(value);
      return { cents: cents(toNumber(rule.amount) * units / (toNumber(rule.divisor) || 1)), percent: 0 };
    }
    case 'per_char': return { cents: cents(toNumber(rule.amount) * charCount(value, !!rule.count_spaces)), percent: 0 };
    case 'tiered': {
      const n = rule.basis === 'value' ? toNumber(value) : ctx.quantity;
      const tier = (rule.tiers || []).find((t) => n >= toNumber(t.min) && (t.max == null || n <= toNumber(t.max)));
      if (!tier) return { cents: 0, percent: 0 };
      return { cents: cents(toNumber(tier.amount) * (rule.basis === 'value' ? n : 1)), percent: 0 };
    }
    case 'table': {
      const table = ctx.definition.tables?.[rule.table];
      const row = ctx.answers[rule.row_field ?? table?.row_field];
      const col = ctx.answers[rule.col_field ?? table?.col_field];
      const cell = table?.values?.[String(row)]?.[String(col)];
      if (cell == null) { errors.push({ field: field ? field.key : null, code: 'price_table_missing' }); return { cents: 0, percent: 0 }; }
      return { cents: cents(cell), percent: 0 };
    }
    case 'formula': {
      try {
        return { cents: cents(evalFormula(rule.expression, ctx.vars)), percent: 0 };
      } catch (e) {
        errors.push({ field: field ? field.key : null, code: 'formula_error' });
        return { cents: 0, percent: 0 };
      }
    }
    default: return { cents: 0, percent: 0 };
  }
}

function applyRounding(unit, rounding) {
  if (!rounding || !rounding.mode || rounding.mode === 'none') return unit;
  if (rounding.mode === 'ending') {
    const end = cents(rounding.ending ?? 0.99);
    let v = Math.floor(unit / 100) * 100 + end;
    if (v < unit) v += 100;
    return v;
  }
  const step = Math.max(1, cents(rounding.step ?? 1));
  if (rounding.mode === 'up') return Math.ceil(unit / step) * step;
  if (rounding.mode === 'down') return Math.floor(unit / step) * step;
  return Math.floor(unit / step + 0.5) * step;
}

function priceOf(definition, answers, state, base, quantity, ctx, errors) {
  const lines = [{ label: 'Base', cents: base }];
  let options = 0;
  let percent = 0;
  const components = [];
  const priceCtx = { definition, answers, quantity, vars: {} };
  priceCtx.vars = formulaVars(definition, answers, { base: base / 100, quantity, options: 0, subtotal: 0 });

  const priceField = (field, value, label, scope) => {
    const add = (r, what) => {
      if (r.cents) { options += r.cents; lines.push({ label: what, cents: r.cents }); }
      if (r.percent) { percent += r.percent; lines.push({ label: what, percent: r.percent }); }
    };
    if (field.price_rule) add(ruleAmount(field.price_rule, field, value, { ...priceCtx, answers: scope }, errors), label);
    const selected = Array.isArray(value) ? value : isEmpty(value) ? [] : [value];
    for (const o of field.options || []) {
      if (!selected.map(String).includes(String(o.value))) continue;
      const r = ruleAmount(o.price_rule, field, value, { ...priceCtx, answers: scope }, errors);
      add(r, label + ' · ' + (o.label ?? o.value));
      if (o.linked_variant_id) components.push({ variant_id: o.linked_variant_id, cents: r.cents, quantity: 1 });
    }
  };

  for (const step of definition.steps || []) {
    if (!state.steps[step.key]?.visible) continue;
    if (step.repeatable) {
      (answers[step.key] || []).forEach((item, i) => {
        for (const field of step.fields || []) {
          if (!isEmpty(item?.[field.key])) priceField(field, item[field.key], (field.label || field.key) + ' #' + (i + 1), item);
        }
      });
      continue;
    }
    for (const field of step.fields || []) {
      if (!state.fields[field.key].visible || isEmpty(answers[field.key])) continue;
      if (field.variant_option) continue;
      priceField(field, answers[field.key], field.label || field.key, answers);
    }
  }

  for (const rule of definition.price_rules || []) {
    if (!matches(rule.when, answers, ctx)) continue;
    const r = ruleAmount(rule, null, null, priceCtx, errors);
    if (r.cents) { options += r.cents; lines.push({ label: rule.label || 'Price rule', cents: r.cents }); }
    if (r.percent) { percent += r.percent; lines.push({ label: rule.label || 'Price rule', percent: r.percent }); }
  }
  for (const add of state.priceAdds) {
    if (add.percent != null) { percent += toNumber(add.percent); lines.push({ label: add.label, percent: toNumber(add.percent) }); }
    else { const c = cents(add.amount); options += c; lines.push({ label: add.label, cents: c }); }
  }

  const markup = toNumber(definition.settings?.markup_percent) + percent;
  const subtotal = roundHalfUp((base + options) * (1 + markup / 100));
  let formulaCents = 0;
  if (definition.settings?.formula) {
    try {
      formulaCents = cents(evalFormula(definition.settings.formula, { ...priceCtx.vars, base: base / 100, options: options / 100, subtotal: subtotal / 100, quantity }));
      if (formulaCents) lines.push({ label: definition.settings.formula_label || 'Formula', cents: formulaCents });
    } catch (e) {
      if (!(e instanceof FormulaError)) throw e;
      errors.push({ field: null, code: 'formula_error' });
    }
  }
  const unit = Math.max(0, applyRounding(subtotal + formulaCents, definition.settings?.rounding));
  return { base_cents: base, options_cents: options, markup_percent: markup, formula_cents: formulaCents, unit_cents: unit, total_cents: unit * quantity, lines, components };
}

// ---- layers -------------------------------------------------------------------------------

function layersOf(definition, answers, state) {
  return (definition.layers || [])
    .filter((l) => matches(l.when, answers))
    .map((l) => {
      if (l.type === 'text') return { id: l.id, z: l.z || 0, type: 'text', text: String(answers[l.field] ?? ''), style: l.style || {} };
      const image = own(state.layers, l.id) ? state.layers[l.id] : l.field ? (l.images || {})[String(answers[l.field])] ?? l.image ?? null : l.image ?? null;
      return { id: l.id, z: l.z || 0, type: 'image', image };
    })
    .filter((l) => (l.type === 'text' ? l.text !== '' : !!l.image))
    .sort((a, b) => a.z - b.z);
}

// ---- entry point --------------------------------------------------------------------------

/**
 * @param {object} definition configurator definition
 * @param {object} rawAnswers shopper answers keyed by field key (repeatable steps by step key)
 * @param {object} context { base_price_cents, quantity, variants: [{id, price_cents, available, options}], country }
 */
export function evaluate(definition, rawAnswers = {}, context = {}) {
  const quantity = Math.max(1, Math.floor(toNumber(context.quantity ?? 1)) || 1);
  const ctx = { quantity, country: context.country };
  const raw = { ...(rawAnswers || {}) };
  let answers = { ...raw };
  let state = baseState(definition);
  let availability = {};

  for (let pass = 0; pass < MAX_PASSES; pass++) {
    state = baseState(definition);
    for (const rule of definition.rules || []) {
      if (rule.enabled === false) continue;
      if (matches(rule.conditions, answers, ctx)) for (const a of rule.actions || []) applyAction(state, a);
    }
    availability = variantAvailability(definition, answers, context.variants);
    const next = clean(definition, raw, state, availability);
    if (same(next, answers)) { answers = next; break; }
    answers = next;
  }

  const errors = [];
  for (const step of definition.steps || []) {
    if (!state.steps[step.key]?.visible) continue;
    if (step.repeatable) {
      const items = answers[step.key] || [];
      if (step.required && !items.length) errors.push({ field: step.key, code: 'required' });
      items.forEach((item, i) => {
        for (const field of step.fields || []) validateField(field, { required: !!field.required }, item?.[field.key], step.key + '.' + i + '.', errors);
      });
      continue;
    }
    for (const field of step.fields || []) {
      if (state.fields[field.key].visible) validateField(field, state.fields[field.key], answers[field.key], '', errors);
    }
  }

  const { needed, variant } = resolveVariant(definition, answers, context.variants);
  if (needed && variant === null && !errors.some((e) => e.code === 'required')) errors.push({ field: null, code: 'variant_unavailable' });
  if (variant && variant.available === false) errors.push({ field: null, code: 'variant_unavailable' });
  const base = variant ? Number(variant.price_cents) : Number(context.base_price_cents || 0);

  const price = priceOf(definition, answers, state, base, quantity, ctx, errors);

  const fields = {};
  for (const [key, fs] of Object.entries(state.fields)) {
    const opts = {};
    for (const [value, o] of Object.entries(fs.options)) {
      const stock = availability[key] ? availability[key][value] !== false : true;
      opts[value] = { visible: o.visible, disabled: o.disabled || !stock };
    }
    fields[key] = { visible: fs.visible, required: fs.required, options: opts };
  }

  return {
    answers,
    visible_steps: (definition.steps || []).filter((s) => state.steps[s.key]?.visible).map((s) => s.key),
    fields,
    errors,
    valid: errors.length === 0,
    variant_id: variant ? variant.id : null,
    price,
    layers: layersOf(definition, answers, state),
  };
}

export { findField };
