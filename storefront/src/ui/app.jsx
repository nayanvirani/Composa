import { Fragment, render } from 'preact';
import { useEffect, useMemo, useRef, useState } from 'preact/hooks';
import { evaluate } from '@engine';
import { Field } from './fields.jsx';
import { ERRORS, currencyInfo, engineVariants, formatMoney, numericId, proxy, tracker, translator } from './lib.js';

/** Layered preview: stacked images and text in z order. */
function Preview({ layers, fallback }) {
  if (!layers.length) return fallback ? <div class="cmp-preview"><img src={fallback} alt="" /></div> : null;
  return (
    <div class="cmp-preview" aria-hidden="true">
      {layers.map((l) =>
        l.type === 'image' ? (
          <img key={l.id} src={l.image} alt="" style={{ zIndex: l.z }} />
        ) : (
          <span key={l.id} class="cmp-layer-text" style={{ zIndex: l.z, left: (l.style.x ?? 50) + '%', top: (l.style.y ?? 50) + '%', color: l.style.color, fontFamily: l.style.font, fontSize: (l.style.size || 6) + 'cqw' }}>{l.text}</span>
        ),
      )}
    </div>
  );
}

function displayValue(field, value, t) {
  if (value == null || value === '') return null;
  if (Array.isArray(value)) return value.map((v) => displayValue(field, v, t)).join(', ');
  if (field.type === 'dimensions') return value.w + ' × ' + value.h + (field.unit ? ' ' + field.unit : '');
  if (field.type === 'file') return value.name;
  const o = (field.options || []).find((x) => String(x.value) === String(value));
  return o ? t('option.' + field.key + '.' + o.value + '.label', o.label ?? String(o.value)) : String(value);
}

function Configurator({ opts, onClose }) {
  const { config, product, settings, locale } = opts;
  const definition = config.definition || {};
  const display = config.display || {};
  const stepsCfg = display.steps || {};
  const t = useMemo(() => translator(definition, locale), [definition, locale]);
  const info = useMemo(() => currencyInfo(opts.currency), [opts.currency]);
  const money = (c) => formatMoney(c, info, locale);
  const variants = useMemo(() => (config.variant_mode === 'base' ? null : engineVariants(product, info.rate)), [product, info.rate]);
  const startVariant = useMemo(() => {
    const id = opts.variantId || numericId(config.base_variant_id);
    const v = product?.variants?.find((x) => String(x.id) === String(id)) || product?.variants?.[0];
    return v || null;
  }, []);

  const [answers, setAnswers] = useState({});
  const [quantity, setQuantity] = useState(1);
  const [index, setIndex] = useState(0);
  const [showErrors, setShowErrors] = useState(false);
  const [busy, setBusy] = useState(false);
  const [failure, setFailure] = useState(null);
  const [added, setAdded] = useState(null);
  const track = useRef(null);
  const reached = useRef(new Set());

  const result = useMemo(
    () => evaluate(definition, answers, {
      base_price_cents: startVariant ? Math.round(Number(startVariant.price) / info.rate) : 0,
      quantity,
      variants,
      country: window.Shopify?.country,
    }),
    [answers, quantity, variants],
  );

  const steps = (definition.steps || []).filter((s) => result.visible_steps.includes(s.key));
  const withReview = stepsCfg.review !== false;
  const total = steps.length + (withReview ? 1 : 0);
  const current = index < steps.length ? steps[index] : null;
  const errorsFor = (prefix) => result.errors.filter((e) => e.field && (e.field === prefix || e.field.startsWith(prefix + '.')));
  const errorText = (key) => {
    const e = result.errors.find((x) => x.field === key);
    return e && showErrors ? t('error.' + e.code, ERRORS[e.code] || ERRORS.required) : null;
  };

  useEffect(() => {
    track.current = tracker(settings, config.id);
    track.current.track('open', null, null, { mode: opts.mount ? 'page' : 'popup' });
    if (opts.loadStartedAt) track.current.track('load_ms', null, Math.round(performance.now() - opts.loadStartedAt));
    return () => track.current.flush();
  }, []);

  useEffect(() => {
    const key = current ? current.key : 'review';
    if (!reached.current.has(key)) {
      reached.current.add(key);
      track.current?.track('step', key, index);
    }
  }, [index, current]);

  useEffect(() => {
    if (opts.mount) return;
    const onKey = (e) => e.key === 'Escape' && onClose();
    addEventListener('keydown', onKey);
    return () => removeEventListener('keydown', onKey);
  }, []);

  const set = (key, value) => {
    setAnswers((a) => ({ ...a, [key]: value }));
    track.current?.track('choice', key, null, { value: typeof value === 'object' ? null : String(value).slice(0, 60) });
  };
  const setItem = (step, i, key, value) => {
    setAnswers((a) => {
      const items = [...(a[step.key] || [])];
      items[i] = { ...(items[i] || {}), [key]: value };
      return { ...a, [step.key]: items };
    });
  };

  const next = () => {
    if (current && errorsFor(current.key).concat(...(current.fields || []).map((f) => errorsFor(f.key))).length) {
      setShowErrors(true);
      return;
    }
    setShowErrors(false);
    setIndex((i) => Math.min(i + 1, total - 1));
  };

  const priceOf = (field, option) => {
    const rule = option ? option.price : field.price;
    if (!rule || !rule.type) return '';
    if (rule.type === 'fixed') return Number(rule.amount) ? (Number(rule.amount) > 0 ? '+' : '') + money(Math.round(Number(rule.amount) * 100)) : '';
    if (rule.type === 'percent') return Number(rule.percent) ? '+' + rule.percent + '%' : '';
    if (rule.type === 'per_char') return '+' + money(Math.round(Number(rule.amount) * 100)) + ' / char';
    return '';
  };

  const addToCart = async () => {
    if (!result.valid) {
      setShowErrors(true);
      const first = steps.findIndex((s) => errorsFor(s.key).length || (s.fields || []).some((f) => errorsFor(f.key).length));
      if (first >= 0) setIndex(first);
      return;
    }
    setBusy(true);
    setFailure(null);
    try {
      const cfg = await proxy(settings, 'configurations', {
        configurator_id: config.id,
        answers: result.answers,
        quantity,
        product_id: product ? String(product.id) : null,
        variant_id: result.variant_id ? numericId(result.variant_id) : startVariant ? String(startVariant.id) : null,
        session: track.current?.session,
        country: window.Shopify?.country,
      });
      const res = await fetch('/cart/add.js', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ items: [{ id: Number(cfg.variant_id), quantity: cfg.quantity, properties: cfg.properties }] }),
      });
      if (!res.ok) throw new Error((await res.json().catch(() => ({}))).description || 'Could not add to cart.');
      track.current?.flush();
      afterAdd(cfg);
    } catch (e) {
      setFailure(e.message);
    } finally {
      setBusy(false);
    }
  };

  const afterAdd = (cfg) => {
    const mode = display.after_add || 'drawer';
    document.dispatchEvent(new CustomEvent('cart:updated', { bubbles: true }));
    document.documentElement.dispatchEvent(new CustomEvent('cart:refresh', { bubbles: true }));
    window.Composa?.refreshCart?.();
    if (mode === 'cart') return (location.href = (window.Shopify?.routes?.root || '/') + 'cart');
    if (mode === 'checkout') return (location.href = (window.Shopify?.routes?.root || '/') + 'checkout');
    const drawer = document.querySelector('cart-drawer');
    if (mode === 'drawer' && drawer && typeof drawer.open === 'function') {
      fetch((window.Shopify?.routes?.root || '/') + '?sections=cart-drawer,cart-icon-bubble')
        .then((r) => r.json())
        .then((sections) => (drawer.renderContents ? drawer.renderContents({ id: cfg.variant_id, sections }) : drawer.open()))
        .catch(() => drawer.open());
      onClose();
      return;
    }
    setAdded(cfg);
  };

  const reset = () => {
    setAnswers({});
    setQuantity(1);
    setIndex(0);
    setAdded(null);
  };

  const fallbackImage = product?.featured_image ? (String(product.featured_image).startsWith('//') ? 'https:' + product.featured_image : product.featured_image) : null;
  const price = result.price;
  const label = (key, fb) => t(key, fb);

  const body = added ? (
    <div class="cmp-done" role="status">
      <p class="cmp-done-title">{label('ui.added')}</p>
      {added.image_url ? <img src={added.image_url} alt="" /> : null}
      <div class="cmp-actions">
        <a class="cmp-btn" href={(window.Shopify?.routes?.root || '/') + 'cart'}>{label('ui.view_cart')}</a>
        <button type="button" class="cmp-btn is-ghost" onClick={reset}>{label('ui.design_another')}</button>
      </div>
    </div>
  ) : (
    <div class="cmp-body">
      <div class="cmp-visual">
        <Preview layers={result.layers} fallback={fallbackImage} />
      </div>
      <div class="cmp-form">
        {stepsCfg.progress !== false ? (
          <div class="cmp-progress">
            <span>{t('ui.step_of', null, { n: Math.min(index + 1, total), total })}</span>
            <div class="cmp-bar"><i style={{ width: ((index + 1) / total) * 100 + '%' }} /></div>
          </div>
        ) : null}
        {stepsCfg.chips !== false ? (
          <ol class="cmp-chips">
            {steps.map((s, i) => (
              <li key={s.key}><button type="button" class={i === index ? 'is-on' : i < index ? 'is-done' : ''} onClick={() => i <= index && setIndex(i)} aria-current={i === index ? 'step' : undefined}>{t('step.' + s.key + '.title', s.title)}</button></li>
            ))}
            {withReview ? <li><button type="button" class={index === steps.length ? 'is-on' : ''} onClick={() => index >= steps.length && setIndex(steps.length)}>{label('ui.review')}</button></li> : null}
          </ol>
        ) : null}

        {current ? (
          <section class="cmp-step" aria-labelledby={'cmp-step-' + current.key}>
            <h3 id={'cmp-step-' + current.key}>{t('step.' + current.key + '.title', current.title)}</h3>
            {current.description ? <p class="cmp-help">{t('step.' + current.key + '.description', current.description)}</p> : null}
            {current.repeatable ? (
              <Repeatable step={current} items={answers[current.key] || []} setItem={setItem} setAnswers={setAnswers} t={t} money={money} priceOf={priceOf} settings={settings} result={result} showErrors={showErrors} />
            ) : (
              (current.fields || []).filter((f) => result.fields[f.key]?.visible !== false).map((f) => (
                <Field key={f.key} field={f} state={result.fields[f.key]} value={result.answers[f.key]} onChange={(v) => set(f.key, v)} t={t} money={money} priceOf={priceOf} error={errorText(f.key)} settings={settings} />
              ))
            )}
          </section>
        ) : (
          <Review steps={steps} result={result} t={t} goTo={setIndex} quantity={quantity} setQuantity={setQuantity} />
        )}

        {stepsCfg.breakdown !== false && !current ? (
          <details class="cmp-breakdown" open>
            <summary>{label('ui.price_breakdown')}</summary>
            <dl>
              <dt>{config.name}</dt><dd>{money(price.base_cents)}</dd>
              {price.lines.map((l, i) => (
                <Fragment key={'l' + i}><dt>{l.label}</dt><dd>{l.percent != null ? '+' + l.percent + '%' : money(l.cents)}</dd></Fragment>
              ))}
              {quantity > 1 ? <><dt>{label('ui.quantity')}</dt><dd>× {quantity}</dd></> : null}
            </dl>
          </details>
        ) : null}

        {result.errors.some((e) => !e.field) && showErrors ? <p class="cmp-error" role="alert">{t('error.' + result.errors.find((e) => !e.field).code, ERRORS[result.errors.find((e) => !e.field).code])}</p> : null}
        {failure ? <p class="cmp-error" role="alert">{failure}</p> : null}
      </div>
    </div>
  );

  return (
    <div class={'cmp-root' + (opts.mount ? ' is-page' : ' is-modal cmp-' + (display.popup_style || 'modal'))} role={opts.mount ? undefined : 'dialog'} aria-modal={opts.mount ? undefined : 'true'} aria-label={config.name}>
      {!opts.mount ? <div class="cmp-scrim" onClick={onClose} /> : null}
      <div class="cmp-panel">
        <header class="cmp-head">
          <strong>{t('configurator.name', config.name)}</strong>
          {!opts.mount ? <button type="button" class="cmp-close" onClick={onClose} aria-label={label('ui.close')}>×</button> : null}
        </header>
        {body}
        {!added ? (
          <footer class={'cmp-foot' + (stepsCfg.sticky_price !== false ? ' is-sticky' : '')}>
            <div class="cmp-total">
              <span>{label('ui.total')}</span>
              <strong aria-live="polite">{money(price.total_cents)}</strong>
            </div>
            <div class="cmp-actions">
              {index > 0 ? <button type="button" class="cmp-btn is-ghost" onClick={() => setIndex(index - 1)}>{label('ui.back')}</button> : null}
              {index < total - 1 ? (
                <button type="button" class="cmp-btn" onClick={next}>{label('ui.next')}</button>
              ) : (
                <button type="button" class="cmp-btn" onClick={addToCart} disabled={busy} aria-busy={busy}>{label('ui.add_to_cart')}</button>
              )}
            </div>
          </footer>
        ) : null}
      </div>
    </div>
  );
}

function Repeatable({ step, items, setItem, setAnswers, t, money, priceOf, settings, result, showErrors }) {
  const max = step.max_repeats || 10;
  const list = items.length ? items : [{}];
  const err = (i, key) => {
    const e = result.errors.find((x) => x.field === step.key + '.' + i + '.' + key);
    return e && showErrors ? t('error.' + e.code, ERRORS[e.code]) : null;
  };
  return (
    <div class="cmp-repeat">
      {list.map((item, i) => (
        <fieldset key={i} class="cmp-repeat-item">
          <legend>{t('step.' + step.key + '.item', step.item_label || step.title)} {i + 1}</legend>
          {(step.fields || []).map((f) => (
            <Field key={f.key} field={f} state={{ required: !!f.required }} value={item?.[f.key]} onChange={(v) => setItem(step, i, f.key, v)} t={t} money={money} priceOf={priceOf} error={err(i, f.key)} settings={settings} />
          ))}
          {list.length > 1 ? <button type="button" class="cmp-link" onClick={() => setAnswers((a) => ({ ...a, [step.key]: list.filter((_, j) => j !== i) }))}>{t('ui.remove')}</button> : null}
        </fieldset>
      ))}
      {list.length < max ? <button type="button" class="cmp-btn is-ghost" onClick={() => setAnswers((a) => ({ ...a, [step.key]: [...list, {}] }))}>{t('ui.add_another')}</button> : null}
    </div>
  );
}

function Review({ steps, result, t, goTo, quantity, setQuantity }) {
  return (
    <section class="cmp-step cmp-review">
      <h3>{t('ui.review')}</h3>
      {steps.map((s, i) => {
        const rows = s.repeatable
          ? (result.answers[s.key] || []).flatMap((item, n) => (s.fields || []).map((f) => [f.label + ' ' + (n + 1), displayValue(f, item?.[f.key], t)]))
          : (s.fields || []).filter((f) => result.fields[f.key]?.visible !== false).map((f) => [t('field.' + f.key + '.label', f.label), displayValue(f, result.answers[f.key], t)]);
        const shown = rows.filter((r) => r[1]);
        if (!shown.length) return null;
        return (
          <div class="cmp-review-step" key={s.key}>
            <div class="cmp-review-head"><strong>{t('step.' + s.key + '.title', s.title)}</strong><button type="button" class="cmp-link" onClick={() => goTo(i)}>{t('ui.edit')}</button></div>
            <dl>{shown.map(([k, v]) => <Fragment key={k}><dt>{k}</dt><dd>{v}</dd></Fragment>)}</dl>
          </div>
        );
      })}
      <label class="cmp-qty">
        {t('ui.quantity')}
        <span>
          <button type="button" onClick={() => setQuantity(Math.max(1, quantity - 1))} aria-label="−">−</button>
          <input type="number" min="1" max="999" value={quantity} onInput={(e) => setQuantity(Math.max(1, Math.min(999, Number(e.currentTarget.value) || 1)))} />
          <button type="button" onClick={() => setQuantity(Math.min(999, quantity + 1))} aria-label="+">+</button>
        </span>
      </label>
    </section>
  );
}

let host = null;
let lastFocus = null;

function open(opts) {
  if (opts.mount) {
    render(<Configurator opts={opts} onClose={() => {}} />, opts.mount);
    return;
  }
  lastFocus = document.activeElement;
  if (!host) {
    host = document.createElement('div');
    host.className = 'composa';
    document.body.appendChild(host);
  }
  const close = () => {
    render(null, host);
    document.documentElement.classList.remove('cmp-lock');
    lastFocus && lastFocus.focus && lastFocus.focus();
  };
  document.documentElement.classList.add('cmp-lock');
  render(<Configurator key={Date.now()} opts={opts} onClose={close} />, host);
  requestAnimationFrame(() => host.querySelector('.cmp-panel button, .cmp-panel input')?.focus());
}

window.ComposaUI = { open };
