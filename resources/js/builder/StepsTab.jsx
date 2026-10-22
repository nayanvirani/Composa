import { useState } from 'react';
import { Badge, BlockStack, Box, Button, ButtonGroup, Card, Checkbox, Divider, FormLayout, InlineGrid, InlineStack, Modal, Select, Text, TextField } from '@shopify/polaris';
import PriceRuleEditor from './PriceRuleEditor';
import ImageField from './ImageField';
import { CHOICE_TYPES, FIELD_TYPES, LAYOUTS, newField } from './fieldTypes';
import { slug, uniqueKey } from '@/lib/format';

const move = (list, i, d) => {
    const j = i + d;
    if (j < 0 || j >= list.length) return list;
    const next = list.slice();
    [next[i], next[j]] = [next[j], next[i]];
    return next;
};

export default function StepsTab({ definition, update, limits, features, product }) {
    const steps = definition.steps || [];
    const [sel, setSel] = useState(0);
    const [picker, setPicker] = useState(false);
    const [optionEdit, setOptionEdit] = useState(null);
    const step = steps[Math.min(sel, steps.length - 1)];
    const si = steps.indexOf(step);
    const allFieldKeys = steps.flatMap((s) => (s.fields || []).map((f) => f.key));
    const allowed = features?.pricing_types || [];
    const nested = !!features?.nested_steps;

    const setSteps = (next) => update({ ...definition, steps: next });
    const setStep = (patch) => setSteps(steps.map((s, i) => (i === si ? { ...s, ...patch } : s)));
    const setField = (fi, patch) => setStep({ fields: step.fields.map((f, i) => (i === fi ? { ...f, ...patch } : f)) });

    const addStep = () => {
        const key = uniqueKey('step_' + (steps.length + 1), steps.map((s) => s.key));
        setSteps([...steps, { key, title: 'New step', help: '', fields: [] }]);
        setSel(steps.length);
    };
    const addField = (type) => {
        const key = uniqueKey(slug(FIELD_TYPES.find((t) => t.type === type)?.label || 'field'), allFieldKeys);
        setStep({ fields: [...(step.fields || []), newField(type, key)] });
        setPicker(false);
    };

    if (!step) {
        return <Card><BlockStack gap="300"><Text>No steps yet.</Text><InlineStack><Button onClick={addStep}>Add step</Button></InlineStack></BlockStack></Card>;
    }

    return (
        <InlineGrid columns={{ xs: 1, md: '260px 1fr' }} gap="400">
            <Card padding="200">
                <BlockStack gap="100">
                    <Box padding="200">
                        <InlineStack align="space-between">
                            <Text variant="bodySm" tone="subdued" fontWeight="semibold">STEPS · {steps.length}{limits.steps ? ' / ' + limits.steps : ''}</Text>
                            <Button size="slim" onClick={addStep} disabled={limits.steps && steps.length >= limits.steps}>Add</Button>
                        </InlineStack>
                    </Box>
                    {steps.map((s, i) => (
                        <div key={s.key} onClick={() => setSel(i)} role="button" tabIndex={0} onKeyDown={(e) => e.key === 'Enter' && setSel(i)} style={{ cursor: 'pointer', paddingLeft: s.parent ? 16 : 0 }}>
                            <Box padding="200" borderRadius="200" background={i === si ? 'bg-surface-selected' : undefined}>
                                <InlineStack align="space-between" wrap={false} gap="100">
                                    <BlockStack gap="050">
                                        <Text fontWeight={i === si ? 'semibold' : 'regular'}>{s.title || s.key}</Text>
                                        <Text variant="bodyXs" tone="subdued">{(s.fields || []).length} field(s){s.repeatable ? ' · repeat' : ''}{s.hidden_by_default ? ' · hidden' : ''}</Text>
                                    </BlockStack>
                                    {s.source === 'shopify_option' && <Badge size="small" tone="info">Variant</Badge>}
                                </InlineStack>
                            </Box>
                        </div>
                    ))}
                    <Box padding="200"><Text variant="bodyXs" tone="subdued">Steps can nest a sub-flow or repeat (“add another”).</Text></Box>
                </BlockStack>
            </Card>

            <BlockStack gap="400">
                <Card>
                    <BlockStack gap="300">
                        <InlineStack align="space-between">
                            <Text as="h3" variant="headingSm">Step {si + 1}</Text>
                            <ButtonGroup>
                                <Button onClick={() => { setSteps(move(steps, si, -1)); setSel(si - 1); }} disabled={si === 0}>Move up</Button>
                                <Button onClick={() => { setSteps(move(steps, si, 1)); setSel(si + 1); }} disabled={si === steps.length - 1}>Move down</Button>
                                <Button onClick={() => setSteps([...steps.slice(0, si + 1), { ...structuredClone(step), key: uniqueKey(step.key + '_copy', steps.map((s) => s.key)), title: step.title + ' (copy)', fields: (step.fields || []).map((f) => ({ ...structuredClone(f), key: uniqueKey(f.key + '_copy', allFieldKeys) })) }, ...steps.slice(si + 1)])}>Duplicate</Button>
                                <Button tone="critical" onClick={() => { if (confirm('Delete this step and its fields?')) { setSteps(steps.filter((_, i) => i !== si)); setSel(Math.max(0, si - 1)); } }}>Delete</Button>
                            </ButtonGroup>
                        </InlineStack>
                        <FormLayout>
                            <FormLayout.Group>
                                <TextField label="Step title" value={step.title || ''} onChange={(v) => setStep({ title: v })} autoComplete="off" />
                                <TextField label="Step key" value={step.key} onChange={(v) => setStep({ key: slug(v) })} monospaced autoComplete="off" helpText="Used in rules" disabled={step.source === 'shopify_option'} />
                            </FormLayout.Group>
                            <TextField label="Help text" value={step.help || ''} onChange={(v) => setStep({ help: v })} autoComplete="off" />
                            <FormLayout.Group>
                                <Select label="Nested under" value={step.parent || ''} disabled={!nested} onChange={(v) => setStep({ parent: v || null })} options={[{ label: nested ? 'Top level' : 'Top level (nesting: Growth+)', value: '' }, ...steps.filter((s) => s.key !== step.key).map((s) => ({ label: s.title, value: s.key }))]} />
                                <TextField label="Max repeats" type="number" disabled={!step.repeatable} value={String(step.max_repeats ?? 10)} onChange={(v) => setStep({ max_repeats: Number(v) || 1 })} autoComplete="off" />
                            </FormLayout.Group>
                            <InlineStack gap="400">
                                <Checkbox label={nested ? 'Repeatable (“add another item”)' : 'Repeatable (Growth+)'} disabled={!nested || step.source === 'shopify_option'} checked={!!step.repeatable} onChange={(v) => setStep({ repeatable: v })} />
                                <Checkbox label="Required" checked={!!step.required} onChange={(v) => setStep({ required: v })} />
                                <Checkbox label="Hidden until a rule shows it" checked={!!step.hidden_by_default} onChange={(v) => setStep({ hidden_by_default: v })} />
                            </InlineStack>
                        </FormLayout>
                    </BlockStack>
                </Card>

                {(step.fields || []).map((field, fi) => (
                    <FieldCard
                        key={field.key + fi}
                        field={field}
                        index={fi}
                        count={step.fields.length}
                        allKeys={allFieldKeys}
                        limits={limits}
                        allowed={allowed}
                        tables={definition.tables}
                        product={product}
                        onChange={(patch) => setField(fi, patch)}
                        onMove={(d) => setStep({ fields: move(step.fields, fi, d) })}
                        onRemove={() => setStep({ fields: step.fields.filter((_, i) => i !== fi) })}
                        onEditOption={(oi) => setOptionEdit({ fi, oi })}
                    />
                ))}
                <InlineStack><Button variant="primary" onClick={() => setPicker(true)}>Add field</Button></InlineStack>
            </BlockStack>

            <Modal open={picker} onClose={() => setPicker(false)} title={`Add field to “${step.title}”`} size="large">
                <Modal.Section>
                    <InlineGrid columns={{ xs: 1, sm: 2, md: 3 }} gap="300">
                        {FIELD_TYPES.map((t) => {
                            const locked = t.feature && !features?.[t.feature];
                            return (
                                <Button key={t.type} disabled={locked} onClick={() => addField(t.type)} textAlign="start" fullWidth>
                                    <BlockStack gap="050">
                                        <Text fontWeight="semibold">{t.glyph}  {t.label}{locked ? ' · Starter+' : ''}</Text>
                                        <Text variant="bodySm" tone="subdued">{t.help}</Text>
                                    </BlockStack>
                                </Button>
                            );
                        })}
                    </InlineGrid>
                </Modal.Section>
            </Modal>

            {optionEdit && step.fields[optionEdit.fi] && (
                <OptionModal
                    field={step.fields[optionEdit.fi]}
                    index={optionEdit.oi}
                    allowed={allowed}
                    tables={definition.tables}
                    product={product}
                    onClose={() => setOptionEdit(null)}
                    onChange={(opt) => setField(optionEdit.fi, { options: step.fields[optionEdit.fi].options.map((o, i) => (i === optionEdit.oi ? opt : o)) })}
                />
            )}
        </InlineGrid>
    );
}

function FieldCard({ field, index, count, allKeys, limits, allowed, tables, onChange, onMove, onRemove, onEditOption }) {
    const choice = CHOICE_TYPES.includes(field.type);
    const v = field.validation || {};
    const setV = (patch) => onChange({ validation: { ...v, ...patch } });
    const num = (x) => (x === '' ? undefined : Number(x));
    const variant = !!field.variant_option;

    return (
        <Card>
            <BlockStack gap="300">
                <InlineStack align="space-between" blockAlign="center">
                    <InlineStack gap="200" blockAlign="center">
                        <Text as="h4" variant="headingSm">{field.label || field.key}</Text>
                        <Badge>{FIELD_TYPES.find((t) => t.type === field.type)?.label || field.type}</Badge>
                        {variant && <Badge tone="info">Shopify option: {field.variant_option}</Badge>}
                    </InlineStack>
                    <ButtonGroup>
                        <Button size="slim" onClick={() => onMove(-1)} disabled={index === 0}>↑</Button>
                        <Button size="slim" onClick={() => onMove(1)} disabled={index === count - 1}>↓</Button>
                        <Button size="slim" tone="critical" onClick={onRemove} disabled={variant}>Remove</Button>
                    </ButtonGroup>
                </InlineStack>
                <FormLayout>
                    <FormLayout.Group condensed>
                        <TextField label="Label shoppers see" value={field.label || ''} onChange={(x) => onChange({ label: x })} autoComplete="off" />
                        <TextField label="Key (rules & formulas)" monospaced value={field.key} disabled={variant} onChange={(x) => { const k = slug(x); if (!allKeys.filter((y) => y !== field.key).includes(k)) onChange({ key: k }); }} autoComplete="off" />
                        {choice && <Select label="Layout" value={field.layout || ''} onChange={(x) => onChange({ layout: x })} options={[{ label: 'Default for type', value: '' }, ...LAYOUTS]} />}
                    </FormLayout.Group>
                    <FormLayout.Group condensed>
                        <TextField label="Help text" value={field.help || ''} onChange={(x) => onChange({ help: x })} autoComplete="off" />
                        {['text', 'number'].includes(field.type) && <TextField label="Placeholder" value={field.placeholder || ''} onChange={(x) => onChange({ placeholder: x })} autoComplete="off" />}
                        {field.type === 'dimensions' && <TextField label="Unit" value={field.unit || ''} onChange={(x) => onChange({ unit: x })} autoComplete="off" />}
                    </FormLayout.Group>
                    <InlineStack gap="400">
                        <Checkbox label="Required" checked={!!field.required} disabled={variant} onChange={(x) => onChange({ required: x })} />
                        <Checkbox label="Show in cart summary" checked={field.show_in_cart !== false} onChange={(x) => onChange({ show_in_cart: x })} />
                        <Checkbox label="Hidden until a rule shows it" checked={!!field.hidden_by_default} onChange={(x) => onChange({ hidden_by_default: x })} />
                    </InlineStack>

                    {field.type === 'text' && (
                        <FormLayout.Group condensed>
                            <TextField label="Max characters" type="number" value={String(v.max_length ?? '')} onChange={(x) => setV({ max_length: num(x) })} autoComplete="off" />
                            <TextField label="Min characters" type="number" value={String(v.min_length ?? '')} onChange={(x) => setV({ min_length: num(x) })} autoComplete="off" />
                            <TextField label="Allowed characters (regex)" monospaced value={v.pattern || ''} onChange={(x) => setV({ pattern: x })} placeholder="^[A-Za-z ]*$" autoComplete="off" />
                        </FormLayout.Group>
                    )}
                    {field.type === 'number' && (
                        <FormLayout.Group condensed>
                            <TextField label="Min" type="number" value={String(v.min ?? '')} onChange={(x) => setV({ min: num(x) })} autoComplete="off" />
                            <TextField label="Max" type="number" value={String(v.max ?? '')} onChange={(x) => setV({ max: num(x) })} autoComplete="off" />
                            <TextField label="Default" type="number" value={String(field.default ?? '')} onChange={(x) => onChange({ default: num(x) })} autoComplete="off" />
                        </FormLayout.Group>
                    )}
                    {field.type === 'dimensions' && (
                        <FormLayout.Group condensed>
                            {['min_w', 'max_w', 'min_h', 'max_h'].map((k) => <TextField key={k} label={k.replace('_w', ' width').replace('_h', ' height').replace('min', 'Min').replace('max', 'Max')} type="number" value={String(v[k] ?? '')} onChange={(x) => setV({ [k]: num(x) })} autoComplete="off" />)}
                        </FormLayout.Group>
                    )}
                    {field.type === 'file' && (
                        <FormLayout.Group condensed>
                            <TextField label="File types" value={(v.file_types || []).join(', ')} onChange={(x) => setV({ file_types: x.split(',').map((s) => s.trim().toLowerCase()).filter(Boolean) })} autoComplete="off" />
                            <TextField label="Max size (MB)" type="number" value={String(v.max_mb ?? 20)} onChange={(x) => setV({ max_mb: num(x) })} autoComplete="off" />
                        </FormLayout.Group>
                    )}
                    {field.type === 'checkbox' && (
                        <FormLayout.Group condensed>
                            <TextField label="Min choices" type="number" value={String(v.min_choices ?? '')} onChange={(x) => setV({ min_choices: num(x) })} autoComplete="off" />
                            <TextField label="Max choices" type="number" value={String(v.max_choices ?? '')} onChange={(x) => setV({ max_choices: num(x) })} autoComplete="off" />
                        </FormLayout.Group>
                    )}
                    {!variant && !choice && <PriceRuleEditor rule={field.price_rule} onChange={(r) => onChange({ price_rule: r })} allowed={allowed} tables={tables} fieldType={field.type} />}
                    {!variant && choice && field.price_rule && <PriceRuleEditor rule={field.price_rule} onChange={(r) => onChange({ price_rule: r })} allowed={allowed} tables={tables} label="Field price rule (in addition to option prices)" />}
                    {!variant && choice && !field.price_rule && <InlineStack><Button variant="plain" onClick={() => onChange({ price_rule: { type: 'table', table: Object.keys(tables || {})[0] || '' } })}>Price this field from a table or formula</Button></InlineStack>}
                </FormLayout>

                {choice && (
                    <BlockStack gap="200">
                        <Divider />
                        <InlineStack align="space-between">
                            <Text fontWeight="semibold">Options · {(field.options || []).length}{limits.options ? ' / ' + limits.options : ''}</Text>
                            {!variant && (
                                <Button size="slim" disabled={limits.options && field.options.length >= limits.options} onClick={() => onChange({ options: [...field.options, { value: uniqueKey('option_' + (field.options.length + 1), field.options.map((o) => String(o.value))), label: 'New option' }] })}>Add option</Button>
                            )}
                        </InlineStack>
                        {(field.options || []).map((o, oi) => (
                            <InlineGrid key={o.value + oi} columns="28px 1.4fr 1fr 1fr auto" gap="200" alignItems="center">
                                <div style={{ width: 22, height: 22, borderRadius: '50%', background: o.swatch || (o.image ? `center/cover url(${o.image})` : 'var(--p-color-bg-fill-secondary)'), border: '1px solid rgba(0,0,0,.15)' }} />
                                <TextField labelHidden label="Label" value={o.label || ''} onChange={(x) => onChange({ options: field.options.map((y, j) => (j === oi ? { ...y, label: x } : y)) })} autoComplete="off" />
                                <Text variant="bodySm" tone="subdued">{priceLabel(o.price_rule)}</Text>
                                <InlineStack gap="100">
                                    {o.default && <Badge>Default</Badge>}
                                    {o.hidden && <Badge tone="warning">Hidden</Badge>}
                                    {o.linked_variant_id && <Badge tone="info">Linked SKU</Badge>}
                                </InlineStack>
                                <ButtonGroup>
                                    <Button size="slim" onClick={() => onEditOption(oi)}>Edit</Button>
                                    <Button size="slim" onClick={() => onChange({ options: move(field.options, oi, -1) })} disabled={oi === 0}>↑</Button>
                                    {!variant && <Button size="slim" tone="critical" onClick={() => onChange({ options: field.options.filter((_, j) => j !== oi) })}>✕</Button>}
                                </ButtonGroup>
                            </InlineGrid>
                        ))}
                    </BlockStack>
                )}
            </BlockStack>
        </Card>
    );
}

export function priceLabel(rule) {
    if (!rule?.type) return 'Included';
    if (rule.type === 'fixed') return (rule.amount >= 0 ? '+' : '') + Number(rule.amount || 0).toFixed(2);
    if (rule.type === 'percent') return (rule.percent >= 0 ? '+' : '') + rule.percent + '%';
    if (rule.type === 'per_char') return rule.amount + ' / letter';
    if (rule.type === 'per_unit') return rule.amount + ' / unit';
    if (rule.type === 'table') return 'Table: ' + (rule.table || '?');
    if (rule.type === 'tiered') return 'Tiered';
    return 'Formula';
}

function OptionModal({ field, index, allowed, tables, product, onChange, onClose }) {
    const o = field.options[index];
    const set = (patch) => onChange({ ...o, ...patch });
    const varsText = Object.entries(o.vars || {}).map(([k, v]) => `${k}=${v}`).join(', ');
    const variants = product?.variants || [];

    return (
        <Modal open onClose={onClose} title={`Edit option · ${o.label || o.value}`} primaryAction={{ content: 'Done', onAction: onClose }}>
            <Modal.Section>
                <FormLayout>
                    <FormLayout.Group>
                        <TextField label="Label" value={o.label || ''} onChange={(x) => set({ label: x })} autoComplete="off" />
                        <TextField label="Value (rules & cart)" monospaced disabled={!!field.variant_option} value={String(o.value)} onChange={(x) => { const val = slug(x); if (!field.options.some((y, j) => j !== index && String(y.value) === val)) set({ value: val }); }} autoComplete="off" />
                    </FormLayout.Group>
                    <TextField label="Description" value={o.description || ''} onChange={(x) => set({ description: x })} multiline={2} autoComplete="off" />
                    <FormLayout.Group>
                        <TextField label="Swatch colour" value={o.swatch || ''} onChange={(x) => set({ swatch: x })} placeholder="#D9A08B" autoComplete="off" />
                        <TextField label="SKU code (production only)" monospaced value={o.sku || ''} onChange={(x) => set({ sku: x })} autoComplete="off" />
                        <TextField label="Badge" value={o.badge || ''} onChange={(x) => set({ badge: x })} placeholder="e.g. Bestseller" autoComplete="off" />
                    </FormLayout.Group>
                    <ImageField label="Image" value={o.image} onChange={(x) => set({ image: x })} />
                    {!field.variant_option && <PriceRuleEditor rule={o.price_rule} onChange={(r) => set({ price_rule: r })} allowed={allowed} tables={tables} fieldType={field.type} />}
                    <TextField label="Formula variables" monospaced value={varsText} onChange={(x) => set({ vars: Object.fromEntries(x.split(',').map((p) => p.split('=').map((s) => s.trim())).filter(([k, val]) => /^[a-z_][a-z0-9_]*$/.test(k || '') && val !== undefined && !Number.isNaN(Number(val))).map(([k, val]) => [k, Number(val)])) })} placeholder="metal_rate=62.5" helpText="Numbers this option sets for price formulas." autoComplete="off" />
                    {!field.variant_option && (
                        <Select label="Link to a stocked Shopify variant (tracks inventory)" value={o.linked_variant_id || ''} onChange={(x) => set({ linked_variant_id: x || null })} options={[{ label: 'Not linked', value: '' }, ...variants.map((v) => ({ label: v.title + (v.sku ? ' · ' + v.sku : ''), value: v.id }))]} helpText={variants.length ? 'From the base product. Linked options are added as cart components in Expand mode.' : 'Choose a base product to link its variants.'} />
                    )}
                    <InlineStack gap="400">
                        <Checkbox label="Default selection" checked={!!o.default} onChange={(x) => onChange({ ...o, default: x })} />
                        <Checkbox label="Hidden (until a rule shows it)" checked={!!o.hidden} onChange={(x) => set({ hidden: x })} />
                        <Checkbox label="Disabled" checked={!!o.disabled} onChange={(x) => set({ disabled: x })} />
                    </InlineStack>
                </FormLayout>
            </Modal.Section>
        </Modal>
    );
}
