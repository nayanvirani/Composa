import { router, usePage } from '@inertiajs/react';
import { Badge, Banner, BlockStack, Button, Card, DataTable, InlineGrid, InlineStack, Page, Select, Text } from '@shopify/polaris';
import FunnelBars from '@/components/FunnelBars';
import { download, toast } from '@/lib/api';
import { money } from '@/lib/format';

export default function Analytics({ level, report, configurators, filters }) {
    const { shop } = usePage().props;
    const go = (patch) => router.get('/app/analytics', { ...filters, ...patch }, { preserveState: true, replace: true });
    const full = level === 'full' || level === 'export';

    if (!report) {
        return (
            <Page title="Analytics">
                <Banner tone="info" title="Analytics are included from the Starter plan" action={{ content: 'Plan & usage', url: '/app/billing' }}>
                    See configurator opens, step drop-off, add-to-cart rate and revenue from configured items.
                </Banner>
            </Page>
        );
    }

    return (
        <Page
            title="Analytics"
            secondaryActions={[{ content: level === 'export' ? 'Export CSV' : 'Export CSV · Pro', disabled: level !== 'export', onAction: () => download(`analytics/export?days=${filters.days}`, 'composa-analytics.csv').catch((e) => toast(e.message, true)) }]}
        >
            <BlockStack gap="400">
                <InlineStack gap="200">
                    <Select label="Range" labelInline value={String(filters.days)} onChange={(v) => go({ days: v })} options={[{ label: 'Last 7 days', value: '7' }, { label: 'Last 30 days', value: '30' }, { label: 'Last 90 days', value: '90' }]} />
                    <Select label="Configurator" labelInline value={String(filters.configurator || '')} onChange={(v) => go({ configurator: v || undefined })} options={[{ label: 'All configurators', value: '' }, ...configurators.map((c) => ({ label: c.name, value: String(c.id) }))]} />
                </InlineStack>
                <InlineGrid columns={{ xs: 2, md: 5 }} gap="300">
                    <Kpi label="Opens" value={report.opens.toLocaleString()} sub={`${report.opens_previous.toLocaleString()} previous period`} />
                    <Kpi label="Add-to-cart rate" value={report.cart_rate + '%'} sub={report.add_to_carts + ' add to carts'} />
                    <Kpi label="Orders" value={report.orders} sub="Configured lines" />
                    <Kpi label="Configured revenue" value={money(report.revenue_cents, shop.currency)} sub="Charged amounts" />
                    <Kpi label="Avg. configured order" value={money(report.aov_cents, shop.currency)} sub="Per configured line" />
                </InlineGrid>
                <InlineGrid columns={{ xs: 1, md: 2 }} gap="400">
                    <Card>
                        <BlockStack gap="300">
                            <InlineStack align="space-between"><Text as="h3" variant="headingSm">Funnel by step</Text>{!full && <Badge>Growth+ for full funnel</Badge>}</InlineStack>
                            {full ? <FunnelBars rows={report.funnel} /> : <Text tone="subdued">Opens {report.opens} → add to cart {report.add_to_carts}.</Text>}
                        </BlockStack>
                    </Card>
                    <Card>
                        <BlockStack gap="300">
                            <Text as="h3" variant="headingSm">Most chosen options</Text>
                            {report.choices.length === 0 && <Text tone="subdued">No choices recorded yet.</Text>}
                            {report.choices.map((c) => (
                                <BlockStack key={c.field} gap="100">
                                    <Text variant="bodySm" fontWeight="semibold">{c.field}</Text>
                                    {c.options.map((o) => (
                                        <InlineGrid key={o.value} columns="140px 1fr 44px" gap="200" alignItems="center">
                                            <Text variant="bodySm">{o.value}</Text>
                                            <div style={{ height: 10, background: 'var(--p-color-bg-fill-secondary)', borderRadius: 3 }}><div style={{ height: 10, width: o.percent + '%', background: 'var(--p-color-bg-fill-brand)', borderRadius: 3 }} /></div>
                                            <Text variant="bodySm" alignment="end">{o.percent}%</Text>
                                        </InlineGrid>
                                    ))}
                                </BlockStack>
                            ))}
                        </BlockStack>
                    </Card>
                </InlineGrid>
                <Card padding="0">
                    <DataTable
                        columnContentTypes={['text', 'numeric', 'numeric', 'numeric', 'numeric', 'numeric']}
                        headings={['Configurator', 'Opens', 'Add-to-cart rate', 'Orders', 'Revenue', 'Avg. order']}
                        rows={report.by_configurator.map((r) => [r.name, r.opens.toLocaleString(), r.cart_rate + '%', r.orders, money(r.revenue_cents, shop.currency), money(r.aov_cents, shop.currency)])}
                    />
                </Card>
            </BlockStack>
        </Page>
    );
}

function Kpi({ label, value, sub }) {
    return (
        <Card>
            <BlockStack gap="100">
                <Text variant="bodySm" tone="subdued">{label}</Text>
                <Text as="p" variant="headingLg">{value}</Text>
                <Text variant="bodyXs" tone="subdued">{sub}</Text>
            </BlockStack>
        </Card>
    );
}
