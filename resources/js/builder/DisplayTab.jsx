import { BlockStack, Button, Card, Checkbox, FormLayout, InlineGrid, InlineStack, RadioButton, Select, Text, TextField, Thumbnail } from '@shopify/polaris';
import { api, toast } from '@/lib/api';
import { money } from '@/lib/format';

const TOGGLES = [
    ['progress', 'Progress bar', 'Shows step x of y'],
    ['chips', 'Step summary chips', 'Lets shoppers jump back to any step'],
    ['sticky_price', 'Sticky price & Add to cart bar', 'Always visible at the bottom'],
    ['breakdown', 'Price breakdown drawer', 'Itemised base + options'],
    ['from_price', '“From” price on the product page', 'Lowest configured price'],
    ['review', 'Review step before Add to cart', 'Summary with edit links and quantity'],
];

export default function DisplayTab({ meta, setMeta, product, setProduct, currency }) {
    const display = meta.display || {};
    const setDisplay = (path, value) => {
        const next = structuredClone(display);
        const keys = path.split('.');
        let o = next;
        keys.slice(0, -1).forEach((k) => (o = o[k] ??= {}));
        o[keys.at(-1)] = value;
        setMeta({ display: next });
    };

    const changeProduct = async () => {
        if (!window.shopify?.resourcePicker) return toast('Open Composa inside Shopify admin to pick products', true);
        const sel = await window.shopify.resourcePicker({ type: 'product', action: 'select', multiple: false, filter: { variants: false } });
        if (!sel?.length) return;
        const detail = await api('products/show', { query: { id: sel[0].id } });
        setProduct(detail);
        setMeta({ base_product_id: detail.id, base_variant_id: detail.variants[0]?.id, base_product_title: detail.title, base_product_image: detail.image });
    };

    return (
        <InlineGrid columns={{ xs: 1, md: 2 }} gap="400">
            <BlockStack gap="400">
                <Card>
                    <BlockStack gap="300">
                        <Text as="h3" variant="headingSm">General</Text>
                        <TextField label="Name (internal)" value={meta.name} onChange={(v) => setMeta({ name: v })} autoComplete="off" />
                        <RadioButton label="Popup / drawer (full-screen sheet on mobile)" checked={meta.display_mode === 'popup'} onChange={() => setMeta({ display_mode: 'popup' })} />
                        {meta.display_mode === 'popup' && (
                            <Select label="Popup style on desktop" value={display.popup_style || 'modal'} onChange={(v) => setDisplay('popup_style', v)} options={[{ label: 'Centred window', value: 'modal' }, { label: 'Side drawer', value: 'drawer' }]} />
                        )}
                        <RadioButton label={`Dedicated page — /apps/composa/configure/${meta.handle} (shareable, SEO-friendly)`} checked={meta.display_mode === 'page'} onChange={() => setMeta({ display_mode: 'page' })} />
                    </BlockStack>
                </Card>
                <Card>
                    <BlockStack gap="300">
                        <Text as="h3" variant="headingSm">Base product</Text>
                        <InlineStack gap="300" blockAlign="center">
                            {meta.base_product_image && <Thumbnail source={meta.base_product_image} alt="" size="small" />}
                            <Text>{meta.base_product_title || 'None chosen'}</Text>
                            <Button onClick={changeProduct}>{meta.base_product_id ? 'Change' : 'Choose product'}</Button>
                        </InlineStack>
                        {product?.variants?.length > 0 && (
                            <Select label="Base variant" value={meta.base_variant_id || ''} onChange={(v) => setMeta({ base_variant_id: v })} options={product.variants.map((v) => ({ label: v.title + ' · ' + money(v.price_cents, currency), value: v.id }))} />
                        )}
                    </BlockStack>
                </Card>
                <Card>
                    <BlockStack gap="300">
                        <Text as="h3" variant="headingSm">Customize button</Text>
                        <FormLayout>
                            <FormLayout.Group>
                                <TextField label="Label" value={display.button?.label || 'Customize'} onChange={(v) => setDisplay('button.label', v)} autoComplete="off" />
                                <Select label="Style" value={display.button?.style || 'theme'} onChange={(v) => setDisplay('button.style', v)} options={[{ label: 'Theme primary button', value: 'theme' }, { label: 'Outline', value: 'outline' }, { label: 'Custom colours', value: 'custom' }]} />
                            </FormLayout.Group>
                            <FormLayout.Group>
                                <Select label="On product page" value={display.button?.product_position || 'replace_add_to_cart'} onChange={(v) => setDisplay('button.product_position', v)} options={[{ label: 'Replace Add to cart', value: 'replace_add_to_cart' }, { label: 'Above Add to cart', value: 'above' }, { label: 'Below Add to cart', value: 'below' }]} />
                                <Select label="On collection cards" value={display.button?.collection_cards === false ? 'hide' : 'show'} onChange={(v) => setDisplay('button.collection_cards', v === 'show')} options={[{ label: 'Show “Customize” link', value: 'show' }, { label: 'Hide', value: 'hide' }]} />
                            </FormLayout.Group>
                            {display.button?.style === 'custom' && (
                                <FormLayout.Group>
                                    <TextField label="Background" value={display.button?.bg || ''} onChange={(v) => setDisplay('button.bg', v)} placeholder="#1C1917" autoComplete="off" />
                                    <TextField label="Text" value={display.button?.color || ''} onChange={(v) => setDisplay('button.color', v)} placeholder="#FFFFFF" autoComplete="off" />
                                </FormLayout.Group>
                            )}
                        </FormLayout>
                    </BlockStack>
                </Card>
            </BlockStack>
            <BlockStack gap="400">
                <Card>
                    <BlockStack gap="300">
                        <Text as="h3" variant="headingSm">Steps & price</Text>
                        {TOGGLES.map(([key, label, help]) => <Checkbox key={key} label={label} helpText={help} checked={display.steps?.[key] !== false} onChange={(v) => setDisplay('steps.' + key, v)} />)}
                    </BlockStack>
                </Card>
                <Card>
                    <BlockStack gap="300">
                        <Text as="h3" variant="headingSm">After Add to cart</Text>
                        <Select label="Action" value={display.after_add || 'drawer'} onChange={(v) => setDisplay('after_add', v)} options={[{ label: 'Open the theme cart drawer', value: 'drawer' }, { label: 'Go to /cart', value: 'cart' }, { label: 'Stay and show confirmation', value: 'stay' }]} />
                        <Checkbox label="Inherit fonts, colours and radius from the theme" checked={display.appearance?.inherit_theme !== false} onChange={(v) => setDisplay('appearance.inherit_theme', v)} />
                        {display.appearance?.inherit_theme === false && (
                            <FormLayout.Group>
                                <TextField label="Accent colour" value={display.appearance?.accent || ''} onChange={(v) => setDisplay('appearance.accent', v)} placeholder="#1C1917" autoComplete="off" />
                                <TextField label="Corner radius (px)" type="number" value={String(display.appearance?.radius ?? 8)} onChange={(v) => setDisplay('appearance.radius', Number(v))} autoComplete="off" />
                            </FormLayout.Group>
                        )}
                    </BlockStack>
                </Card>
            </BlockStack>
        </InlineGrid>
    );
}
