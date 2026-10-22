import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Badge, Banner, BlockStack, Box, Button, Card, Checkbox, ChoiceList, FormLayout, InlineGrid, InlineStack, Page, RadioButton, Select, Text, TextField, Thumbnail } from '@shopify/polaris';
import { api, toast } from '@/lib/api';
import { money } from '@/lib/format';

/**
 * New configurator: name and display, base product (the real product that goes into the cart —
 * chosen with Shopify's own product picker), base variant, and a starting point.
 */
export default function Create({ templates, existing, preselect }) {
    const [name, setName] = useState('');
    const [displayMode, setDisplayMode] = useState('popup');
    const [product, setProduct] = useState(null);
    const [variantId, setVariantId] = useState(null);
    const [assignBase, setAssignBase] = useState(true);
    const [start, setStart] = useState(preselect ? 'template' : 'template');
    const [template, setTemplate] = useState(preselect || templates.find((t) => t.featured)?.key || templates[0]?.key);
    const [copyFrom, setCopyFrom] = useState(existing[0]?.id ? String(existing[0].id) : '');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);

    const pickProduct = async () => {
        if (!window.shopify?.resourcePicker) return toast('Open Composa inside Shopify admin to pick products', true);
        const selection = await window.shopify.resourcePicker({ type: 'product', action: 'select', multiple: false, filter: { variants: false } });
        if (!selection?.length) return;
        const detail = await api('products/show', { query: { id: selection[0].id } });
        setProduct(detail);
        setVariantId(detail.variants[0]?.id || null);
        if (!name) setName(detail.title + ' builder');
    };

    const create = async () => {
        setBusy(true);
        setError(null);
        try {
            const r = await api('configurators', {
                method: 'POST',
                body: {
                    name: name || 'New configurator',
                    display_mode: displayMode,
                    variant_mode: product && product.variants.length > 1 ? 'variants' : 'base',
                    template: start === 'template' ? template : null,
                    copy_from: start === 'copy' ? Number(copyFrom) : null,
                    base_product_id: product?.id,
                    base_variant_id: variantId,
                    base_product_title: product?.title,
                    base_product_image: product?.image,
                    assign_base: assignBase && !!product,
                },
            });
            router.visit(r.redirect + (product && product.options.length && product.variants.length > 1 ? '?tab=variants' : ''));
        } catch (e) {
            setError(e.message);
        } finally {
            setBusy(false);
        }
    };

    const tpl = templates.find((t) => t.key === template);

    return (
        <Page title="New configurator" backAction={{ url: '/app/configurators' }} primaryAction={{ content: 'Create & open builder', onAction: create, loading: busy }}>
            <BlockStack gap="400">
                {error && <Banner tone="critical">{error}</Banner>}
                <Card>
                    <FormLayout>
                        <FormLayout.Group>
                            <TextField label="Name (internal)" value={name} onChange={setName} autoComplete="off" />
                            <Select label="Opens as" value={displayMode} onChange={setDisplayMode} options={[{ label: 'Popup / drawer (full-screen sheet on mobile)', value: 'popup' }, { label: 'Dedicated page (/apps/composa/configure/…)', value: 'page' }]} />
                        </FormLayout.Group>
                    </FormLayout>
                </Card>

                <InlineGrid columns={{ xs: 1, md: ['twoThirds', 'oneThird'] }} gap="400">
                    <Card>
                        <BlockStack gap="300">
                            <Text as="h2" variant="headingMd">Base product</Text>
                            <Text tone="subdued">This real product goes into the cart. Its price is the base; options add to it. Composa never creates hidden or fee products.</Text>
                            {!product ? (
                                <Box><Button onClick={pickProduct}>Choose product</Button></Box>
                            ) : (
                                <BlockStack gap="300">
                                    <InlineStack gap="300" blockAlign="center">
                                        <Thumbnail source={product.image || 'data:image/gif;base64,R0lGODlhAQABAAAAACw='} alt="" />
                                        <BlockStack gap="050">
                                            <Text fontWeight="semibold">{product.title}</Text>
                                            <Text tone="subdued" variant="bodySm">{product.variants.length} variants · {product.options.map((o) => o.name).filter((n) => n !== 'Title').join(', ') || 'no options'}</Text>
                                        </BlockStack>
                                        <Button variant="plain" onClick={pickProduct}>Change</Button>
                                    </InlineStack>
                                    {product.variants.length > 1 ? (
                                        <Banner tone="info" title="This product has Shopify variants">
                                            Its options ({product.options.map((o) => o.name).join(', ')}) will become steps, and the shopper’s choices pick the exact variant. You can review this on the next screen.
                                        </Banner>
                                    ) : null}
                                    <Select
                                        label="Base variant (used when variants aren’t steps)"
                                        value={variantId || ''}
                                        onChange={setVariantId}
                                        options={product.variants.map((v) => ({ label: `${v.title} · ${money(v.price_cents)}${v.available ? '' : ' · sold out'}`, value: v.id }))}
                                    />
                                    <Checkbox label="Also assign this configurator to the base product" checked={assignBase} onChange={setAssignBase} />
                                </BlockStack>
                            )}
                        </BlockStack>
                    </Card>

                    <Card>
                        <BlockStack gap="300">
                            <Text as="h2" variant="headingMd">Start from</Text>
                            <RadioButton label="A template" checked={start === 'template'} onChange={() => setStart('template')} />
                            {start === 'template' && (
                                <BlockStack gap="200">
                                    <Select label="Template" labelHidden value={template} onChange={setTemplate} options={templates.map((t) => ({ label: t.name + ' — ' + t.category, value: t.key }))} />
                                    {tpl && <Text variant="bodySm" tone="subdued">{tpl.description}</Text>}
                                    {tpl && !tpl.fits && <Badge tone="attention">{tpl.needs_plan ? 'Needs ' + tpl.needs_plan : 'Above your plan'}</Badge>}
                                </BlockStack>
                            )}
                            <RadioButton label="Copy an existing configurator" disabled={!existing.length} checked={start === 'copy'} onChange={() => setStart('copy')} />
                            {start === 'copy' && <Select label="Configurator" labelHidden value={copyFrom} onChange={setCopyFrom} options={existing.map((c) => ({ label: c.name, value: String(c.id) }))} />}
                            <RadioButton label="Blank" checked={start === 'blank'} onChange={() => setStart('blank')} />
                        </BlockStack>
                    </Card>
                </InlineGrid>
            </BlockStack>
        </Page>
    );
}
