import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Badge, Banner, BlockStack, Box, InlineGrid, InlineStack, Page, Tabs, Text } from '@shopify/polaris';
import StepsTab from '@/builder/StepsTab';
import VariantsTab from '@/builder/VariantsTab';
import RulesTab from '@/builder/RulesTab';
import PricingTab from '@/builder/PricingTab';
import LayersTab from '@/builder/LayersTab';
import DisplayTab from '@/builder/DisplayTab';
import AssignTab from '@/builder/AssignTab';
import PublishTab from '@/builder/PublishTab';
import PreviewPanel from '@/builder/PreviewPanel';
import { api, toast } from '@/lib/api';
import { relative } from '@/lib/format';

const TABS = [
    { id: 'steps', content: 'Steps & options' },
    { id: 'variants', content: 'Shopify variants' },
    { id: 'rules', content: 'Rules' },
    { id: 'pricing', content: 'Pricing' },
    { id: 'layers', content: 'Preview layers' },
    { id: 'display', content: 'Display' },
    { id: 'assign', content: 'Assignment' },
    { id: 'publish', content: 'Publish' },
];

/** The configurator builder: edits autosave to the draft version; Publish freezes it. */
export default function Builder(props) {
    const { configurator: initial, definition: initialDefinition, problems: initialProblems, violations: initialViolations, limits, features, assignments, conflicts, versions } = props;
    const { shop, usage, impersonation } = usePage().props;
    const readOnly = impersonation && impersonation.mode !== 'write';
    const [definition, setDefinition] = useState(initialDefinition);
    const [meta, setMetaState] = useState(initial);
    const [product, setProduct] = useState(props.product);
    const [problems, setProblems] = useState(initialProblems);
    const [violations, setViolations] = useState(initialViolations);
    const [saveState, setSaveState] = useState({ status: 'saved', at: null });
    const [tab, setTab] = useState(Math.max(0, TABS.findIndex((t) => t.id === props.tab)));
    const dirty = useRef(false);
    const timer = useRef(null);
    const latest = useRef({ definition, meta });
    latest.current = { definition, meta };

    const save = useCallback(async () => {
        if (readOnly || !dirty.current) return;
        clearTimeout(timer.current);
        dirty.current = false;
        setSaveState({ status: 'saving' });
        try {
            const { definition: d, meta: m } = latest.current;
            const r = await api(`configurators/${initial.id}/draft`, { method: 'PUT', body: { definition: d, meta: { name: m.name, display_mode: m.display_mode, variant_mode: m.variant_mode, display: m.display, base_product_id: m.base_product_id, base_variant_id: m.base_variant_id, base_product_title: m.base_product_title, base_product_image: m.base_product_image } } });
            setProblems(r.problems);
            setViolations(r.violations);
            setSaveState({ status: 'saved', at: r.saved_at });
        } catch (e) {
            dirty.current = true;
            setSaveState({ status: 'error', message: e.message });
        }
    }, [initial.id, readOnly]);

    const schedule = () => {
        dirty.current = true;
        setSaveState({ status: 'pending' });
        clearTimeout(timer.current);
        timer.current = setTimeout(save, 1200);
    };
    const update = (next) => {
        setDefinition(next);
        schedule();
    };
    const setMeta = (patch) => {
        setMetaState((m) => ({ ...m, ...patch }));
        schedule();
    };

    useEffect(() => {
        const beforeUnload = (e) => {
            if (dirty.current) {
                save();
                e.preventDefault();
            }
        };
        window.addEventListener('beforeunload', beforeUnload);
        const off = router.on('before', () => {
            if (dirty.current) save();
        });
        return () => {
            window.removeEventListener('beforeunload', beforeUnload);
            off();
        };
    }, [save]);

    const variants = useMemo(() => (product?.variants?.length > 1 ? product.variants.map((v) => ({ id: v.id, price_cents: v.price_cents, available: v.available, options: v.options })) : null), [product]);
    const basePriceCents = useMemo(() => product?.variants?.find((v) => v.id === meta.base_variant_id)?.price_cents ?? product?.variants?.[0]?.price_cents ?? 0, [product, meta.base_variant_id]);
    const tabId = TABS[tab].id;
    const status = { pending: 'Unsaved changes', saving: 'Saving…', saved: saveState.at ? 'Saved ' + relative(saveState.at) : 'All changes saved', error: 'Not saved: ' + saveState.message }[saveState.status];

    const sharedProps = { configurator: meta, definition, update, limits, features, product, currency: shop.currency };
    const wide = ['assign', 'publish', 'variants', 'display'].includes(tabId);

    return (
        <Page
            fullWidth
            title={meta.name}
            titleMetadata={<InlineStack gap="200">{meta.status === 'published' ? <Badge tone="success">{`Live v${meta.published_version}`}</Badge> : <Badge>Draft</Badge>}<Badge tone="attention">{`Draft v${meta.draft_version}`}</Badge></InlineStack>}
            subtitle={status}
            backAction={{ content: 'Configurators', onAction: async () => { await save(); router.visit('/app/configurators'); } }}
            primaryAction={{ content: 'Review & publish', disabled: readOnly, onAction: async () => { await save(); setTab(TABS.findIndex((t) => t.id === 'publish')); } }}
            secondaryActions={[
                { content: 'Translations', onAction: async () => { await save(); router.visit(`/app/translations?configurator=${initial.id}`); } },
                meta.status === 'published' ? { content: 'Unpublish', destructive: true, onAction: async () => { await api(`configurators/${initial.id}/unpublish`, { method: 'POST' }); toast('Unpublished'); setMetaState((m) => ({ ...m, status: 'draft' })); } } : null,
            ].filter(Boolean)}
        >
            <BlockStack gap="400">
                {readOnly && <Banner tone="warning">Read-only support session — changes are not saved.</Banner>}
                {(problems?.length > 0 || violations?.length > 0) && tabId !== 'publish' && (
                    <Banner tone={problems?.length ? 'warning' : 'info'} title={problems?.length ? `${problems.length} thing(s) to fix before publishing` : 'Some features aren’t in your plan'}>
                        <Text as="p">{[...(problems || []).slice(0, 3).map((p) => p.message), ...(violations || []).slice(0, 3).map((v) => v.message)].join(' ')}</Text>
                    </Banner>
                )}
                <Tabs tabs={TABS} selected={tab} onSelect={async (i) => { await save(); setTab(i); }} />
                <InlineGrid columns={wide ? 1 : { xs: 1, lg: '1fr 380px' }} gap="400">
                    <Box>
                        {tabId === 'steps' && <StepsTab {...sharedProps} />}
                        {tabId === 'variants' && <VariantsTab {...sharedProps} setMeta={setMeta} shopCurrency={shop.currency} />}
                        {tabId === 'rules' && <RulesTab {...sharedProps} />}
                        {tabId === 'pricing' && <PricingTab {...sharedProps} basePriceCents={basePriceCents} variants={variants} />}
                        {tabId === 'layers' && <LayersTab {...sharedProps} />}
                        {tabId === 'display' && <DisplayTab meta={meta} setMeta={setMeta} product={product} setProduct={setProduct} currency={shop.currency} />}
                        {tabId === 'assign' && <AssignTab configurator={meta} initial={assignments} conflicts={conflicts} usage={usage} />}
                        {tabId === 'publish' && (
                            <PublishTab
                                configurator={meta}
                                versions={versions}
                                save={save}
                                onPublished={(r) => { setMetaState((m) => ({ ...m, status: 'published', published_version: r.version, draft_version: r.version + 1 })); router.reload({ only: ['versions', 'configurator', 'usage'] }); }}
                                onRestored={(d) => { setDefinition(d); setTab(0); }}
                            />
                        )}
                    </Box>
                    {!wide && <PreviewPanel definition={definition} basePriceCents={basePriceCents} variants={variants} currency={shop.currency} />}
                </InlineGrid>
            </BlockStack>
        </Page>
    );
}
