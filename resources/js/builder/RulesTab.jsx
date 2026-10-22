import { BlockStack, Button, ButtonGroup, Card, Checkbox, InlineGrid, InlineStack, Select, Text, TextField } from '@shopify/polaris';
import ConditionEditor from './ConditionEditor';

export const ACTIONS = [
    { value: 'hide_option', label: 'Hide option', needs: ['field', 'option'] },
    { value: 'show_option', label: 'Show option', needs: ['field', 'option'] },
    { value: 'disable_option', label: 'Disable option', needs: ['field', 'option'] },
    { value: 'hide_field', label: 'Hide field', needs: ['field'] },
    { value: 'show_field', label: 'Show field', needs: ['field'] },
    { value: 'hide_step', label: 'Hide step', needs: ['step'] },
    { value: 'show_step', label: 'Show step', needs: ['step'] },
    { value: 'require', label: 'Make required', needs: ['field'] },
    { value: 'unrequire', label: 'Make optional', needs: ['field'] },
    { value: 'set_default', label: 'Set default', needs: ['field', 'value'] },
    { value: 'add_price', label: 'Add price', needs: ['amount'] },
    { value: 'set_layer', label: 'Set preview layer image', needs: ['layer', 'image'] },
    { value: 'set_variant_option', label: 'Set Shopify option', needs: ['option_name', 'value'] },
];

export default function RulesTab({ definition, update, limits, features }) {
    const rules = definition.rules || [];
    const steps = definition.steps || [];
    const fields = steps.flatMap((s) => s.fields || []);
    const advanced = features?.rules_level === 'advanced';
    const setRules = (next) => update({ ...definition, rules: next });
    const setRule = (i, patch) => setRules(rules.map((r, j) => (j === i ? { ...r, ...patch } : r)));

    return (
        <BlockStack gap="400">
            <InlineStack align="space-between">
                <Text as="h2" variant="headingMd">Conditional rules · {rules.length}{limits.rules ? ' of ' + limits.rules : ''}</Text>
                <Button variant="primary" disabled={limits.rules && rules.length >= limits.rules} onClick={() => setRules([...rules, { id: 'r' + Date.now().toString(36), name: 'New rule', enabled: true, conditions: { all: [{ field: fields[0]?.key || '$quantity', op: 'eq', value: '' }] }, actions: [{ type: 'hide_option', field: fields[0]?.key }] }])}>Add rule</Button>
            </InlineStack>
            {rules.length === 0 && <Card><Text tone="subdued">Rules show, hide, disable, require, set defaults or add price based on earlier answers — e.g. IF shape is Princess THEN hide 2.0 ct.</Text></Card>}
            {rules.map((rule, i) => (
                <Card key={rule.id || i}>
                    <BlockStack gap="300">
                        <InlineStack align="space-between" blockAlign="center">
                            <InlineStack gap="300" blockAlign="center">
                                <TextField labelHidden label="Rule name" value={rule.name || ''} onChange={(v) => setRule(i, { name: v })} autoComplete="off" />
                                <Checkbox label="Enabled" checked={rule.enabled !== false} onChange={(v) => setRule(i, { enabled: v })} />
                            </InlineStack>
                            <ButtonGroup>
                                <Button size="slim" onClick={() => setRules([...rules.slice(0, i + 1), { ...structuredClone(rule), id: 'r' + Date.now().toString(36), name: rule.name + ' (copy)' }, ...rules.slice(i + 1)])}>Duplicate</Button>
                                <Button size="slim" tone="critical" onClick={() => setRules(rules.filter((_, j) => j !== i))}>Delete</Button>
                            </ButtonGroup>
                        </InlineStack>
                        <Text fontWeight="bold" tone="caution">IF</Text>
                        <ConditionEditor node={rule.conditions} fields={fields} advanced={advanced} onChange={(n) => setRule(i, { conditions: n })} />
                        <Text fontWeight="bold" tone="success">THEN</Text>
                        {(rule.actions || []).map((a, ai) => (
                            <ActionRow key={ai} action={a} steps={steps} fields={fields} layers={definition.layers || []} onChange={(n) => setRule(i, { actions: rule.actions.map((x, k) => (k === ai ? n : x)) })} onRemove={() => setRule(i, { actions: rule.actions.filter((_, k) => k !== ai) })} />
                        ))}
                        <InlineStack><Button onClick={() => setRule(i, { actions: [...(rule.actions || []), { type: 'hide_option', field: fields[0]?.key }] })}>Add action</Button></InlineStack>
                        <TextField label="Message shown to shoppers (optional)" value={rule.message || ''} onChange={(v) => setRule(i, { message: v })} placeholder="2.0 ct isn’t available for Princess cut." autoComplete="off" />
                    </BlockStack>
                </Card>
            ))}
            <Text variant="bodySm" tone="subdued">Rules run in order in the shopper’s browser and again on the server before the price is signed. Nested AND/OR groups need the Growth plan or higher.</Text>
        </BlockStack>
    );
}

function ActionRow({ action, steps, fields, layers, onChange, onRemove }) {
    const def = ACTIONS.find((x) => x.value === action.type) || ACTIONS[0];
    const field = fields.find((f) => f.key === action.field);
    const set = (patch) => onChange({ ...action, ...patch });
    const variantOptions = fields.filter((f) => f.variant_option);

    return (
        <InlineGrid columns="1fr 1fr 1fr auto" gap="200" alignItems="center">
            <Select labelHidden label="Action" value={action.type} onChange={(t) => onChange({ type: t, field: action.field })} options={ACTIONS} />
            {def.needs.includes('step') && <Select labelHidden label="Step" value={action.step || ''} onChange={(v) => set({ step: v })} options={[{ label: 'Choose step', value: '' }, ...steps.map((s) => ({ label: s.title, value: s.key }))]} />}
            {def.needs.includes('field') && <Select labelHidden label="Field" value={action.field || ''} onChange={(v) => set({ field: v, option: undefined })} options={[{ label: 'Choose field', value: '' }, ...fields.map((f) => ({ label: f.label || f.key, value: f.key }))]} />}
            {def.needs.includes('option') && <Select labelHidden label="Option" value={String(action.option ?? '')} onChange={(v) => set({ option: v })} options={[{ label: 'Choose option', value: '' }, ...(field?.options || []).map((o) => ({ label: o.label || o.value, value: String(o.value) }))]} />}
            {def.needs.includes('value') && !def.needs.includes('option_name') && (field?.options?.length ? <Select labelHidden label="Value" value={String(action.value ?? '')} onChange={(v) => set({ value: v })} options={[{ label: 'Choose', value: '' }, ...field.options.map((o) => ({ label: o.label || o.value, value: String(o.value) }))]} /> : <TextField labelHidden label="Value" value={String(action.value ?? '')} onChange={(v) => set({ value: v })} autoComplete="off" />)}
            {def.needs.includes('amount') && (
                <InlineStack gap="100" wrap={false}>
                    <Select labelHidden label="Kind" value={action.percent != null ? 'percent' : 'amount'} onChange={(k) => onChange(k === 'percent' ? { type: action.type, percent: action.amount ?? 0, label: action.label } : { type: action.type, amount: action.percent ?? 0, label: action.label })} options={[{ label: 'Amount', value: 'amount' }, { label: 'Percent', value: 'percent' }]} />
                    <TextField labelHidden label="Amount" type="number" value={String(action.percent ?? action.amount ?? '')} onChange={(v) => set(action.percent != null ? { percent: Number(v) } : { amount: Number(v) })} autoComplete="off" />
                </InlineStack>
            )}
            {def.needs.includes('amount') && <TextField labelHidden label="Label" value={action.label || ''} placeholder="Label, e.g. GIA certificate" onChange={(v) => set({ label: v })} autoComplete="off" />}
            {def.needs.includes('layer') && <Select labelHidden label="Layer" value={action.layer || ''} onChange={(v) => set({ layer: v })} options={[{ label: 'Choose layer', value: '' }, ...layers.map((l) => ({ label: l.name || l.id, value: l.id }))]} />}
            {def.needs.includes('image') && <TextField labelHidden label="Image URL" value={action.image || ''} onChange={(v) => set({ image: v })} autoComplete="off" />}
            {def.needs.includes('option_name') && (
                <>
                    <Select labelHidden label="Shopify option" value={action.option_name || ''} onChange={(v) => set({ option_name: v })} options={[{ label: 'Choose option', value: '' }, ...variantOptions.map((f) => ({ label: f.variant_option, value: f.variant_option }))]} />
                    <Select labelHidden label="Value" value={String(action.value ?? '')} onChange={(v) => set({ value: v })} options={[{ label: 'Choose', value: '' }, ...(variantOptions.find((f) => f.variant_option === action.option_name)?.options || []).map((o) => ({ label: o.label, value: String(o.value) }))]} />
                </>
            )}
            {def.needs.length === 1 && <span />}
            <Button onClick={onRemove} accessibilityLabel="Remove action">✕</Button>
        </InlineGrid>
    );
}
