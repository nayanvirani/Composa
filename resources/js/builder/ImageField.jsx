import { useRef, useState } from 'react';
import { BlockStack, Button, InlineStack, Text, TextField, Thumbnail } from '@shopify/polaris';
import { api, toast } from '@/lib/api';

/** An image URL field with "Upload": the file goes to Shopify Files and loads from Shopify's CDN. */
export default function ImageField({ label = 'Image', value, onChange, helpText }) {
    const input = useRef(null);
    const [busy, setBusy] = useState(false);

    const upload = async (file) => {
        if (!file) return;
        setBusy(true);
        try {
            const body = new FormData();
            body.append('image', file);
            const r = await api('images', { method: 'POST', body });
            onChange(r.url);
        } catch (e) {
            toast(e.message, true);
        } finally {
            setBusy(false);
            if (input.current) input.current.value = '';
        }
    };

    return (
        <BlockStack gap="100">
            <InlineStack gap="200" blockAlign="end" wrap={false}>
                {value ? <Thumbnail size="small" source={value} alt="" /> : null}
                <div style={{ flex: 1 }}>
                    <TextField label={label} value={value || ''} onChange={onChange} placeholder="https://cdn.shopify.com/…" autoComplete="off" />
                </div>
                <Button onClick={() => input.current?.click()} loading={busy}>Upload</Button>
                {value ? <Button variant="plain" tone="critical" onClick={() => onChange(null)}>Remove</Button> : null}
            </InlineStack>
            <input ref={input} type="file" accept="image/*" hidden onChange={(e) => upload(e.target.files?.[0])} />
            {helpText && <Text variant="bodySm" tone="subdued">{helpText}</Text>}
        </BlockStack>
    );
}
