import { useEffect, useState } from 'react';
import { Badge, Banner, BlockStack, Box, Button, Card, Icon, InlineGrid, InlineStack, Spinner, Text, TextField } from '@shopify/polaris';
import { AlertTriangleIcon, CheckCircleIcon, XCircleIcon } from '@shopify/polaris-icons';
import { api, toast } from '@/lib/api';
import { date } from '@/lib/format';

const LABELS = {
    structure: 'Steps, fields and options are complete',
    plan: 'Within your plan limits',
    product: 'Base product chosen',
    selling_plans: 'Subscriptions hidden on configured items',
    assignment: 'Assigned to products',
};

export default function PublishTab({ configurator, versions, save, onPublished, onRestored }) {
    const [data, setData] = useState(null);
    const [notes, setNotes] = useState('');
    const [busy, setBusy] = useState(false);

    const load = async () => {
        await save();
        setData(await api(`configurators/${configurator.id}/checks`));
    };
    useEffect(() => { load(); }, []);

    const publish = async () => {
        setBusy(true);
        try {
            const r = await api(`configurators/${configurator.id}/publish`, { method: 'POST', body: { notes } });
            toast(`Published v${r.version}`);
            onPublished(r);
        } catch (e) {
            toast(e.message, true);
            if (e.data) setData((d) => ({ ...d, problems: e.data.problems, violations: e.data.violations }));
        } finally {
            setBusy(false);
        }
    };

    const restore = async (v) => {
        if (!confirm(`Copy v${v} into the draft? The live version doesn’t change until you publish.`)) return;
        const r = await api(`configurators/${configurator.id}/versions/${v}/restore`, { method: 'POST' });
        onRestored(r.definition);
        toast(`v${v} copied into the draft`);
    };

    if (!data) return <Box padding="800"><InlineStack align="center"><Spinner /></InlineStack></Box>;

    return (
        <InlineGrid columns={{ xs: 1, md: ['twoThirds', 'oneThird'] }} gap="400">
            <BlockStack gap="400">
                <Card>
                    <BlockStack gap="300">
                        <InlineStack align="space-between"><Text as="h3" variant="headingSm">Pre-publish checks</Text><Button variant="plain" onClick={load}>Re-check</Button></InlineStack>
                        {data.checks.map((c) => (
                            <InlineStack key={c.key} gap="300" blockAlign="start" wrap={false}>
                                <Box><Icon source={c.ok ? CheckCircleIcon : c.warning ? AlertTriangleIcon : XCircleIcon} tone={c.ok ? 'success' : c.warning ? 'caution' : 'critical'} /></Box>
                                <BlockStack gap="050">
                                    <Text fontWeight="medium">{LABELS[c.key] || c.key.replace('translation_', 'Translation ')}</Text>
                                    <Text variant="bodySm" tone="subdued">{c.detail}</Text>
                                </BlockStack>
                            </InlineStack>
                        ))}
                        {data.problems?.length > 0 && <Banner tone="critical" title="Fix before publishing">{data.problems.slice(0, 8).map((p) => p.message).join(' ')}</Banner>}
                        {data.violations?.length > 0 && <Banner tone="warning" title="Not included in your plan" action={{ content: 'Plan & usage', url: '/app/billing' }}>{data.violations.map((v) => v.message).join(' ')}</Banner>}
                    </BlockStack>
                </Card>
                {data.diff?.length > 0 && (
                    <Card>
                        <BlockStack gap="200">
                            <Text as="h3" variant="headingSm">Changes since v{configurator.published_version} (live)</Text>
                            {data.diff.map((d, i) => (
                                <InlineStack key={i} gap="200"><Badge tone={d.kind === 'added' ? 'success' : d.kind === 'removed' ? 'critical' : 'attention'}>{d.kind}</Badge><Text>{d.text}</Text></InlineStack>
                            ))}
                        </BlockStack>
                    </Card>
                )}
            </BlockStack>
            <BlockStack gap="400">
                <Card>
                    <BlockStack gap="300">
                        <Text as="h3" variant="headingSm">Publish v{configurator.draft_version}</Text>
                        <Text tone="subdued" variant="bodySm">{configurator.published_version ? `v${configurator.published_version} stays live until v${configurator.draft_version} is published.` : 'Shoppers see it on assigned products as soon as it’s published.'} Published versions are locked; carts keep their signed price.</Text>
                        <TextField label="Version notes" value={notes} onChange={setNotes} autoComplete="off" placeholder="What changed?" />
                        <Button variant="primary" onClick={publish} loading={busy} disabled={!data.can_publish}>Publish now</Button>
                    </BlockStack>
                </Card>
                <Card>
                    <BlockStack gap="200">
                        <Text as="h3" variant="headingSm">Version history</Text>
                        {(versions || []).map((v) => (
                            <InlineStack key={v.id} align="space-between" blockAlign="center">
                                <BlockStack gap="050">
                                    <Text fontWeight="semibold">v{v.version} {v.notes ? '· ' + v.notes : ''}</Text>
                                    <Text variant="bodySm" tone="subdued">{v.published_at ? 'Published ' + date(v.published_at) : 'Edited ' + date(v.updated_at)}</Text>
                                </BlockStack>
                                <InlineStack gap="100">
                                    <Badge tone={v.status === 'published' ? 'success' : v.status === 'draft' ? 'attention' : undefined}>{v.status === 'published' ? 'Live' : v.status}</Badge>
                                    {v.status === 'archived' && <Button size="slim" onClick={() => restore(v.version)}>Restore</Button>}
                                </InlineStack>
                            </InlineStack>
                        ))}
                    </BlockStack>
                </Card>
            </BlockStack>
        </InlineGrid>
    );
}
