import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { Badge, Banner, BlockStack, Box, Button, Card, IndexTable, InlineStack, Page, Select, Tabs, Text, TextField } from '@shopify/polaris';
import { api, toast } from '@/lib/api';

/** Per-language labels for a configurator; missing strings fall back to the default language. */
export default function Translations({ configurators, current, primary, locales, limit, keys, translations, coverage: initialCoverage }) {
    const [locale, setLocale] = useState(locales[0] || '');
    const [strings, setStrings] = useState(() => Object.fromEntries(locales.map((l) => [l, { ...(translations?.[l] || {}) }])));
    const [coverage, setCoverage] = useState(initialCoverage);
    const [filter, setFilter] = useState(0);
    const [busy, setBusy] = useState(false);
    const overLimit = limit !== null && locales.length + 1 > limit;

    const rows = useMemo(() => {
        const all = Object.entries(keys);
        if (filter === 1) return all.filter(([k]) => !strings[locale]?.[k]);
        if (filter === 2) return all.filter(([k]) => k.startsWith('ui.'));
        return all;
    }, [keys, filter, locale, strings]);

    if (!configurators.length) return <Page title="Translations"><Card><Text tone="subdued">Create a configurator first.</Text></Card></Page>;
    if (!locales.length) {
        return (
            <Page title="Translations">
                <Banner tone="info" title="Your store has one published language">Add languages in Shopify admin › Settings › Languages; they appear here automatically.</Banner>
            </Page>
        );
    }

    const save = async () => {
        setBusy(true);
        try {
            const r = await api(`configurators/${current}/translations`, { method: 'PUT', body: { locale, strings: strings[locale] } });
            setCoverage({ ...coverage, [locale]: r.coverage });
            toast('Translations saved');
        } catch (e) {
            toast(e.message, true);
        } finally {
            setBusy(false);
        }
    };

    return (
        <Page title="Translations" primaryAction={{ content: 'Save', onAction: save, loading: busy }}>
            <BlockStack gap="400">
                {overLimit && <Banner tone="warning" title={`Your plan includes ${limit} language${limit === 1 ? '' : 's'}`} action={{ content: 'Plan & usage', url: '/app/billing' }}>Extra languages are saved but only shown to shoppers after you upgrade.</Banner>}
                <Card>
                    <InlineStack gap="300" blockAlign="center">
                        <Select label="Configurator" labelInline value={String(current)} onChange={(v) => router.get('/app/translations', { configurator: v })} options={configurators.map((c) => ({ label: c.name, value: String(c.id) }))} />
                        <Text tone="subdued">Default: {primary.toUpperCase()}</Text>
                        {locales.map((l) => <Badge key={l} tone={coverage[l]?.percent === 100 ? 'success' : 'attention'}>{l.toUpperCase()} {coverage[l]?.percent ?? 0}%</Badge>)}
                    </InlineStack>
                </Card>
                <Card padding="0">
                    <Box padding="300">
                        <InlineStack gap="300" blockAlign="center">
                            <Select label="Language" labelInline value={locale} onChange={setLocale} options={locales.map((l) => ({ label: l.toUpperCase(), value: l }))} />
                            <Tabs tabs={[{ id: 'all', content: 'All strings' }, { id: 'missing', content: 'Missing' }, { id: 'ui', content: 'Storefront UI' }]} selected={filter} onSelect={setFilter} />
                        </InlineStack>
                    </Box>
                    <IndexTable itemCount={rows.length} selectable={false} headings={[{ title: 'Where' }, { title: primary.toUpperCase() + ' (default)' }, { title: locale.toUpperCase() }]}>
                        {rows.map(([key, text], i) => (
                            <IndexTable.Row id={key} key={key} position={i}>
                                <IndexTable.Cell><Text variant="bodySm" tone="subdued">{key}</Text></IndexTable.Cell>
                                <IndexTable.Cell><Text>{text}</Text></IndexTable.Cell>
                                <IndexTable.Cell>
                                    <div style={{ minWidth: 260 }}>
                                        <TextField labelHidden label={`${locale}: ${text}`} value={strings[locale]?.[key] || ''} onChange={(v) => setStrings({ ...strings, [locale]: { ...strings[locale], [key]: v } })} autoComplete="off" error={!strings[locale]?.[key] ? undefined : undefined} />
                                    </div>
                                </IndexTable.Cell>
                            </IndexTable.Row>
                        ))}
                    </IndexTable>
                </Card>
                <Text variant="bodySm" tone="subdued">Missing translations fall back to {primary.toUpperCase()} on the storefront. Saving updates the draft; publish the configurator to make translations live.</Text>
                <InlineStack><Button url={`/app/configurators/${current}?tab=publish`}>Go to publish</Button></InlineStack>
            </BlockStack>
        </Page>
    );
}
