import { router, usePage } from '@inertiajs/react';
import { Badge, BlockStack, Box, Button, Card, Icon, InlineGrid, InlineStack, Layout, Page, ProgressBar, ResourceItem, ResourceList, Text } from '@shopify/polaris';
import { CheckCircleIcon } from '@shopify/polaris-icons';
import UsageMeters from '@/components/UsageMeters';
import FunnelBars from '@/components/FunnelBars';
import { api, openTop } from '@/lib/api';
import { money } from '@/lib/format';

const STEPS = {
    plan: ['Choose a plan', 'Plans start with a 14-day free trial, billed by Shopify.'],
    template: ['Create a configurator', 'Start from a template or a blank one.'],
    embed: ['Enable the app embed', 'One click in your theme editor adds the Customize button and cart display.'],
    assign: ['Assign it to products', 'Products, collections, tags or vendors.'],
    publish: ['Publish', 'Shoppers see it as soon as it’s published.'],
};

export default function Home({ checklist, dismissed, configurators, analytics, templates }) {
    const { shop, usage } = usePage().props;
    const done = checklist.filter((c) => c.done).length;
    const action = {
        plan: () => openTop(shop.pricingUrl),
        template: () => router.visit('/app/configurators/new'),
        embed: () => router.visit('/app/onboarding/embed'),
        assign: () => router.visit('/app/configurators'),
        publish: () => router.visit('/app/configurators'),
    };

    return (
        <Page title={`Welcome back${shop?.name ? ', ' + shop.name : ''}`} primaryAction={{ content: 'Create configurator', url: '/app/configurators/new' }} secondaryActions={[{ content: 'Plan & usage', url: '/app/billing' }]}>
            <Layout>
                {!dismissed && done < checklist.length && (
                    <Layout.Section>
                        <Card>
                            <BlockStack gap="300">
                                <InlineStack align="space-between">
                                    <Text as="h2" variant="headingMd">Set up Composa</Text>
                                    <InlineStack gap="200" blockAlign="center">
                                        <Text tone="subdued">{done} of {checklist.length} done</Text>
                                        <Button variant="plain" onClick={() => api('onboarding', { method: 'POST', body: { key: 'checklist_dismissed', value: true } }).then(() => router.reload())}>Dismiss</Button>
                                    </InlineStack>
                                </InlineStack>
                                <ProgressBar progress={(done / checklist.length) * 100} size="small" tone="primary" />
                                {checklist.map((c) => (
                                    <InlineStack key={c.key} align="space-between" blockAlign="center" wrap={false}>
                                        <InlineStack gap="300" blockAlign="center" wrap={false}>
                                            <Box width="20px">{c.done ? <Icon source={CheckCircleIcon} tone="success" /> : <Box borderWidth="025" borderColor="border" borderRadius="full" minHeight="18px" />}</Box>
                                            <BlockStack gap="050">
                                                <Text fontWeight="semibold" textDecorationLine={c.done ? 'line-through' : undefined}>{STEPS[c.key][0]}</Text>
                                                <Text tone="subdued" variant="bodySm">{STEPS[c.key][1]}</Text>
                                            </BlockStack>
                                        </InlineStack>
                                        {!c.done && <Button onClick={action[c.key]}>Start</Button>}
                                    </InlineStack>
                                ))}
                            </BlockStack>
                        </Card>
                    </Layout.Section>
                )}

                <Layout.Section>
                    <Card>
                        <BlockStack gap="400">
                            <InlineStack align="space-between">
                                <Text as="h2" variant="headingMd">Plan usage {shop?.plan ? '· ' + shop.plan.name : ''}</Text>
                                <Button variant="plain" url="/app/billing">View plan & limits</Button>
                            </InlineStack>
                            <UsageMeters meters={usage} keys={['configurators', 'products', 'steps', 'storage_mb']} />
                        </BlockStack>
                    </Card>
                </Layout.Section>

                {analytics && (
                    <Layout.Section>
                        <BlockStack gap="400">
                            <InlineGrid columns={{ xs: 2, md: 4 }} gap="400">
                                <Kpi label="Configurator opens" value={analytics.opens.toLocaleString()} sub={`${analytics.opens_previous.toLocaleString()} previous 30 days`} />
                                <Kpi label="Open → add to cart" value={analytics.cart_rate + '%'} sub="Last 30 days" />
                                <Kpi label="Configured revenue" value={money(analytics.revenue_cents, shop.currency)} sub={analytics.orders + ' orders'} />
                                <Kpi label="Avg. configured order" value={money(analytics.aov_cents, shop.currency)} sub="Last 30 days" />
                            </InlineGrid>
                            <InlineGrid columns={{ xs: 1, md: ['twoThirds', 'oneThird'] }} gap="400">
                                <Card>
                                    <BlockStack gap="300">
                                        <Text as="h3" variant="headingSm">Step drop-off</Text>
                                        <FunnelBars rows={analytics.funnel} />
                                    </BlockStack>
                                </Card>
                                <Card padding="0">
                                    <Box padding="400"><Text as="h3" variant="headingSm">Configurators</Text></Box>
                                    <ResourceList
                                        items={configurators}
                                        emptyState={<Box padding="400"><Text tone="subdued">No configurators yet.</Text></Box>}
                                        renderItem={(c) => (
                                            <ResourceItem id={String(c.id)} url={`/app/configurators/${c.id}`} accessibilityLabel={`Edit ${c.name}`}>
                                                <InlineStack align="space-between" wrap={false}>
                                                    <BlockStack gap="050">
                                                        <Text fontWeight="semibold">{c.name}</Text>
                                                        <Text variant="bodySm" tone="subdued">{c.steps} steps · {c.assigned || 'Not assigned'}</Text>
                                                    </BlockStack>
                                                    <StatusBadge status={c.status} unpublished={c.unpublished} />
                                                </InlineStack>
                                            </ResourceItem>
                                        )}
                                    />
                                </Card>
                            </InlineGrid>
                        </BlockStack>
                    </Layout.Section>
                )}

                {templates?.length > 0 && (
                    <Layout.Section>
                        <Card>
                            <BlockStack gap="300">
                                <Text as="h2" variant="headingMd">Start from a template</Text>
                                <InlineGrid columns={{ xs: 2, md: 3, lg: 6 }} gap="300">
                                    {templates.map((t) => (
                                        <Button key={t.key} url={`/app/configurators/new?template=${t.key}`} fullWidth>{t.name}</Button>
                                    ))}
                                </InlineGrid>
                            </BlockStack>
                        </Card>
                    </Layout.Section>
                )}
            </Layout>
        </Page>
    );
}

function Kpi({ label, value, sub }) {
    return (
        <Card>
            <BlockStack gap="100">
                <Text tone="subdued" variant="bodySm">{label}</Text>
                <Text as="p" variant="headingLg">{value}</Text>
                <Text tone="subdued" variant="bodyXs">{sub}</Text>
            </BlockStack>
        </Card>
    );
}

export function StatusBadge({ status, unpublished }) {
    if (status === 'published') return unpublished ? <Badge tone="attention">Unpublished changes</Badge> : <Badge tone="success">Published</Badge>;
    if (status === 'archived') return <Badge>Archived</Badge>;
    return <Badge>Draft</Badge>;
}
