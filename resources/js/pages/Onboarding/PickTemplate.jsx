import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Badge, Banner, BlockStack, Box, Button, Card, InlineGrid, InlineStack, List, Page, Text } from '@shopify/polaris';
import SetupSteps from '@/components/SetupSteps';
import { api, toast } from '@/lib/api';

/** Step 2: pick a template; it shows whether it fits the plan before installing. */
export default function PickTemplate({ templates }) {
    const [selected, setSelected] = useState(templates[0]?.key);
    const [busy, setBusy] = useState(false);
    const current = templates.find((t) => t.key === selected);

    const install = async () => {
        setBusy(true);
        try {
            await api('configurators', { method: 'POST', body: { name: current.name, template: current.key } });
            await api('onboarding', { method: 'POST', body: { key: 'template', value: current.key } });
            toast(`${current.name} added as a draft`);
            router.visit('/app/onboarding/embed');
        } catch (e) {
            toast(e.message, true);
        } finally {
            setBusy(false);
        }
    };

    return (
        <Page title="What do you want shoppers to customize?" subtitle="Templates are a head start — every step, option and price can be changed later." backAction={{ url: '/app/onboarding/welcome' }}>
            <BlockStack gap="500">
                <SetupSteps current={1} />
                <InlineGrid columns={{ xs: 1, md: ['twoThirds', 'oneThird'] }} gap="400">
                    <InlineGrid columns={{ xs: 1, sm: 2, lg: 3 }} gap="300">
                        {templates.map((t) => (
                            <div key={t.key} onClick={() => setSelected(t.key)} role="button" tabIndex={0} onKeyDown={(e) => e.key === 'Enter' && setSelected(t.key)} style={{ cursor: 'pointer' }}>
                                <Card background={selected === t.key ? 'bg-surface-selected' : undefined}>
                                    <BlockStack gap="200">
                                        <InlineStack align="space-between">
                                            <Text fontWeight="semibold">{t.name}</Text>
                                            {!t.fits && t.needs_plan && <Badge tone="attention">Needs {t.needs_plan}</Badge>}
                                        </InlineStack>
                                        <Text variant="bodySm" tone="subdued">{t.category} · {t.steps.length} steps</Text>
                                    </BlockStack>
                                </Card>
                            </div>
                        ))}
                    </InlineGrid>
                    {current && (
                        <Card>
                            <BlockStack gap="300">
                                <Text as="h2" variant="headingMd">{current.name}</Text>
                                <Text tone="subdued">{current.description}</Text>
                                <Text fontWeight="semibold">Steps included</Text>
                                <List type="number">{current.steps.map((s) => <List.Item key={s}>{s}</List.Item>)}</List>
                                {current.fits ? (
                                    <Banner tone="success">Fits your plan — {current.stats.steps} steps, {current.stats.rules} rules.</Banner>
                                ) : (
                                    <Banner tone="warning">
                                        {current.violations.map((v) => v.message).join(' ')} You can install it now and upgrade before publishing.
                                    </Banner>
                                )}
                                <InlineStack gap="200">
                                    <Button url="/app">Skip for now</Button>
                                    <Button variant="primary" onClick={install} loading={busy}>Use template</Button>
                                </InlineStack>
                            </BlockStack>
                        </Card>
                    )}
                </InlineGrid>
                <Box paddingBlockEnd="800" />
            </BlockStack>
        </Page>
    );
}
