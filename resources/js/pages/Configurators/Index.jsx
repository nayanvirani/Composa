import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { ActionList, Banner, BlockStack, Button, Card, EmptyState, IndexTable, InlineStack, Page, Popover, ProgressBar, Text, Thumbnail } from '@shopify/polaris';
import { StatusBadge } from '@/pages/Home';
import { api, toast } from '@/lib/api';
import { relative } from '@/lib/format';

export default function Index({ configurators, limits }) {
    const { usage } = usePage().props;
    const [menu, setMenu] = useState(null);
    const published = usage?.configurators;

    const act = async (path, method, message, after) => {
        try {
            const r = await api(path, { method });
            toast(message);
            after ? after(r) : router.reload({ only: ['configurators', 'usage'] });
        } catch (e) {
            toast(e.message, true);
        }
    };

    const rows = configurators.map((c, i) => (
        <IndexTable.Row id={String(c.id)} key={c.id} position={i}>
            <IndexTable.Cell>
                <BlockStack gap="050">
                    <Button variant="plain" url={`/app/configurators/${c.id}`}>{c.name}</Button>
                    <Text variant="bodySm" tone="subdued">{c.version ? `v${c.version} live` : 'Never published'}{c.draft_version ? ` · v${c.draft_version} draft` : ''} · {c.display_mode === 'page' ? 'Page' : 'Popup'}</Text>
                </BlockStack>
            </IndexTable.Cell>
            <IndexTable.Cell>
                <InlineStack gap="200" blockAlign="center" wrap={false}>
                    <Thumbnail size="small" source={c.base.image || 'data:image/gif;base64,R0lGODlhAQABAAAAACw='} alt="" />
                    <Text>{c.base.title || '—'}</Text>
                </InlineStack>
            </IndexTable.Cell>
            <IndexTable.Cell>{c.steps}{limits.steps ? ` / ${limits.steps}` : ''}</IndexTable.Cell>
            <IndexTable.Cell><Text tone="subdued">{c.assigned || 'Not assigned'}</Text></IndexTable.Cell>
            <IndexTable.Cell><StatusBadge status={c.status} unpublished={c.unpublished} /></IndexTable.Cell>
            <IndexTable.Cell><Text tone="subdued">{relative(c.updated_at)}</Text></IndexTable.Cell>
            <IndexTable.Cell>
                <Popover active={menu === c.id} onClose={() => setMenu(null)} activator={<Button onClick={() => setMenu(menu === c.id ? null : c.id)} accessibilityLabel={`More actions for ${c.name}`}>⋯</Button>}>
                    <ActionList
                        items={[
                            { content: 'Edit', url: `/app/configurators/${c.id}` },
                            { content: 'Duplicate', onAction: () => act(`configurators/${c.id}/duplicate`, 'POST', 'Duplicated', (r) => router.visit(r.redirect)) },
                            c.status === 'published' ? { content: 'Unpublish', onAction: () => act(`configurators/${c.id}/unpublish`, 'POST', 'Unpublished') } : null,
                            { content: 'Delete', destructive: true, onAction: () => confirm(`Delete “${c.name}”? This can’t be undone.`) && act(`configurators/${c.id}`, 'DELETE', 'Deleted') },
                        ].filter(Boolean)}
                    />
                </Popover>
            </IndexTable.Cell>
        </IndexTable.Row>
    ));

    const reached = published && published.limit !== null && published.used >= published.limit;

    return (
        <Page title="Configurators" primaryAction={{ content: 'Create configurator', url: '/app/configurators/new' }} secondaryActions={[{ content: 'From template', url: '/app/onboarding/template' }]}>
            <BlockStack gap="400">
                {published && (
                    <Card>
                        <BlockStack gap="200">
                            <InlineStack align="space-between">
                                <Text><b>{published.used} of {published.limit ?? '∞'}</b> published configurators</Text>
                                <Text tone="subdued" variant="bodySm">Drafts don’t count · up to {limits.drafts ?? '∞'} drafts</Text>
                            </InlineStack>
                            <ProgressBar progress={published.limit ? (published.used / published.limit) * 100 : 0} size="small" tone={reached ? 'critical' : 'primary'} />
                        </BlockStack>
                    </Card>
                )}
                {reached && (
                    <Banner tone="warning" title={`You’ve reached ${published.limit} published configurators`} action={{ content: 'Plan & usage', url: '/app/billing' }}>
                        Unpublish one or upgrade to publish another. Your drafts are saved and live configurators keep selling.
                    </Banner>
                )}
                <Card padding="0">
                    {configurators.length ? (
                        <IndexTable
                            resourceName={{ singular: 'configurator', plural: 'configurators' }}
                            itemCount={configurators.length}
                            selectable={false}
                            headings={[{ title: 'Configurator' }, { title: 'Base product' }, { title: 'Steps' }, { title: 'Assigned to' }, { title: 'Status' }, { title: 'Updated' }, { title: '' }]}
                        >
                            {rows}
                        </IndexTable>
                    ) : (
                        <EmptyState heading="Build your first configurator" action={{ content: 'Create configurator', url: '/app/configurators/new' }} secondaryAction={{ content: 'Browse templates', url: '/app/onboarding/template' }} image="">
                            <p>Turn any product into a step-by-step builder with rules and live pricing.</p>
                        </EmptyState>
                    )}
                </Card>
            </BlockStack>
        </Page>
    );
}
