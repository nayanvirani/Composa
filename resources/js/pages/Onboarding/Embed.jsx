import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import { Badge, BlockStack, Box, Button, Card, Divider, InlineStack, Page, Text } from '@shopify/polaris';
import SetupSteps from '@/components/SetupSteps';
import { api, openTop, toast } from '@/lib/api';

/** Step 3: turn on the app embed and add the Customize button block — no code edits. */
export default function Embed({ status: initial }) {
    const { shop } = usePage().props;
    const [status, setStatus] = useState(initial);
    const [checking, setChecking] = useState(false);

    const check = async () => {
        setChecking(true);
        try {
            setStatus(await api('theme/check', { method: 'POST' }));
        } catch (e) {
            toast(e.message, true);
        } finally {
            setChecking(false);
        }
    };

    const rows = [
        { key: 'embed', ok: status.embed_enabled, title: 'App embed', body: 'Loads the Customize button on collection cards and quick view, and the cart display script for configured images and summaries.', link: shop.embedLink, cta: 'Enable in theme editor' },
        { key: 'block', ok: status.product_block, title: 'Customize button on product pages', body: 'Adds the app block to your product template. You can move and restyle it in the theme editor.', link: shop.blockLink, cta: 'Add block in theme editor' },
    ];

    return (
        <Page title="Turn Composa on in your theme" subtitle="Works on any Online Store 2.0 theme, without code edits." backAction={{ url: '/app/onboarding/template' }}>
            <BlockStack gap="500">
                <SetupSteps current={2} />
                <Card>
                    <BlockStack gap="300">
                        <InlineStack align="space-between">
                            <BlockStack gap="050">
                                <Text tone="subdued" variant="bodySm">Live theme</Text>
                                <Text fontWeight="semibold">{status.theme || 'Unknown'}</Text>
                            </BlockStack>
                            <Button onClick={check} loading={checking}>Check again</Button>
                        </InlineStack>
                        {rows.map((r) => (
                            <BlockStack key={r.key} gap="200">
                                <Divider />
                                <InlineStack align="space-between" blockAlign="start" wrap={false} gap="400">
                                    <BlockStack gap="100">
                                        <Text fontWeight="semibold">{r.title}</Text>
                                        <Text tone="subdued" variant="bodySm">{r.body}</Text>
                                        {!r.ok && <Box><Button onClick={() => openTop(r.link)}>{r.cta}</Button></Box>}
                                    </BlockStack>
                                    {r.ok ? <Badge tone="success">Enabled</Badge> : <Badge tone="attention">Not yet</Badge>}
                                </InlineStack>
                            </BlockStack>
                        ))}
                        <Divider />
                        <InlineStack align="end">
                            <Button variant="primary" url="/app/configurators">Continue: assign & publish</Button>
                        </InlineStack>
                    </BlockStack>
                </Card>
            </BlockStack>
        </Page>
    );
}
