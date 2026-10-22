import { useState } from 'react';
import { Badge, Banner, BlockStack, Card, Checkbox, FormLayout, InlineGrid, InlineStack, Layout, Page, ProgressBar, Select, Text, TextField } from '@shopify/polaris';
import { api, toast } from '@/lib/api';
import { LAYOUTS } from '@/builder/fieldTypes';

export default function Settings({ settings: initial, cart, storage, features }) {
    const [s, setS] = useState(initial);
    const [busy, setBusy] = useState(false);
    const set = (group, key, value) => setS({ ...s, [group]: { ...s[group], [key]: value } });

    const save = async () => {
        setBusy(true);
        try {
            await api('settings', { method: 'PUT', body: s });
            toast('Settings saved');
        } catch (e) {
            toast(e.message, true);
        } finally {
            setBusy(false);
        }
    };

    return (
        <Page title="Settings" primaryAction={{ content: 'Save', onAction: save, loading: busy }}>
            <Layout>
                <Layout.AnnotatedSection title="Appearance" description="Configurators inherit your theme’s fonts, colours and corner radius by default.">
                    <Card>
                        <FormLayout>
                            <Checkbox label="Inherit colours, fonts and radius from the theme" checked={s.appearance.inherit_theme !== false} onChange={(v) => set('appearance', 'inherit_theme', v)} />
                            <FormLayout.Group>
                                <TextField label="Accent colour override" value={s.appearance.accent || ''} onChange={(v) => set('appearance', 'accent', v)} placeholder="#1C1917" disabled={s.appearance.inherit_theme !== false} autoComplete="off" />
                                <Select label="Corner radius" value={String(s.appearance.radius ?? 'theme')} onChange={(v) => set('appearance', 'radius', v)} disabled={s.appearance.inherit_theme !== false} options={[{ label: 'Theme', value: 'theme' }, { label: 'Square', value: '0' }, { label: 'Small', value: '4' }, { label: 'Medium', value: '8' }, { label: 'Large', value: '16' }]} />
                            </FormLayout.Group>
                        </FormLayout>
                    </Card>
                </Layout.AnnotatedSection>

                <Layout.AnnotatedSection title="Cart & checkout" description="Configured items reach the cart as one line priced by Cart Transform. Composa never creates hidden or fee products.">
                    <Card>
                        <BlockStack gap="300">
                            <InlineStack gap="200">
                                <Text>Cart mode:</Text>
                                <Badge tone="info">{cart.mode === 'update' ? 'Update (Plus / dev store)' : cart.mode === 'expand' ? 'Expand' : 'Discount fallback'}</Badge>
                                {cart.transform ? <Badge tone="success">Cart Transform active</Badge> : <Badge tone="critical">Cart Transform not registered</Badge>}
                            </InlineStack>
                            {cart.conflict && <Banner tone="critical">Another app owns this store’s cart transform. Contact support.</Banner>}
                            <FormLayout>
                                <FormLayout.Group>
                                    <Select label="Cart line title" value={s.cart.line_title || 'product_custom'} onChange={(v) => set('cart', 'line_title', v)} options={[{ label: 'Product title — Custom', value: 'product_custom' }, { label: 'Product title only', value: 'product' }]} />
                                    <TextField label="Summary lines in cart" type="number" value={String(s.cart.summary_lines ?? 6)} onChange={(v) => set('cart', 'summary_lines', Number(v))} autoComplete="off" />
                                </FormLayout.Group>
                                <Select label="After Add to cart (default for new configurators)" value={s.cart.after_add || 'drawer'} onChange={(v) => set('cart', 'after_add', v)} options={[{ label: 'Open the cart drawer', value: 'drawer' }, { label: 'Go to /cart', value: 'cart' }, { label: 'Stay and confirm', value: 'stay' }]} />
                                <Checkbox label="Flag and tag orders when the price signature fails" checked={s.cart.flag_signature_failures !== false} onChange={(v) => set('cart', 'flag_signature_failures', v)} />
                            </FormLayout>
                        </BlockStack>
                    </Card>
                </Layout.AnnotatedSection>

                <Layout.AnnotatedSection title="Files & storage" description="Shopper uploads are private. Files from carts that never became orders are deleted after 30 days.">
                    <Card>
                        <BlockStack gap="300">
                            {!features.uploads ? <Banner tone="info">File uploads are included from the Starter plan.</Banner> : (
                                <>
                                    <InlineStack align="space-between"><Text>Shopper uploads</Text><Text fontWeight="semibold">{storage.used_mb} / {storage.limit_mb ?? '∞'} MB</Text></InlineStack>
                                    <ProgressBar size="small" progress={storage.limit_mb ? (storage.used_mb / storage.limit_mb) * 100 : 0} />
                                </>
                            )}
                            <FormLayout.Group>
                                <TextField label="Allowed file types" value={(s.files.types || []).join(', ')} onChange={(v) => set('files', 'types', v.split(',').map((x) => x.trim().toLowerCase()).filter(Boolean))} autoComplete="off" />
                                <TextField label="Max file size (MB)" type="number" value={String(s.files.max_mb ?? 20)} onChange={(v) => set('files', 'max_mb', Number(v))} autoComplete="off" />
                            </FormLayout.Group>
                        </BlockStack>
                    </Card>
                </Layout.AnnotatedSection>

                <Layout.AnnotatedSection title="Markets, currency & units" description="Prices are defined in your shop currency and converted with Shopify Markets rates, so the configurator matches checkout.">
                    <Card>
                        <FormLayout>
                            <FormLayout.Group>
                                <Select label="Units" value={s.markets.units || 'imperial'} onChange={(v) => set('markets', 'units', v)} options={[{ label: 'Imperial (in, sq ft)', value: 'imperial' }, { label: 'Metric (cm, m²)', value: 'metric' }]} />
                                <Select label="Ring size system" value={s.markets.ring_sizes || 'US'} onChange={(v) => set('markets', 'ring_sizes', v)} options={['US', 'UK', 'EU', 'IN', 'JP'].map((x) => ({ label: x, value: x }))} />
                            </FormLayout.Group>
                        </FormLayout>
                    </Card>
                </Layout.AnnotatedSection>

                <Layout.AnnotatedSection title="Production & notifications" description="Order tags, the production PDF and who gets told about new configured orders.">
                    <Card>
                        <FormLayout>
                            <FormLayout.Group>
                                <TextField label="Extra order tags" value={s.production.order_tags || ''} onChange={(v) => set('production', 'order_tags', v)} helpText="Comma-separated; “composa” is always added." autoComplete="off" />
                                <TextField label="Email production team at" type="email" value={s.production.workshop_email || ''} onChange={(v) => set('production', 'workshop_email', v)} autoComplete="off" />
                            </FormLayout.Group>
                            <Checkbox label="Email me when usage reaches 80% of a plan limit" checked={s.production.notify_usage !== false} onChange={(v) => set('production', 'notify_usage', v)} />
                            <FormLayout.Group>
                                <TextField label="PDF brand name" disabled={!features.pdf_branding} value={s.production.pdf?.name || ''} onChange={(v) => set('production', 'pdf', { ...(s.production.pdf || {}), name: v })} helpText={features.pdf_branding ? '' : 'Custom PDF branding is included on Pro.'} autoComplete="off" />
                                <TextField label="PDF footer" disabled={!features.pdf_branding} value={s.production.pdf?.footer || ''} onChange={(v) => set('production', 'pdf', { ...(s.production.pdf || {}), footer: v })} autoComplete="off" />
                            </FormLayout.Group>
                        </FormLayout>
                    </Card>
                </Layout.AnnotatedSection>

                <Layout.AnnotatedSection title="Default layouts for Shopify options" description="Applied when you map a product’s variants to steps.">
                    <Card>
                        <BlockStack gap="200">
                            {(s.variant_presets || []).map((p, i) => (
                                <InlineGrid key={i} columns="1.5fr 1fr" gap="200">
                                    <TextField labelHidden label="Option names" value={(p.match || []).join(', ')} onChange={(v) => setS({ ...s, variant_presets: s.variant_presets.map((x, j) => (j === i ? { ...x, match: v.split(',').map((y) => y.trim()).filter(Boolean) } : x)) })} autoComplete="off" />
                                    <Select labelHidden label="Layout" value={p.layout} onChange={(v) => setS({ ...s, variant_presets: s.variant_presets.map((x, j) => (j === i ? { ...x, layout: v, type: v === 'swatches' ? 'swatch' : v === 'tiles' ? 'image_tiles' : v === 'dropdown' ? 'dropdown' : v === 'radio' ? 'radio' : 'buttons' } : x)) })} options={LAYOUTS} />
                                </InlineGrid>
                            ))}
                        </BlockStack>
                    </Card>
                </Layout.AnnotatedSection>
            </Layout>
        </Page>
    );
}
