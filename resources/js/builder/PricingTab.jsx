import { useMemo, useState } from 'react';
import { Badge, Banner, BlockStack, Button, Card, DataTable, InlineGrid, InlineStack, Select, Text, TextField } from '@shopify/polaris';
import { evaluate, parseFormula, variablesOf } from '@engine';
import PriceRuleEditor from './PriceRuleEditor';
import ConditionEditor from './ConditionEditor';
import { api } from '@/lib/api';
import { money, slug } from '@/lib/format';

export default function PricingTab({ configurator, definition, update, features, basePriceCents, variants, currency }) {
    const settings = definition.settings || {};
    const setSettings = (patch) => update({ ...definition, settings: { ...settings, ...patch } });
    const allowed = features?.pricing_types || [];
    const fields = (definition.steps || []).flatMap((s) => s.fields || []);
    const variables = settings.variables && !Array.isArray(settings.variables) ? settings.variables : {};
    const tables = definition.tables && !Array.isArray(definition.tables) ? definition.tables : {};

    const knownVars = useMemo(() => {
        const out = new Set(['base', 'options', 'subtotal', 'quantity', ...Object.keys(variables)]);
        fields.forEach((f) => {
            out.add(f.key);
            if (f.type === 'dimensions') { out.add(f.key + '_w'); out.add(f.key + '_h'); }
            (f.options || []).forEach((o) => Object.keys(o.vars || {}).forEach((k) => out.add(k)));
        });
        return [...out];
    }, [definition]);

    const formulaCheck = useMemo(() => {
        if (!settings.formula) return null;
        try {
            const unknown = [...variablesOf(parseFormula(settings.formula))].filter((v) => !knownVars.includes(v));
            return unknown.length ? { tone: 'warning', text: 'Unknown: ' + unknown.join(', ') } : { tone: 'success', text: 'Formula is valid' };
        } catch (e) {
            return { tone: 'critical', text: e.message };
        }
    }, [settings.formula, knownVars]);

    return (
        <BlockStack gap="400">
            <Card>
                <BlockStack gap="300">
                    <Text as="h3" variant="headingSm">Price = (base + options) × (1 + markup) + formula, then rounding</Text>
                    <InlineGrid columns={{ xs: 1, md: 3 }} gap="300">
                        <TextField label="Markup" type="number" suffix="%" value={String(settings.markup_percent ?? 0)} onChange={(v) => setSettings({ markup_percent: Number(v) })} disabled={!allowed.includes('percent')} autoComplete="off" />
                        <Select label="Rounding" value={settings.rounding?.mode || 'none'} onChange={(m) => setSettings({ rounding: { ...(settings.rounding || {}), mode: m } })} options={[{ label: 'None', value: 'none' }, { label: 'Nearest', value: 'nearest' }, { label: 'Up', value: 'up' }, { label: 'Down', value: 'down' }, { label: 'End in…', value: 'ending' }]} />
                        {settings.rounding?.mode === 'ending' ? (
                            <TextField label="Ending" type="number" step={0.01} value={String(settings.rounding?.ending ?? 0.99)} onChange={(v) => setSettings({ rounding: { ...settings.rounding, ending: Number(v) } })} autoComplete="off" />
                        ) : settings.rounding?.mode && settings.rounding.mode !== 'none' ? (
                            <TextField label="To the nearest" type="number" step={0.01} value={String(settings.rounding?.step ?? 1)} onChange={(v) => setSettings({ rounding: { ...settings.rounding, step: Number(v) } })} autoComplete="off" />
                        ) : <span />}
                    </InlineGrid>
                </BlockStack>
            </Card>

            <Card>
                <BlockStack gap="300">
                    <InlineStack align="space-between">
                        <Text as="h3" variant="headingSm">Formula</Text>
                        {!allowed.includes('formula') && <Badge tone="attention">Pro</Badge>}
                    </InlineStack>
                    <TextField label="Added to the price" monospaced multiline={3} disabled={!allowed.includes('formula')} value={settings.formula || ''} onChange={(v) => setSettings({ formula: v })} placeholder="weight_g * metal_rate + making" autoComplete="off" helpText="Sandboxed — no code runs. Functions: min, max, round, ceil, floor, abs, if." />
                    {formulaCheck && <Text tone={formulaCheck.tone}>{formulaCheck.text}</Text>}
                    <InlineStack gap="100">{knownVars.map((v) => <Button key={v} size="micro" onClick={() => setSettings({ formula: (settings.formula || '') + (settings.formula ? ' ' : '') + v })}>{v}</Button>)}</InlineStack>
                    <TextField label="Line label" value={settings.formula_label || ''} onChange={(v) => setSettings({ formula_label: v })} placeholder="Metal and making" autoComplete="off" />
                    <Text fontWeight="semibold">Constants</Text>
                    {Object.entries(variables).map(([k, v]) => (
                        <InlineGrid key={k} columns="1fr 1fr auto" gap="200">
                            <TextField labelHidden label="Name" monospaced value={k} onChange={(n) => { const next = {}; Object.entries(variables).forEach(([a, b]) => (next[a === k ? slug(n) : a] = b)); setSettings({ variables: next }); }} autoComplete="off" />
                            <TextField labelHidden label="Value" type="number" value={String(v)} onChange={(n) => setSettings({ variables: { ...variables, [k]: Number(n) } })} autoComplete="off" />
                            <Button onClick={() => { const next = { ...variables }; delete next[k]; setSettings({ variables: next }); }}>✕</Button>
                        </InlineGrid>
                    ))}
                    <InlineStack><Button onClick={() => setSettings({ variables: { ...variables, ['const_' + (Object.keys(variables).length + 1)]: 0 } })}>Add constant</Button></InlineStack>
                </BlockStack>
            </Card>

            <Card>
                <BlockStack gap="300">
                    <InlineStack align="space-between">
                        <Text as="h3" variant="headingSm">Price rules for the whole configurator</Text>
                        <Button onClick={() => update({ ...definition, price_rules: [...(definition.price_rules || []), { id: 'p' + Date.now().toString(36), label: 'Price rule', type: 'fixed', amount: 0 }] })}>Add price rule</Button>
                    </InlineStack>
                    {(definition.price_rules || []).map((rule, i) => (
                        <Card key={rule.id || i} background="bg-surface-secondary">
                            <BlockStack gap="200">
                                <InlineStack align="space-between">
                                    <TextField labelHidden label="Label" value={rule.label || ''} onChange={(v) => update({ ...definition, price_rules: definition.price_rules.map((r, j) => (j === i ? { ...r, label: v } : r)) })} autoComplete="off" />
                                    <Button tone="critical" onClick={() => update({ ...definition, price_rules: definition.price_rules.filter((_, j) => j !== i) })}>Remove</Button>
                                </InlineStack>
                                <PriceRuleEditor rule={rule} allowed={allowed} tables={tables} onChange={(r) => update({ ...definition, price_rules: definition.price_rules.map((x, j) => (j === i ? { ...(r || { type: 'fixed', amount: 0 }), id: rule.id, label: rule.label, when: rule.when } : x)) })} />
                                <Text variant="bodySm" fontWeight="semibold">Only when (optional)</Text>
                                <ConditionEditor node={rule.when} fields={fields} advanced={features?.rules_level === 'advanced'} onChange={(n) => update({ ...definition, price_rules: definition.price_rules.map((x, j) => (j === i ? { ...x, when: (n.all || n.any || []).length ? n : null } : x)) })} />
                            </BlockStack>
                        </Card>
                    ))}
                </BlockStack>
            </Card>

            <TablesEditor tables={tables} fields={fields} allowed={allowed.includes('table')} onChange={(t) => update({ ...definition, tables: t })} />
            <TestPanel configurator={configurator} definition={definition} fields={fields} basePriceCents={basePriceCents} variants={variants} currency={currency} />
        </BlockStack>
    );
}

function TablesEditor({ tables, fields, allowed, onChange }) {
    const entries = Object.entries(tables);
    const choiceFields = fields.filter((f) => f.options?.length);
    const add = () => {
        const row = choiceFields[0];
        const col = choiceFields[1];
        const values = {};
        (row?.options || []).forEach((r) => {
            values[r.value] = {};
            (col?.options || []).forEach((c) => (values[r.value][c.value] = 0));
        });
        onChange({ ...tables, ['table_' + (entries.length + 1)]: { label: 'Price table', row_field: row?.key, col_field: col?.key, values } });
    };
    return (
        <Card>
            <BlockStack gap="300">
                <InlineStack align="space-between">
                    <Text as="h3" variant="headingSm">Lookup tables</Text>
                    <Button onClick={add} disabled={!allowed || choiceFields.length < 2}>{allowed ? 'Add table' : 'Add table (Growth+)'}</Button>
                </InlineStack>
                {entries.map(([key, t]) => {
                    const rowField = fields.find((f) => f.key === t.row_field);
                    const colField = fields.find((f) => f.key === t.col_field);
                    const set = (patch) => onChange({ ...tables, [key]: { ...t, ...patch } });
                    return (
                        <BlockStack key={key} gap="200">
                            <InlineGrid columns="1fr 1fr 1fr auto" gap="200">
                                <TextField label="Name" value={t.label || ''} onChange={(v) => set({ label: v })} autoComplete="off" />
                                <Select label="Rows" value={t.row_field || ''} onChange={(v) => set({ row_field: v })} options={choiceFields.map((f) => ({ label: f.label || f.key, value: f.key }))} />
                                <Select label="Columns" value={t.col_field || ''} onChange={(v) => set({ col_field: v })} options={choiceFields.map((f) => ({ label: f.label || f.key, value: f.key }))} />
                                <div style={{ alignSelf: 'end' }}><Button tone="critical" onClick={() => { const next = { ...tables }; delete next[key]; onChange(next); }}>Remove</Button></div>
                            </InlineGrid>
                            {rowField && colField && (
                                <div style={{ overflowX: 'auto' }}>
                                    <table style={{ borderCollapse: 'collapse' }}>
                                        <thead><tr><th style={{ padding: 6, textAlign: 'left' }}>{rowField.label} \ {colField.label}</th>{colField.options.map((c) => <th key={c.value} style={{ padding: 6 }}>{c.label}</th>)}</tr></thead>
                                        <tbody>
                                            {rowField.options.map((r) => (
                                                <tr key={r.value}>
                                                    <th style={{ padding: 6, textAlign: 'left' }}>{r.label}</th>
                                                    {colField.options.map((c) => (
                                                        <td key={c.value} style={{ padding: 4, minWidth: 100 }}>
                                                            <TextField labelHidden label={`${r.label} ${c.label}`} type="number" value={String(t.values?.[r.value]?.[c.value] ?? '')} onChange={(v) => set({ values: { ...t.values, [r.value]: { ...(t.values?.[r.value] || {}), [c.value]: v === '' ? null : Number(v) } } })} autoComplete="off" />
                                                        </td>
                                                    ))}
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                            <Text variant="bodySm" tone="subdued">Use it from a field’s price rule: “Lookup table” → {t.label || key}.</Text>
                        </BlockStack>
                    );
                })}
            </BlockStack>
        </Card>
    );
}

/** Pick test answers, see the browser price, and confirm the server signs the same price. */
function TestPanel({ configurator, definition, fields, basePriceCents, variants, currency }) {
    const [answers, setAnswers] = useState({});
    const [quantity, setQuantity] = useState('1');
    const [server, setServer] = useState(null);
    const browser = useMemo(() => evaluate(definition, answers, { base_price_cents: basePriceCents, quantity: Number(quantity) || 1, variants }), [definition, answers, quantity, basePriceCents, variants]);

    const runServer = async () => setServer(await api(`configurators/${configurator.id}/evaluate`, { method: 'POST', body: { definition, answers, quantity: Number(quantity) || 1 } }));
    const match = server && server.price.unit_cents === browser.price.unit_cents;

    return (
        <Card>
            <BlockStack gap="300">
                <Text as="h3" variant="headingSm">Test with answers</Text>
                <InlineGrid columns={{ xs: 1, md: 3 }} gap="200">
                    {fields.filter((f) => f.type !== 'file').map((f) =>
                        f.options?.length && f.type !== 'checkbox' ? (
                            <Select key={f.key} label={f.label || f.key} value={String(answers[f.key] ?? '')} onChange={(v) => setAnswers({ ...answers, [f.key]: v || undefined })} options={[{ label: 'Default', value: '' }, ...f.options.map((o) => ({ label: o.label, value: String(o.value) }))]} />
                        ) : f.type === 'dimensions' ? (
                            <TextField key={f.key} label={(f.label || f.key) + ' (W×H)'} value={answers[f.key] ? `${answers[f.key].w}x${answers[f.key].h}` : ''} onChange={(v) => { const [w, h] = v.split(/x|×/i).map(Number); setAnswers({ ...answers, [f.key]: { w, h } }); }} placeholder="60x30" autoComplete="off" />
                        ) : f.type === 'checkbox' ? null : (
                            <TextField key={f.key} label={f.label || f.key} value={String(answers[f.key] ?? '')} onChange={(v) => setAnswers({ ...answers, [f.key]: f.type === 'number' ? (v === '' ? undefined : Number(v)) : v })} autoComplete="off" />
                        ),
                    )}
                    <TextField label="Quantity" type="number" value={quantity} onChange={setQuantity} autoComplete="off" />
                </InlineGrid>
                {browser.errors.length > 0 && <Banner tone="warning">{browser.errors.map((e) => (e.field ? e.field + ': ' : '') + e.code.replaceAll('_', ' ')).join(' · ')}</Banner>}
                <DataTable columnContentTypes={['text', 'numeric']} headings={['Line', 'Amount']} rows={browser.price.lines.map((l) => [l.label, l.percent != null ? (l.percent > 0 ? '+' : '') + l.percent + '%' : money(l.cents, currency)])} totals={['', money(browser.price.unit_cents, currency)]} showTotalsInFooter />
                <InlineStack gap="300" blockAlign="center">
                    <Text>Browser engine: <b>{money(browser.price.unit_cents, currency)}</b> × {Number(quantity) || 1} = {money(browser.price.total_cents, currency)}</Text>
                    <Button onClick={runServer}>Check server price</Button>
                    {server && <Badge tone={match ? 'success' : 'critical'}>{match ? 'Server matches ✓' : 'Server: ' + money(server.price.unit_cents, currency)}</Badge>}
                </InlineStack>
            </BlockStack>
        </Card>
    );
}
