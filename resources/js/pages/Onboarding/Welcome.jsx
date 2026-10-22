import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Badge, BlockStack, Box, Button, Card, Divider, InlineGrid, InlineStack, Page, Text } from '@shopify/polaris';
import SetupSteps from '@/components/SetupSteps';
import { api, openTop, toast } from '@/lib/api';

const LIMITS = [
    ['configurators', 'Published configurators'],
    ['steps', 'Steps per configurator'],
    ['options', 'Options per field'],
    ['rules', 'Rules per configurator'],
    ['products', 'Assigned products'],
    ['storage_mb', 'File storage'],
    ['languages', 'Languages'],
];

const fmt = (key, v) => (v === null || v === undefined ? (key === 'languages' ? 'All' : 'No limit') : key === 'storage_mb' ? (v ? (v >= 1024 ? v / 1024 + ' GB' : v + ' MB') : '—') : Number(v).toLocaleString());

/**
 * Step 1: compare plans and choose one on Shopify's hosted plan page (Shopify App Pricing). When the
 * merchant comes back, the subscription is read again.
 */
export default function Welcome({ plans, current }) {
    const { shop } = usePage().props;
    const [checking, setChecking] = useState(false);

    const recheck = async () => {
        setChecking(true);
        try {
            const r = await api('onboarding/plan', { method: 'POST' });
            if (r.plan) {
                toast(`${r.plan.name} plan is active`);
                router.visit('/app/onboarding/template');
            } else {
                toast('No active plan yet — choose one on Shopify');
            }
        } finally {
            setChecking(false);
        }
    };

    useEffect(() => {
        const onFocus = () => !current && recheck();
        window.addEventListener('focus', onFocus);
        return () => window.removeEventListener('focus', onFocus);
    }, [current]);

    return (
        <Page title={`Welcome to Composa${shop?.name ? ', ' + shop.name : ''}`} subtitle="Choose a plan to start your free trial. You can change plans any time.">
            <BlockStack gap="500">
                <SetupSteps current={0} />
                <InlineGrid columns={{ xs: 1, sm: 2, lg: plans.length }} gap="400">
                    {plans.map((p) => (
                        <Card key={p.handle} background={current === p.handle ? 'bg-surface-success' : undefined}>
                            <BlockStack gap="300">
                                <InlineStack align="space-between">
                                    <Text as="h3" variant="headingMd">{p.name}</Text>
                                    {current === p.handle ? <Badge tone="success">Current</Badge> : p.featured ? <Badge tone="info">Most popular</Badge> : null}
                                </InlineStack>
                                <Text as="p"><Text as="span" variant="heading2xl">{p.price}</Text> <Text as="span" tone="subdued">/ month</Text></Text>
                                <Text tone="subdued" variant="bodySm">{p.tagline}</Text>
                                <Divider />
                                {LIMITS.map(([k, label]) => (
                                    <InlineStack key={k} align="space-between">
                                        <Text variant="bodySm" tone="subdued">{label}</Text>
                                        <Text variant="bodySm" fontWeight="semibold">{fmt(k, p.limits[k])}</Text>
                                    </InlineStack>
                                ))}
                                <Text variant="bodySm" tone="subdued">Pricing: {(p.features.pricing_types || []).join(', ').replaceAll('_', ' ')}</Text>
                            </BlockStack>
                        </Card>
                    ))}
                </InlineGrid>
                <Card>
                    <InlineStack align="space-between" blockAlign="center" gap="300">
                        <BlockStack gap="100">
                            <Text fontWeight="semibold">Plans are chosen and billed through Shopify</Text>
                            <Text tone="subdued" variant="bodySm">You’ll confirm the plan on Shopify’s approval page; charges appear on your Shopify invoice.</Text>
                        </BlockStack>
                        <InlineStack gap="200">
                            <Button onClick={recheck} loading={checking}>I’ve chosen a plan</Button>
                            {current ? (
                                <Button variant="primary" url="/app/onboarding/template">Continue</Button>
                            ) : (
                                <Button variant="primary" onClick={() => openTop(shop.pricingUrl)}>Choose plan on Shopify</Button>
                            )}
                        </InlineStack>
                    </InlineStack>
                </Card>
                <Box paddingBlockEnd="800" />
            </BlockStack>
        </Page>
    );
}
