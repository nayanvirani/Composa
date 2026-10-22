import { BlockStack, Box, Button, InlineGrid, InlineStack, Select, Text, TextField } from '@shopify/polaris';

export const OPS = [
    { value: 'eq', label: 'is' },
    { value: 'neq', label: 'is not' },
    { value: 'in', label: 'is any of' },
    { value: 'not_in', label: 'is none of' },
    { value: 'contains', label: 'contains' },
    { value: 'gt', label: 'is greater than' },
    { value: 'gte', label: 'is at least' },
    { value: 'lt', label: 'is less than' },
    { value: 'lte', label: 'is at most' },
    { value: 'empty', label: 'is empty' },
    { value: 'not_empty', label: 'is not empty' },
];

/** A condition tree: {all|any: [...]} groups of {field, op, value}; nesting needs advanced rules. */
export default function ConditionEditor({ node, onChange, fields, advanced, depth = 0 }) {
    const group = node && (node.all || node.any) ? node : { all: node && node.field ? [node] : [] };
    const kind = group.any ? 'any' : 'all';
    const items = group[kind];
    const setItems = (next) => onChange({ [kind]: next });
    const fieldOptions = [
        ...fields.map((f) => ({ label: f.label || f.key, value: f.key })),
        { label: 'Cart quantity', value: '$quantity' },
        { label: 'Shopper country', value: '$country' },
    ];

    return (
        <Box padding={depth ? '300' : '0'} borderWidth={depth ? '025' : undefined} borderColor="border" borderRadius="200">
            <BlockStack gap="200">
                <InlineStack gap="200" blockAlign="center">
                    <Text variant="bodySm">Match</Text>
                    <Select labelHidden label="Match" value={kind} onChange={(k) => onChange({ [k]: items })} options={[{ label: 'all of', value: 'all' }, { label: 'any of', value: 'any' }]} />
                </InlineStack>
                {items.map((item, i) =>
                    item.all || item.any ? (
                        <InlineStack key={i} gap="200" blockAlign="start" wrap={false}>
                            <div style={{ flex: 1 }}>
                                <ConditionEditor node={item} fields={fields} advanced={advanced} depth={depth + 1} onChange={(n) => setItems(items.map((x, j) => (j === i ? n : x)))} />
                            </div>
                            <Button onClick={() => setItems(items.filter((_, j) => j !== i))} accessibilityLabel="Remove group">✕</Button>
                        </InlineStack>
                    ) : (
                        <Row key={i} item={item} fields={fields} fieldOptions={fieldOptions} onChange={(n) => setItems(items.map((x, j) => (j === i ? n : x)))} onRemove={() => setItems(items.filter((_, j) => j !== i))} />
                    ),
                )}
                <InlineStack gap="200">
                    <Button onClick={() => setItems([...items, { field: fields[0]?.key || '$quantity', op: 'eq', value: '' }])}>Add condition</Button>
                    <Button disabled={!advanced || depth > 1} onClick={() => setItems([...items, { any: [] }])}>{advanced ? 'Add group' : 'Add group (Growth+)'}</Button>
                </InlineStack>
            </BlockStack>
        </Box>
    );
}

function Row({ item, fields, fieldOptions, onChange, onRemove }) {
    const field = fields.find((f) => f.key === item.field);
    const multi = ['in', 'not_in'].includes(item.op);
    const noValue = ['empty', 'not_empty'].includes(item.op);
    const options = field?.options?.length ? field.options.map((o) => ({ label: o.label || o.value, value: String(o.value) })) : null;

    let value = null;
    if (!noValue) {
        if (options && !multi) value = <Select labelHidden label="Value" value={String(item.value ?? '')} onChange={(v) => onChange({ ...item, value: v })} options={[{ label: 'Choose…', value: '' }, ...options]} />;
        else value = <TextField labelHidden label="Value" value={Array.isArray(item.value) ? item.value.join(', ') : String(item.value ?? '')} onChange={(v) => onChange({ ...item, value: multi ? v.split(',').map((s) => s.trim()).filter(Boolean) : v })} placeholder={multi ? 'Comma-separated values' : 'Value'} autoComplete="off" />;
    }

    return (
        <InlineGrid columns="1.2fr 1fr 1.4fr auto" gap="200" alignItems="center">
            <Select labelHidden label="Field" value={item.field} onChange={(v) => onChange({ ...item, field: v, value: '' })} options={fieldOptions} />
            <Select labelHidden label="Operator" value={item.op} onChange={(v) => onChange({ ...item, op: v })} options={OPS} />
            {value || <span />}
            <Button onClick={onRemove} accessibilityLabel="Remove condition">✕</Button>
        </InlineGrid>
    );
}
