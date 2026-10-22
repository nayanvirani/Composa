import { BlockStack, Button, ButtonGroup, Card, InlineGrid, InlineStack, Select, Text, TextField } from '@shopify/polaris';
import ImageField from './ImageField';

/** Preview layers: images that swap with answers, plus text overlays (engraving, print). */
export default function LayersTab({ definition, update }) {
    const layers = definition.layers || [];
    const fields = (definition.steps || []).flatMap((s) => s.fields || []);
    const setLayers = (next) => update({ ...definition, layers: next });
    const setLayer = (i, patch) => setLayers(layers.map((l, j) => (j === i ? { ...l, ...patch } : l)));
    const sorted = layers.map((l, i) => ({ l, i })).sort((a, b) => (b.l.z || 0) - (a.l.z || 0));

    return (
        <InlineGrid columns={{ xs: 1, md: '1fr 360px' }} gap="400">
            <BlockStack gap="300">
                <InlineStack align="space-between">
                    <Text as="h2" variant="headingMd">Layers · top first</Text>
                    <ButtonGroup>
                        <Button onClick={() => setLayers([...layers, { id: 'layer_' + (layers.length + 1), name: 'Image layer', z: layers.length, type: 'image' }])}>Add image layer</Button>
                        <Button onClick={() => setLayers([...layers, { id: 'text_' + (layers.length + 1), name: 'Text layer', z: layers.length + 10, type: 'text', field: fields.find((f) => f.type === 'text')?.key, style: { font: 'serif', color: '#333333', size: 20 } }])}>Add text layer</Button>
                    </ButtonGroup>
                </InlineStack>
                {sorted.map(({ l, i }) => {
                    const driver = fields.find((f) => f.key === l.field);
                    return (
                        <Card key={l.id}>
                            <BlockStack gap="300">
                                <InlineGrid columns="1fr 100px auto" gap="200" alignItems="end">
                                    <TextField label="Name" value={l.name || l.id} onChange={(v) => setLayer(i, { name: v })} autoComplete="off" />
                                    <TextField label="Order (z)" type="number" value={String(l.z ?? 0)} onChange={(v) => setLayer(i, { z: Number(v) })} autoComplete="off" />
                                    <Button tone="critical" onClick={() => setLayers(layers.filter((_, j) => j !== i))}>Remove</Button>
                                </InlineGrid>
                                {l.type === 'text' ? (
                                    <InlineGrid columns={{ xs: 1, md: 4 }} gap="200">
                                        <Select label="Text from" value={l.field || ''} onChange={(v) => setLayer(i, { field: v })} options={fields.filter((f) => f.type === 'text').map((f) => ({ label: f.label, value: f.key }))} />
                                        <Select label="Font" value={l.style?.font || 'serif'} onChange={(v) => setLayer(i, { style: { ...l.style, font: v } })} options={['serif', 'sans', 'script', 'mono', 'block'].map((f) => ({ label: f, value: f }))} />
                                        <TextField label="Colour" value={l.style?.color || ''} onChange={(v) => setLayer(i, { style: { ...l.style, color: v } })} autoComplete="off" />
                                        <TextField label="Size (px)" type="number" value={String(l.style?.size ?? 20)} onChange={(v) => setLayer(i, { style: { ...l.style, size: Number(v) } })} autoComplete="off" />
                                        <TextField label="Top (%)" type="number" value={String(l.style?.top ?? 70)} onChange={(v) => setLayer(i, { style: { ...l.style, top: Number(v) } })} autoComplete="off" />
                                        <Select label="Path" value={l.style?.path || 'straight'} onChange={(v) => setLayer(i, { style: { ...l.style, path: v } })} options={[{ label: 'Straight', value: 'straight' }, { label: 'Curve', value: 'curve' }]} />
                                    </InlineGrid>
                                ) : (
                                    <BlockStack gap="200">
                                        <Select label="Image changes with" value={l.field || ''} onChange={(v) => setLayer(i, { field: v || null })} options={[{ label: 'Always the same image', value: '' }, ...fields.filter((f) => f.options?.length).map((f) => ({ label: f.label || f.key, value: f.key }))]} />
                                        {!driver && <ImageField label="Image" value={l.image} onChange={(v) => setLayer(i, { image: v })} />}
                                        {driver && driver.options.map((o) => <ImageField key={o.value} label={o.label} value={l.images?.[o.value]} onChange={(v) => setLayer(i, { images: { ...(l.images || {}), [o.value]: v } })} />)}
                                    </BlockStack>
                                )}
                            </BlockStack>
                        </Card>
                    );
                })}
                <Text variant="bodySm" tone="subdued">Images load from Shopify’s CDN. The composite image is also used on the cart line and the order.</Text>
            </BlockStack>
            <Card>
                <BlockStack gap="200">
                    <Text variant="bodySm" tone="subdued">Stack preview (first option of each driver)</Text>
                    <div style={{ position: 'relative', aspectRatio: '1 / 1', background: '#F7F3ED', borderRadius: 8, overflow: 'hidden' }}>
                        {[...layers].sort((a, b) => (a.z || 0) - (b.z || 0)).map((l) => {
                            if (l.type === 'text') return <div key={l.id} style={{ position: 'absolute', left: 0, right: 0, top: (l.style?.top ?? 70) + '%', textAlign: 'center', color: l.style?.color, fontSize: l.style?.size || 20, fontFamily: l.style?.font === 'script' ? 'cursive' : l.style?.font === 'mono' ? 'monospace' : l.style?.font === 'sans' ? 'sans-serif' : 'serif' }}>Sample text</div>;
                            const driver = fields.find((f) => f.key === l.field);
                            const src = driver ? l.images?.[driver.options?.[0]?.value] : l.image;
                            return src ? <img key={l.id} src={src} alt="" style={{ position: 'absolute', inset: 0, width: '100%', height: '100%', objectFit: 'contain' }} /> : null;
                        })}
                    </div>
                </BlockStack>
            </Card>
        </InlineGrid>
    );
}
