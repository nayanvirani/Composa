import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import { Banner, BlockStack, Button, Card, DataTable, InlineGrid, InlineStack, Page, Select, Text } from '@shopify/polaris';
import UsageMeters from '@/components/UsageMeters';
import { api, openTop, toast } from '@/lib/api';
import { date } from '@/lib/format';

const fmt = (k, v) => (v === null || v === undefined ? (k === 'languages' ? 'All' : 'No limit') : k === 'storage_mb' ? (v ? (v >= 1024 ? v / 1024 + ' GB' : v + ' MB') : '—') : Number(v).toLocaleString());

/** Plan & usage. Plans are changed on Shopify's hosted plan page; charges are on the Shopify invoice. */
export default function Billing({ plan, trialEndsAt, meters, labels, plans, impact, pricingUrl, graceUntil }) {
    const { shop } = usePage().props;
    const others = plans.filter((p) => p.handle !== plan?.handle);
    const [compare, setCompare] = useState(others[0]?.handle);
    const [busy, setBusy] = useState(false);

    const sync = async () => {
        setBusy(true);
        try {
            const r = await api('onboarding/plan', { method: 'POST' });
            toast(r.plan ? r.plan.name + ' is active' : 'No active plan');
            window.location.reload();
        } finally {
            setBusy(false);
        }
    };

    return (
        <Page title="Plan & usage">
            <BlockStack gap="400">
                <Card>
                    <InlineStack align="space-between" blockAlign="center" gap="300">
                        <BlockStack gap="100">
                            <Text tone="subdued" variant="bodySm">Current plan</Text>
                            <Text as="h2" variant="headingXl">{plan ? `${plan.name} · ${plan.price}/mo` : 'No plan'}</Text>
                            <Text tone="subdued" variant="bodySm">{trialEndsAt ? 'Free trial ends ' + date(trialEndsAt) + ' · ' : ''}Billed on your Shopify invoice (Settings › Billing in Shopify admin)</Text>
                        </BlockStack>
                        <InlineStack gap="200">
                            <Button onClick={sync} loading={busy}>Refresh</Button>
                            <Button variant="primary" onClick={() => openTop(pricingUrl)}>Change plan on Shopify</Button>
                        </InlineStack>
                    </InlineStack>
                </Card>
                {graceUntil && <Banner tone="warning">Over the new plan’s limits — extra published configurators move to draft on {date(graceUntil)}.</Banner>}
                <InlineGrid columns={{ xs: 1, md: ['twoThirds', 'oneThird'] }} gap="400">
                    <Card>
                        <BlockStack gap="400">
                            <Text as="h3" variant="headingSm">Usage against limits</Text>
                            <UsageMeters meters={meters} columns={{ xs: 1, sm: 2 }} />
                            <Text variant="bodySm" tone="subdued">At 80% we email you. At 100% new publishes, assignments or uploads of that kind pause — live configurators keep selling and checkout is never blocked.</Text>
                        </BlockStack>
                    </Card>
                    <BlockStack gap="400">
                        <Card padding="0">
                            <DataTable
                                columnContentTypes={['text', ...plans.map(() => 'text')]}
                                headings={['Limit', ...plans.map((p) => p.name)]}
                                rows={Object.entries(labels).map(([k, label]) => [label, ...plans.map((p) => fmt(k, p.limits[k]))])}
                            />
                        </Card>
                        {others.length > 0 && (
                            <Card>
                                <BlockStack gap="200">
                                    <Select label="If you move to" value={compare} onChange={setCompare} options={others.map((p) => ({ label: `${p.name} (${p.price}/mo)`, value: p.handle }))} />
                                    {(impact[compare] || []).length === 0 ? <Text tone="success">✓ Everything you use fits.</Text> : impact[compare].map((x, i) => <Text key={i} tone="critical">! {x.text}</Text>)}
                                    <Text variant="bodySm" tone="subdued">Over-limit items get a 7-day grace period, then extra configurators move to draft. Nothing is deleted.</Text>
                                </BlockStack>
                            </Card>
                        )}
                    </BlockStack>
                </InlineGrid>
            </BlockStack>
        </Page>
    );
}
