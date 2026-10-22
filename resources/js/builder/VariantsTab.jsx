import { useMemo, useState } from 'react';
import { Badge, Banner, BlockStack, Box, Button, Card, DataTable, InlineGrid, InlineStack, RadioButton, Select, Text } from '@shopify/polaris';
import { LAYOUTS } from './fieldTypes';
import { api, toast } from '@/lib/api';
import { money, relative } from '@/lib/format';

const MODES = [
    ['variants', 'Variants drive the steps', 'Each Shopify option becomes a step. The exact variant goes to cart with its own price and stock.'],
    ['mixed', 'Mixed', 'Some options are steps; others are prefilled from the product page or fixed.'],
    ['base', 'Base variant only', 'Ignore variants. One variant goes to cart and every choice is a custom option priced by rules.'],
];

export default function VariantsTab({ configurator, definition, update, product, setMeta, shopCurrency }) {
    const [mode, setMode] = useState(configurator.variant_mode || 'variants');
    const options = (product?.options || []).filter((o) => !(o.name === 'Title' && o.values.length === 1));
    const existing = Object.fromEntries((definition.steps || []).filter((s) => s.source === 'shopify_option').map((s, i) => [s.shopify_option, { order: i, field: s.fields?.[0] }]));
    const [choices, setChoices] = useState(() => Object.fromEntries(options.map((o, i) => [o.name, { order: existing[o.name]?.order ?? i, show: existing[o.name] ? existing[o.name].field?.mode || 'pick' : 'pick', layout: existing[o.name]?.field?.layout || '' }])));
    const [busy, setBusy] = useState(false);

    if (!product) return <Banner tone="info" title="Choose a base product first">Variant mapping uses the base product’s Shopify options. Set it in the Display tab.</Banner>;
    if (!options.length) return <Banner tone="info" title="This product has no variants">All steps are custom fields; the single variant goes to cart and rules set the price.</Banner>;

    const ordered = [...options].sort((a, b) => (choices[a.name]?.order ?? 0) - (choices[b.name]?.order ?? 0));
    const moveOpt = (name, d) => {
        const names = ordered.map((o) => o.name);
        const i = names.indexOf(name);
        const j = i + d;
        if (j < 0 || j >= names.length) return;
        [names[i], names[j]] = [names[j], names[i]];
        setChoices(Object.fromEntries(names.map((n, k) => [n, { ...choices[n], order: k }])));
    };

    const apply = async () => {
        setBusy(true);
        try {
            const body = { definition, variant_mode: mode, choices: Object.fromEntries(Object.entries(choices).map(([k, c]) => [k, { order: c.order, show: mode === 'variants' ? 'pick' : c.show, layout: c.layout || undefined }])) };
            const r = await api(`configurators/${configurator.id}/variants`, { method: 'POST', body });
            update(r.definition);
            setMeta({ variant_mode: mode });
            toast(mode === 'base' ? 'Variant steps removed' : 'Shopify options mapped to steps');
        } catch (e) {
            toast(e.message, true);
        } finally {
            setBusy(false);
        }
    };

    return (
        <BlockStack gap="400">
            <Card>
                <BlockStack gap="300">
                    <InlineStack align="space-between">
                        <BlockStack gap="050">
                            <Text fontWeight="semibold">{product.title}</Text>
                            <Text tone="subdued" variant="bodySm">{options.length} Shopify options · {product.variants.length} variants{configurator.variant_synced_at ? ' · synced ' + relative(configurator.variant_synced_at) : ''}</Text>
                        </BlockStack>
                        <InlineStack gap="100">{options.map((o) => <Badge key={o.name}>{o.name} · {o.values.length}</Badge>)}</InlineStack>
                    </InlineStack>
                    <InlineGrid columns={{ xs: 1, md: 3 }} gap="300">
                        {MODES.map(([value, title, body]) => (
                            <Box key={value} padding="300" borderWidth="025" borderRadius="200" borderColor={mode === value ? 'border-emphasis' : 'border'}>
                                <RadioButton label={title} helpText={body} checked={mode === value} onChange={() => setMode(value)} />
                            </Box>
                        ))}
                    </InlineGrid>
                </BlockStack>
            </Card>

            {mode !== 'base' && (
                <Card>
                    <BlockStack gap="300">
                        <Text as="h3" variant="headingSm">Shopify options → configurator steps</Text>
                        {ordered.map((o, i) => (
                            <InlineGrid key={o.name} columns="auto 1fr 1fr 1fr" gap="300" alignItems="center">
                                <InlineStack gap="100">
                                    <Button size="slim" onClick={() => moveOpt(o.name, -1)} disabled={i === 0} accessibilityLabel={`Move ${o.name} up`}>↑</Button>
                                    <Button size="slim" onClick={() => moveOpt(o.name, 1)} disabled={i === ordered.length - 1} accessibilityLabel={`Move ${o.name} down`}>↓</Button>
                                </InlineStack>
                                <BlockStack gap="050">
                                    <Text fontWeight="semibold">{o.name} · step {i + 1}</Text>
                                    <Text variant="bodySm" tone="subdued">{o.values.join(', ')}</Text>
                                </BlockStack>
                                <Select label="Shopper" labelHidden disabled={mode === 'variants'} value={mode === 'variants' ? 'pick' : choices[o.name]?.show} onChange={(v) => setChoices({ ...choices, [o.name]: { ...choices[o.name], show: v } })} options={[{ label: 'Shopper picks', value: 'pick' }, { label: 'Prefill from product page', value: 'prefill' }, { label: 'Fixed value', value: 'fixed' }, { label: 'Leave out', value: 'skip' }]} />
                                <Select label="Layout" labelHidden value={choices[o.name]?.layout || ''} onChange={(v) => setChoices({ ...choices, [o.name]: { ...choices[o.name], layout: v } })} options={[{ label: 'Default for this option name', value: '' }, ...LAYOUTS]} />
                            </InlineGrid>
                        ))}
                        <Text variant="bodySm" tone="subdued">Shoppers’ picks resolve to one exact Shopify variant; its price is the base and custom options add on top. Sold-out and missing combinations are disabled automatically.</Text>
                    </BlockStack>
                </Card>
            )}
            <InlineStack align="end"><Button variant="primary" onClick={apply} loading={busy}>{mode === 'base' ? 'Use base variant only' : 'Map options to steps'}</Button></InlineStack>

            <Matrix product={product} currency={shopCurrency} />

            {configurator.variant_changes?.length > 0 && (
                <Card>
                    <BlockStack gap="200">
                        <Text as="h3" variant="headingSm">Changes from Shopify</Text>
                        {configurator.variant_changes.slice(0, 10).map((c, i) => (
                            <InlineStack key={i} gap="200">
                                <Badge tone={c.kind === 'value_added' ? 'info' : c.kind === 'value_removed' ? 'critical' : 'attention'}>{c.kind.replace('_', ' ')}</Badge>
                                <Text>{c.option}{c.value ? ` “${c.value}”` : ''} · {relative(c.at)}</Text>
                            </InlineStack>
                        ))}
                        <Text variant="bodySm" tone="subdued">Press “Map options to steps” to bring new values in; removed variants are disabled for shoppers automatically.</Text>
                    </BlockStack>
                </Card>
            )}
        </BlockStack>
    );
}

/** Combinations grid: price and stock of every variant, by the first two options. */
function Matrix({ product, currency }) {
    const opts = product.options.filter((o) => !(o.name === 'Title' && o.values.length === 1));
    const [third, setThird] = useState(opts[2]?.values[0] || '');
    const rows = useMemo(() => {
        if (opts.length < 2) return product.variants.map((v) => [v.title, money(v.price_cents, currency), v.available ? (v.inventory ?? '—') + ' in stock' : 'Sold out']);
        return opts[0].values.map((a) => [
            a,
            ...opts[1].values.map((b) => {
                const v = product.variants.find((x) => x.options[opts[0].name] === a && x.options[opts[1].name] === b && (!opts[2] || x.options[opts[2].name] === third));
                if (!v) return '— not created';
                return `${money(v.price_cents, currency)} · ${v.available ? (v.inventory != null && v.inventory < 5 ? '! ' + v.inventory + ' left' : 'in stock') : '✕ sold out'}`;
            }),
        ]);
    }, [product, third]);

    return (
        <Card>
            <BlockStack gap="300">
                <InlineStack align="space-between">
                    <Text as="h3" variant="headingSm">Variant combinations</Text>
                    {opts[2] && <Select label={opts[2].name} labelInline value={third} onChange={setThird} options={opts[2].values} />}
                </InlineStack>
                <DataTable
                    columnContentTypes={['text', ...(opts.length >= 2 ? opts[1].values.map(() => 'text') : ['text', 'text'])]}
                    headings={opts.length >= 2 ? [opts[0].name + ' \\ ' + opts[1].name, ...opts[1].values] : ['Variant', 'Price', 'Stock']}
                    rows={rows}
                />
                <Text variant="bodySm" tone="subdued">Prices and stock come from Shopify and update by webhook. Edit variants in Shopify admin.</Text>
            </BlockStack>
        </Card>
    );
}
