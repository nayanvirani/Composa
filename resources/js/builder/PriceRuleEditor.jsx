import { BlockStack, Button, InlineGrid, InlineStack, Select, Text, TextField } from '@shopify/polaris';

export const PRICE_TYPES = [
    { value: '', label: 'No price' },
    { value: 'fixed', label: 'Fixed amount' },
    { value: 'percent', label: 'Percent of subtotal' },
    { value: 'per_char', label: 'Per character' },
    { value: 'per_unit', label: 'Per unit (value or area)' },
    { value: 'tiered', label: 'Tiered' },
    { value: 'table', label: 'Lookup table' },
    { value: 'formula', label: 'Formula' },
];

/** Edits one price rule ({type, …params}); disables types the plan doesn't include. */
export default function PriceRuleEditor({ rule, onChange, allowed = [], tables = {}, label = 'Price rule', fieldType }) {
    const r = rule || {};
    const set = (patch) => onChange({ ...r, ...patch });
    const options = PRICE_TYPES.map((t) => ({ ...t, disabled: t.value !== '' && !allowed.includes(t.value), label: t.label + (t.value && !allowed.includes(t.value) ? ' (upgrade)' : '') }));

    return (
        <BlockStack gap="200">
            <Select label={label} value={r.type || ''} options={options} onChange={(type) => onChange(type ? { type } : null)} />
            {r.type === 'fixed' && <TextField label="Amount" type="number" step={0.01} value={String(r.amount ?? '')} onChange={(v) => set({ amount: v === '' ? '' : Number(v) })} autoComplete="off" helpText="Negative amounts lower the price." />}
            {r.type === 'percent' && <TextField label="Percent" type="number" suffix="%" value={String(r.percent ?? '')} onChange={(v) => set({ percent: Number(v) })} autoComplete="off" />}
            {r.type === 'per_char' && (
                <InlineGrid columns={2} gap="200">
                    <TextField label="Per character" type="number" step={0.01} value={String(r.amount ?? '')} onChange={(v) => set({ amount: Number(v) })} autoComplete="off" />
                    <Select label="Spaces" value={r.count_spaces ? 'yes' : 'no'} onChange={(v) => set({ count_spaces: v === 'yes' })} options={[{ label: 'Not counted', value: 'no' }, { label: 'Counted', value: 'yes' }]} />
                </InlineGrid>
            )}
            {r.type === 'per_unit' && (
                <InlineGrid columns={3} gap="200">
                    <TextField label="Amount per unit" type="number" step={0.01} value={String(r.amount ?? '')} onChange={(v) => set({ amount: Number(v) })} autoComplete="off" />
                    <Select label="Units" value={r.basis || (fieldType === 'dimensions' ? 'area' : 'value')} onChange={(v) => set({ basis: v })} options={[{ label: 'Field value', value: 'value' }, { label: 'Area (W × H)', value: 'area' }]} />
                    <TextField label="Divide by" type="number" value={String(r.divisor ?? 1)} onChange={(v) => set({ divisor: Number(v) || 1 })} helpText="e.g. 144 for sq in → sq ft" autoComplete="off" />
                </InlineGrid>
            )}
            {r.type === 'tiered' && (
                <BlockStack gap="200">
                    <Select label="Tier by" value={r.basis || 'quantity'} onChange={(v) => set({ basis: v })} options={[{ label: 'Cart quantity (amount per item)', value: 'quantity' }, { label: 'Field value (amount × value)', value: 'value' }]} />
                    {(r.tiers || []).map((t, i) => (
                        <InlineGrid key={i} columns="1fr 1fr 1fr auto" gap="200" alignItems="end">
                            <TextField label="From" type="number" value={String(t.min ?? '')} onChange={(v) => set({ tiers: r.tiers.map((x, j) => (j === i ? { ...x, min: Number(v) } : x)) })} autoComplete="off" />
                            <TextField label="To (blank = and up)" type="number" value={t.max == null ? '' : String(t.max)} onChange={(v) => set({ tiers: r.tiers.map((x, j) => (j === i ? { ...x, max: v === '' ? null : Number(v) } : x)) })} autoComplete="off" />
                            <TextField label="Amount" type="number" step={0.01} value={String(t.amount ?? '')} onChange={(v) => set({ tiers: r.tiers.map((x, j) => (j === i ? { ...x, amount: Number(v) } : x)) })} autoComplete="off" />
                            <Button onClick={() => set({ tiers: r.tiers.filter((_, j) => j !== i) })} accessibilityLabel="Remove tier">✕</Button>
                        </InlineGrid>
                    ))}
                    <InlineStack><Button onClick={() => set({ tiers: [...(r.tiers || []), { min: (r.tiers?.at(-1)?.max ?? 0) + 1, max: null, amount: 0 }] })}>Add tier</Button></InlineStack>
                </BlockStack>
            )}
            {r.type === 'table' && (
                <Select label="Table" value={r.table || ''} onChange={(v) => set({ table: v })} options={[{ label: 'Choose a table', value: '' }, ...Object.entries(tables || {}).map(([k, t]) => ({ label: t.label || k, value: k }))]} helpText="Create tables in the Pricing tab." />
            )}
            {r.type === 'formula' && <TextField label="Expression" monospaced value={r.expression || ''} onChange={(v) => set({ expression: v })} autoComplete="off" helpText="Use field keys and option variables; functions min, max, round, ceil, floor, abs, if." />}
            {r.type === 'table' && !Object.keys(tables || {}).length && <Text tone="caution" variant="bodySm">No tables yet.</Text>}
        </BlockStack>
    );
}
