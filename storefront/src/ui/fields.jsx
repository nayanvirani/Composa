import { useState } from 'preact/hooks';
import { proxy } from './lib.js';

const CHOICE = ['image_tiles', 'swatch', 'buttons', 'dropdown', 'radio'];

function layoutOf(field) {
  if (field.layout) return field.layout;
  return { image_tiles: 'tiles', swatch: 'swatches', buttons: 'buttons', dropdown: 'dropdown', radio: 'radio', checkbox: 'checkbox' }[field.type] || 'buttons';
}

/** One field, rendered for its type and layout. */
export function Field({ field, state, value, onChange, t, money, priceOf, error, settings }) {
  const id = 'cmp-' + field.key;
  const label = t('field.' + field.key + '.label', field.label);
  const help = field.help ? t('field.' + field.key + '.help', field.help) : null;
  const required = state?.required;

  let control = null;
  if (CHOICE.includes(field.type) || field.type === 'checkbox') {
    control = <Choice field={field} state={state} value={value} onChange={onChange} t={t} money={money} priceOf={priceOf} label={label} />;
  } else if (field.type === 'text') {
    const max = field.validation?.max_length;
    control = (
      <div class="cmp-text">
        <input id={id} type="text" value={value || ''} maxLength={max || undefined} placeholder={field.placeholder ? t('field.' + field.key + '.placeholder', field.placeholder) : ''} onInput={(e) => onChange(e.currentTarget.value || undefined)} aria-invalid={!!error} />
        <div class="cmp-meta"><span>{priceOf(field)}</span>{max ? <span>{Array.from(value || '').length} / {max}</span> : null}</div>
      </div>
    );
  } else if (field.type === 'number') {
    control = <input id={id} type="number" inputMode="decimal" value={value ?? ''} min={field.validation?.min} max={field.validation?.max} onInput={(e) => onChange(e.currentTarget.value === '' ? undefined : Number(e.currentTarget.value))} aria-invalid={!!error} />;
  } else if (field.type === 'dimensions') {
    const v = value || {};
    control = (
      <div class="cmp-dims">
        <label>W<input type="number" inputMode="decimal" value={v.w ?? ''} onInput={(e) => onChange({ ...v, w: e.currentTarget.value === '' ? undefined : Number(e.currentTarget.value) })} aria-label={label + ' width'} /></label>
        <span>×</span>
        <label>H<input type="number" inputMode="decimal" value={v.h ?? ''} onInput={(e) => onChange({ ...v, h: e.currentTarget.value === '' ? undefined : Number(e.currentTarget.value) })} aria-label={label + ' height'} /></label>
        {field.unit ? <span class="cmp-unit">{field.unit}</span> : null}
      </div>
    );
  } else if (field.type === 'file') {
    control = <FileField field={field} value={value} onChange={onChange} t={t} settings={settings} label={label} />;
  } else if (field.type === 'product_picker') {
    control = <Choice field={{ ...field, type: 'radio' }} state={state} value={value} onChange={onChange} t={t} money={money} priceOf={priceOf} label={label} />;
  }

  return (
    <div class={'cmp-field' + (error ? ' has-error' : '')}>
      {CHOICE.includes(field.type) || field.type === 'checkbox' ? (
        <div class="cmp-label" id={id + '-label'}>{label}{required ? <span aria-hidden="true"> *</span> : null}</div>
      ) : (
        <label class="cmp-label" for={id}>{label}{required ? <span aria-hidden="true"> *</span> : null}</label>
      )}
      {help ? <p class="cmp-help">{help}</p> : null}
      {control}
      {error ? <p class="cmp-error" role="alert">{error}</p> : null}
    </div>
  );
}

function Choice({ field, state, value, onChange, t, money, priceOf, label }) {
  const multi = field.type === 'checkbox';
  const layout = layoutOf(field);
  const opts = (field.options || []).filter((o) => state?.options?.[String(o.value)]?.visible !== false);
  const selected = (o) => (multi ? Array.isArray(value) && value.map(String).includes(String(o.value)) : String(value) === String(o.value));
  const pick = (o) => {
    if (multi) {
      const cur = Array.isArray(value) ? value : [];
      onChange(selected(o) ? cur.filter((v) => String(v) !== String(o.value)) : [...cur, o.value]);
    } else onChange(o.value);
  };

  if (layout === 'dropdown' && !multi) {
    return (
      <select aria-labelledby={'cmp-' + field.key + '-label'} value={value ?? ''} onChange={(e) => onChange(e.currentTarget.value || undefined)}>
        <option value="">—</option>
        {opts.map((o) => {
          const dis = state?.options?.[String(o.value)]?.disabled;
          return <option key={o.value} value={o.value} disabled={dis}>{t('option.' + field.key + '.' + o.value + '.label', o.label)}{dis ? ' — ' + t('ui.sold_out') : ''}{priceOf(field, o) ? ' (' + priceOf(field, o) + ')' : ''}</option>;
        })}
      </select>
    );
  }

  return (
    <div class={'cmp-choices cmp-' + layout} role={multi ? 'group' : 'radiogroup'} aria-labelledby={'cmp-' + field.key + '-label'}>
      {opts.map((o) => {
        const dis = !!state?.options?.[String(o.value)]?.disabled;
        const on = selected(o);
        const name = t('option.' + field.key + '.' + o.value + '.label', o.label ?? String(o.value));
        const desc = o.description ? t('option.' + field.key + '.' + o.value + '.description', o.description) : null;
        const price = priceOf(field, o);
        return (
          <button type="button" key={o.value} class={'cmp-choice' + (on ? ' is-on' : '') + (dis ? ' is-off' : '')} role={multi ? 'checkbox' : 'radio'} aria-checked={on} aria-disabled={dis} disabled={dis} onClick={() => pick(o)} title={dis ? t('ui.sold_out') : name}>
            {layout === 'swatches' ? <span class="cmp-swatch" style={{ background: o.image ? 'center/cover url(' + o.image + ')' : o.swatch || '#ddd' }} /> : null}
            {layout === 'tiles' ? <span class="cmp-tile-img" style={o.image ? { backgroundImage: 'url(' + o.image + ')' } : null} /> : null}
            {layout === 'radio' || layout === 'checkbox' ? <span class={'cmp-dot' + (multi ? ' is-box' : '')} /> : null}
            <span class="cmp-choice-text">
              <span class="cmp-choice-name">{name}</span>
              {desc ? <span class="cmp-choice-desc">{desc}</span> : null}
              {dis ? <span class="cmp-choice-desc">{t('ui.sold_out')}</span> : null}
            </span>
            {o.badge ? <span class="cmp-badge">{o.badge}</span> : null}
            {price && layout !== 'swatches' && layout !== 'size_grid' ? <span class="cmp-choice-price">{price}</span> : null}
          </button>
        );
      })}
    </div>
  );
}

function FileField({ field, value, onChange, t, settings, label }) {
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState(null);
  const types = field.validation?.file_types || settings?.uploads?.types || [];
  const maxMb = Math.min(field.validation?.max_mb || 20, settings?.uploads?.max_mb || 20);

  const upload = async (file) => {
    if (!file) return;
    setErr(null);
    const ext = file.name.split('.').pop().toLowerCase();
    if (types.length && !types.includes(ext)) return setErr('Allowed: ' + types.join(', '));
    if (file.size > maxMb * 1048576) return setErr('Max ' + maxMb + ' MB');
    setBusy(true);
    try {
      const sign = await proxy(settings, 'uploads', { name: file.name, mime: file.type || 'application/octet-stream', size: file.size, field: field.key });
      const put = await fetch(sign.url, { method: 'PUT', headers: { ...(sign.headers || {}), 'Content-Type': file.type || 'application/octet-stream' }, body: file });
      if (!put.ok) throw new Error('Upload failed');
      const done = await proxy(settings, 'uploads/' + sign.id + '/complete', {});
      onChange({ id: done.id, name: done.name });
    } catch (e) {
      setErr(e.message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <div class="cmp-file">
      {value?.name ? (
        <div class="cmp-file-done"><span>✓ {value.name}</span><button type="button" onClick={() => onChange(undefined)}>{t('ui.remove')}</button></div>
      ) : (
        <label class="cmp-file-drop">
          <input type="file" accept={types.map((x) => '.' + x).join(',')} onChange={(e) => upload(e.currentTarget.files[0])} aria-label={label} />
          <span>{busy ? t('ui.uploading') : t('ui.upload')}</span>
          <small>{types.join(', ').toUpperCase()} · max {maxMb} MB</small>
        </label>
      )}
      {err ? <p class="cmp-error" role="alert">{err}</p> : null}
    </div>
  );
}
