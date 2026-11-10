// The eleven input types and their defaults when added in the builder.
export const FIELD_TYPES = [
    { type: 'image_tiles', label: 'Image tiles', glyph: '▦', help: 'Big visual choices with photos', options: true },
    { type: 'swatch', label: 'Swatches', glyph: '●', help: 'Colours, metals, fabrics', options: true },
    { type: 'buttons', label: 'Buttons', glyph: '▭', help: 'Short text choices', options: true },
    { type: 'dropdown', label: 'Dropdown', glyph: '▾', help: 'Long lists like sizes', options: true },
    { type: 'radio', label: 'Radio', glyph: '◉', help: 'One of a few, with descriptions', options: true },
    { type: 'checkbox', label: 'Checkbox (multi)', glyph: '☑', help: 'Pick several add-ons', options: true },
    { type: 'text', label: 'Text', glyph: 'Aa', help: 'Engraving, names, messages' },
    { type: 'number', label: 'Number', glyph: '12', help: 'Quantities, weights, min/max' },
    { type: 'dimensions', label: 'Dimensions', glyph: 'W×H', help: 'Width × height, priced per unit' },
    { type: 'file', label: 'File upload', glyph: '↑', help: 'Artwork, photos, logos', feature: 'uploads' },
    { type: 'product_picker', label: 'Shopify product picker', glyph: '⌘', help: 'Shoppers choose from products you pick (add-ons, charms, gift items)', options: true },
];

export const CHOICE_TYPES = ['image_tiles', 'swatch', 'buttons', 'dropdown', 'radio', 'checkbox', 'product_picker'];

export const LAYOUTS = [
    { label: 'Swatches', value: 'swatches' },
    { label: 'Image tiles', value: 'tiles' },
    { label: 'Buttons', value: 'buttons' },
    { label: 'Size grid', value: 'size_grid' },
    { label: 'Pills', value: 'pills' },
    { label: 'Dropdown', value: 'dropdown' },
    { label: 'Radio list', value: 'radio' },
];

export function newField(type, key) {
    const base = { key, type, label: FIELD_TYPES.find((t) => t.type === type)?.label || 'Field', required: false };
    if (type === 'product_picker') return { ...base, options: [], layout: 'tiles', multiple: false };
    if (CHOICE_TYPES.includes(type)) {
        base.options = [
            { value: 'option_a', label: 'Option A', default: type !== 'checkbox' },
            { value: 'option_b', label: 'Option B' },
        ];
        base.required = type !== 'checkbox';
    }
    if (type === 'text') base.validation = { max_length: 40 };
    if (type === 'number') base.validation = { min: 0 };
    if (type === 'dimensions') Object.assign(base, { unit: 'in', validation: { min_w: 1, min_h: 1 } });
    if (type === 'file') base.validation = { file_types: ['png', 'jpg', 'pdf'], max_mb: 20 };
    return base;
}

/** Options from Shopify's resource picker: one per selected variant, linked so inventory is tracked. */
export function optionsFromPicker(selection, existing = []) {
    const seen = new Set(existing.map((o) => String(o.value)));
    const out = [];
    for (const product of selection || []) {
        const productImage = product.images?.[0]?.originalSrc || product.images?.[0]?.url || null;
        for (const v of product.variants || []) {
            const value = String(v.id).split('/').pop();
            if (seen.has(value)) continue;
            seen.add(value);
            const single = !v.title || v.title === 'Default Title' || (product.variants || []).length === 1;
            out.push({
                value,
                label: single ? product.title : product.title + ' — ' + v.title,
                image: v.image?.originalSrc || v.image?.url || productImage,
                linked_variant_id: v.id,
                price_rule: { type: 'fixed', amount: Number(v.price) || 0 },
            });
        }
    }
    return out;
}
