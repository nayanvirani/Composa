import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { Badge, BlockStack, Box, Button, Card, DataTable, EmptyState, IndexTable, InlineGrid, InlineStack, Modal, Page, Pagination, Select, Spinner, Tabs, Text, TextField, Thumbnail } from '@shopify/polaris';
import { api, download, openTop, toast } from '@/lib/api';
import { date, money } from '@/lib/format';

const STATUS = { new: ['New', undefined], in_production: ['In production', 'attention'], ready: ['Ready to ship', 'info'], shipped: ['Shipped', 'success'] };
const FILTERS = [['', 'All'], ['new', 'New'], ['in_production', 'In production'], ['ready', 'Ready'], ['shipped', 'Shipped'], ['flagged', 'Flagged']];

export default function Orders({ orders, filters, counts }) {
    const [q, setQ] = useState(filters.q || '');
    const [open, setOpen] = useState(null);
    const selected = Math.max(0, FILTERS.findIndex(([v]) => v === (filters.status || '')));
    const go = (patch) => router.get('/app/orders', { ...filters, ...patch }, { preserveState: true, replace: true });

    return (
        <Page title="Configured orders">
            <Card padding="0">
                <Tabs tabs={FILTERS.map(([v, label]) => ({ id: v || 'all', content: `${label} ${counts[v || 'all'] ?? ''}`.trim() }))} selected={selected} onSelect={(i) => go({ status: FILTERS[i][0] || undefined, page: undefined })} />
                <Box padding="300">
                    <TextField label="Search" labelHidden placeholder="Search by order or customer" value={q} onChange={setQ} onBlur={() => go({ q: q || undefined })} connectedRight={<Button onClick={() => go({ q: q || undefined })}>Search</Button>} autoComplete="off" />
                </Box>
                {orders.data.length ? (
                    <IndexTable resourceName={{ singular: 'order', plural: 'orders' }} itemCount={orders.data.length} selectable={false} headings={[{ title: 'Order' }, { title: 'Configurator' }, { title: 'Total' }, { title: 'Production' }, { title: 'Price check' }, { title: '' }]}>
                        {orders.data.map((o, i) => (
                            <IndexTable.Row id={String(o.id)} key={o.id} position={i}>
                                <IndexTable.Cell>
                                    <InlineStack gap="200" blockAlign="center" wrap={false}>
                                        {o.image && <Thumbnail size="small" source={o.image} alt="" />}
                                        <BlockStack gap="050"><Text fontWeight="semibold">{o.order}</Text><Text variant="bodySm" tone="subdued">{o.customer || '—'} · {date(o.placed_at)}</Text></BlockStack>
                                    </InlineStack>
                                </IndexTable.Cell>
                                <IndexTable.Cell>{o.configurator || '—'}</IndexTable.Cell>
                                <IndexTable.Cell>{money(o.total_cents, o.currency)}</IndexTable.Cell>
                                <IndexTable.Cell><Badge tone={STATUS[o.status]?.[1]}>{STATUS[o.status]?.[0]}</Badge></IndexTable.Cell>
                                <IndexTable.Cell>{o.price_check === 'ok' ? <Badge tone="success">Signed ✓</Badge> : <Badge tone="critical">{o.price_check === 'mismatch' ? 'Mismatch' : 'Unsigned'}</Badge>}</IndexTable.Cell>
                                <IndexTable.Cell><Button onClick={() => setOpen(o.id)}>Spec</Button></IndexTable.Cell>
                            </IndexTable.Row>
                        ))}
                    </IndexTable>
                ) : (
                    <EmptyState heading="No configured orders yet" image="">
                        <p>Orders with configured items appear here with a production spec and PDF.</p>
                    </EmptyState>
                )}
                {orders.last_page > 1 && (
                    <Box padding="300">
                        <InlineStack align="center">
                            <Pagination hasPrevious={!!orders.prev_page_url} hasNext={!!orders.next_page_url} onPrevious={() => router.visit(orders.prev_page_url)} onNext={() => router.visit(orders.next_page_url)} label={`Page ${orders.current_page} of ${orders.last_page}`} />
                        </InlineStack>
                    </Box>
                )}
            </Card>
            {open && <SpecModal id={open} onClose={() => setOpen(null)} />}
        </Page>
    );
}

function SpecModal({ id, onClose }) {
    const [o, setO] = useState(null);
    const [busy, setBusy] = useState(false);
    useEffect(() => { api(`orders/${id}`).then(setO).catch((e) => toast(e.message, true)); }, [id]);

    const setStatus = async (status) => {
        const r = await api(`orders/${id}/status`, { method: 'PUT', body: { status } });
        setO({ ...o, status: r.status });
        toast('Status updated');
        router.reload({ only: ['orders', 'counts'] });
    };
    const pdf = async () => {
        setBusy(true);
        try { await download(`orders/${id}/pdf`, `spec-${o.order}.pdf`); } catch (e) { toast(e.message, true); } finally { setBusy(false); }
    };
    const file = async (f) => {
        const r = await api(`orders/${id}/files/${f.id}`);
        window.open(r.url, '_blank');
    };

    return (
        <Modal open onClose={onClose} title={o ? `${o.order} · production spec` : 'Production spec'} primaryAction={o ? { content: 'Download production PDF', onAction: pdf, loading: busy } : undefined} secondaryActions={o ? [{ content: 'Open in Shopify', onAction: () => openTop(o.admin_url) }] : []}>
            <Modal.Section>
                {!o ? <InlineStack align="center"><Spinner /></InlineStack> : (
                    <BlockStack gap="300">
                        <InlineGrid columns="96px 1fr" gap="300">
                            {o.image ? <Thumbnail source={o.image} alt="" size="large" /> : <span />}
                            <BlockStack gap="100">
                                <Text fontWeight="semibold">{o.configurator} · v{o.version}</Text>
                                <Text variant="bodySm" tone="subdued">config {o.config_id} · qty {o.quantity} · {o.cart_mode} mode</Text>
                                <Text variant="bodySm" tone={o.price_check === 'ok' ? 'success' : 'critical'}>{o.price_check === 'ok' ? 'Signature verified' : 'Price check: ' + o.price_check} · charged {money(o.total_cents, o.currency)}{o.signed_cents != null ? ' · signed ' + money(o.signed_cents * o.quantity, o.currency) : ''}</Text>
                            </BlockStack>
                        </InlineGrid>
                        <DataTable columnContentTypes={['text', 'text']} headings={['', '']} rows={o.rows.map((r) => [r.label, r.value])} />
                        {o.files.length > 0 && (
                            <BlockStack gap="100">
                                <Text fontWeight="semibold">Shopper files</Text>
                                {o.files.map((f) => <InlineStack key={f.id}><Button variant="plain" onClick={() => file(f)}>{f.name}</Button></InlineStack>)}
                            </BlockStack>
                        )}
                        <Select label="Production status" value={o.status} onChange={setStatus} options={Object.entries(STATUS).map(([v, [label]]) => ({ value: v, label }))} />
                    </BlockStack>
                )}
            </Modal.Section>
        </Modal>
    );
}
