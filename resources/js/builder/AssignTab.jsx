import { useEffect, useState } from 'react';
import { Badge, Banner, BlockStack, Button, Card, Checkbox, InlineGrid, InlineStack, ProgressBar, Tabs, Text, TextField } from '@shopify/polaris';
import { api, toast } from '@/lib/api';

const TYPES = [
    { id: 'product', content: 'Products' },
    { id: 'collection', content: 'Collections' },
    { id: 'tag', content: 'Tags' },
    { id: 'vendor', content: 'Vendors' },
];

/** Which products the configurator appears on, resolved top to bottom by priority. */
export default function AssignTab({ configurator, initial, conflicts: initialConflicts, usage }) {
    const [assignments, setAssignments] = useState(initial || []);
    const [conflicts, setConflicts] = useState(initialConflicts || []);
    const [meter, setMeter] = useState({ products: usage?.products?.used ?? 0, limit: usage?.products?.limit ?? null });
    const [tab, setTab] = useState(0);
    const [q, setQ] = useState('');
    const [results, setResults] = useState([]);
    const [picked, setPicked] = useState({});
    const [busy, setBusy] = useState(false);
    const type = TYPES[tab].id;

    const apply = (r) => {
        setAssignments(r.assignments);
        setConflicts(r.conflicts);
        setMeter(r.usage);
    };

    useEffect(() => {
        if (type === 'product' || type === 'collection') return;
        const t = setTimeout(() => api('products/search', { query: { type, q } }).then((r) => setResults(r.items)).catch(() => setResults([])), 250);
        return () => clearTimeout(t);
    }, [type, q]);

    const add = async (targets) => {
        if (!targets.length) return;
        setBusy(true);
        try {
            apply(await api(`configurators/${configurator.id}/assignments`, { method: 'POST', body: { targets } }));
            setPicked({});
            toast('Assignment added');
        } catch (e) {
            toast(e.message, true);
        } finally {
            setBusy(false);
        }
    };

    const pick = async () => {
        if (!window.shopify?.resourcePicker) return toast('Open Composa inside Shopify admin to pick products', true);
        const sel = await window.shopify.resourcePicker({ type, action: 'add', multiple: true });
        if (sel?.length) add(sel.map((s) => ({ type, id: s.id, label: s.title })));
    };

    const move = async (i, d) => {
        const ids = assignments.map((a) => a.id);
        const j = i + d;
        if (j < 0 || j >= ids.length) return;
        [ids[i], ids[j]] = [ids[j], ids[i]];
        apply(await api(`configurators/${configurator.id}/assignments/order`, { method: 'PUT', body: { ids } }));
    };

    const remove = async (a) => apply(await api(`configurators/${configurator.id}/assignments/${a.id}`, { method: 'DELETE' }));

    const adding = Object.values(picked).reduce((s, x) => s + (x.count || 0), 0);
    const total = meter.products + adding;

    return (
        <BlockStack gap="400">
            <Card>
                <BlockStack gap="200">
                    <InlineStack align="space-between">
                        <Text>Assigned products across all configurators: <b>{total}{meter.limit !== null ? ' / ' + meter.limit : ''}</b></Text>
                        <Text tone={meter.limit !== null && total > meter.limit ? 'critical' : 'subdued'} variant="bodySm">{meter.limit !== null && total > meter.limit ? 'Over your plan limit — upgrade or remove an assignment' : 'Collections, tags and vendors count every product they cover'}</Text>
                    </InlineStack>
                    {meter.limit !== null && <ProgressBar size="small" progress={Math.min(100, (total / meter.limit) * 100)} tone={total > meter.limit ? 'critical' : 'primary'} />}
                </BlockStack>
            </Card>
            <InlineGrid columns={{ xs: 1, md: 2 }} gap="400">
                <Card>
                    <BlockStack gap="300">
                        <Text as="h3" variant="headingSm">Current rules · top wins</Text>
                        {assignments.length === 0 && <Text tone="subdued">Not assigned yet. Shoppers won’t see this configurator until it’s assigned and published.</Text>}
                        {assignments.map((a, i) => (
                            <InlineStack key={a.id} align="space-between" blockAlign="center">
                                <InlineStack gap="200" blockAlign="center">
                                    <Text tone="subdued">{i + 1}</Text>
                                    <BlockStack gap="050">
                                        <Text fontWeight="semibold">{a.target_label}</Text>
                                        <Text variant="bodySm" tone="subdued">{a.target_type} · {a.product_count} product{a.product_count === 1 ? '' : 's'}</Text>
                                    </BlockStack>
                                </InlineStack>
                                <InlineStack gap="100">
                                    <Button size="slim" onClick={() => move(i, -1)} disabled={i === 0}>↑</Button>
                                    <Button size="slim" onClick={() => move(i, 1)} disabled={i === assignments.length - 1}>↓</Button>
                                    <Button size="slim" tone="critical" onClick={() => remove(a)}>Remove</Button>
                                </InlineStack>
                            </InlineStack>
                        ))}
                        {conflicts.length > 0 && (
                            <Banner tone="warning" title="Also matched by other configurators">
                                {conflicts.map((c) => `${c.type} “${c.target}” → ${c.configurator}`).join('; ')}. The one with the higher priority wins on each product.
                            </Banner>
                        )}
                    </BlockStack>
                </Card>
                <Card padding="0">
                    <Tabs tabs={TYPES} selected={tab} onSelect={(i) => { setTab(i); setPicked({}); setQ(''); }}>
                        <div style={{ padding: 16 }}>
                            {type === 'product' || type === 'collection' ? (
                                <BlockStack gap="300">
                                    <Text tone="subdued">Choose {type === 'product' ? 'products' : 'collections'} with Shopify’s picker.</Text>
                                    <InlineStack><Button variant="primary" onClick={pick} loading={busy}>Choose {type === 'product' ? 'products' : 'collections'}</Button></InlineStack>
                                </BlockStack>
                            ) : (
                                <BlockStack gap="200">
                                    <TextField label={`Search ${type}s`} labelHidden value={q} onChange={setQ} placeholder={`Search ${type}s`} autoComplete="off" />
                                    {results.map((r) => (
                                        <Checkbox key={r.id} label={r.label} checked={!!picked[r.id]} onChange={(v) => setPicked(v ? { ...picked, [r.id]: { type, id: r.id, label: r.label } } : Object.fromEntries(Object.entries(picked).filter(([k]) => k !== r.id)))} />
                                    ))}
                                    <InlineStack align="space-between">
                                        <Badge>{Object.keys(picked).length} selected</Badge>
                                        <Button variant="primary" disabled={!Object.keys(picked).length} loading={busy} onClick={() => add(Object.values(picked))}>Add to rules</Button>
                                    </InlineStack>
                                </BlockStack>
                            )}
                        </div>
                    </Tabs>
                </Card>
            </InlineGrid>
        </BlockStack>
    );
}
