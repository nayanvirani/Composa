import { useMemo, useState } from 'react';
import { BlockStack, Box, Button, ButtonGroup, Card, InlineStack, Text } from '@shopify/polaris';
import { evaluate } from '@engine';
import { money } from '@/lib/format';

/**
 * Live preview: the same engine the storefront runs, on the current draft. Clicking an option sets
 * a test answer so rules, availability and price can be checked while editing.
 */
export default function PreviewPanel({ definition, basePriceCents, variants, currency }) {
    const [answers, setAnswers] = useState({});
    const [stepIndex, setStepIndex] = useState(0);
    const [device, setDevice] = useState('desktop');
    const result = useMemo(() => evaluate(definition, answers, { base_price_cents: basePriceCents, quantity: 1, variants }), [definition, answers, basePriceCents, variants]);
    const steps = (definition.steps || []).filter((s) => result.visible_steps.includes(s.key));
    const step = steps[Math.min(stepIndex, steps.length - 1)];

    const pick = (field, value) => {
        if (field.type === 'checkbox') {
            const cur = Array.isArray(answers[field.key]) ? answers[field.key] : [];
            setAnswers({ ...answers, [field.key]: cur.includes(value) ? cur.filter((v) => v !== value) : [...cur, value] });
        } else setAnswers({ ...answers, [field.key]: value });
    };

    return (
        <Card>
            <BlockStack gap="300">
                <InlineStack align="space-between">
                    <Text variant="bodySm" tone="subdued" fontWeight="semibold">LIVE PREVIEW</Text>
                    <ButtonGroup variant="segmented">
                        <Button size="micro" pressed={device === 'desktop'} onClick={() => setDevice('desktop')}>Desktop</Button>
                        <Button size="micro" pressed={device === 'mobile'} onClick={() => setDevice('mobile')}>Mobile</Button>
                    </ButtonGroup>
                </InlineStack>
                <div style={{ maxWidth: device === 'mobile' ? 300 : '100%', margin: '0 auto', border: '1px solid var(--p-color-border)', borderRadius: 12, overflow: 'hidden', width: '100%' }}>
                    <div style={{ position: 'relative', aspectRatio: device === 'mobile' ? '4 / 3' : '16 / 9', background: '#F7F3ED' }}>
                        {result.layers.map((l) =>
                            l.type === 'text' ? (
                                <div key={l.id} style={{ position: 'absolute', left: 0, right: 0, top: (l.style?.top ?? 70) + '%', textAlign: 'center', color: l.style?.color, fontSize: (l.style?.size || 20) * 0.8, fontStyle: 'italic' }}>{l.text}</div>
                            ) : (
                                <img key={l.id} src={l.image} alt="" style={{ position: 'absolute', inset: 0, width: '100%', height: '100%', objectFit: 'contain' }} />
                            ),
                        )}
                        {!result.layers.length && <div style={{ position: 'absolute', inset: 0, display: 'grid', placeItems: 'center', color: '#8A8073', fontSize: 12 }}>Add preview layers to show the product</div>}
                    </div>
                    <Box padding="300">
                        <BlockStack gap="200">
                            <InlineStack gap="100">
                                {steps.map((s, i) => (
                                    <Button key={s.key} size="micro" pressed={step?.key === s.key} onClick={() => setStepIndex(i)}>{s.title}</Button>
                                ))}
                            </InlineStack>
                            {step && <Text variant="bodySm" tone="subdued">Step {steps.indexOf(step) + 1} of {steps.length} · {step.title}</Text>}
                            {step?.fields?.filter((f) => result.fields[f.key]?.visible).map((f) => (
                                <BlockStack key={f.key} gap="100">
                                    <Text variant="bodySm" fontWeight="semibold">{f.label}{result.fields[f.key]?.required ? ' *' : ''}</Text>
                                    {f.options?.length ? (
                                        <InlineStack gap="100">
                                            {f.options.filter((o) => result.fields[f.key]?.options[String(o.value)]?.visible !== false).map((o) => {
                                                const state = result.fields[f.key]?.options[String(o.value)] || {};
                                                const selected = Array.isArray(result.answers[f.key]) ? result.answers[f.key].includes(o.value) : String(result.answers[f.key]) === String(o.value);
                                                return (
                                                    <Button key={o.value} size="micro" pressed={selected} disabled={state.disabled} onClick={() => pick(f, o.value)}>
                                                        {o.label}
                                                    </Button>
                                                );
                                            })}
                                        </InlineStack>
                                    ) : (
                                        <input aria-label={f.label} style={{ width: '100%', padding: 6, border: '1px solid var(--p-color-border)', borderRadius: 6 }} placeholder={f.placeholder || ''} value={typeof answers[f.key] === 'object' ? '' : answers[f.key] ?? ''} onChange={(e) => setAnswers({ ...answers, [f.key]: f.type === 'number' ? Number(e.target.value) : e.target.value })} />
                                    )}
                                </BlockStack>
                            ))}
                            {step?.repeatable && <Text variant="bodySm" tone="subdued">Repeatable step — shoppers add items one by one.</Text>}
                        </BlockStack>
                    </Box>
                    <div style={{ background: '#1C1917', color: '#fff', padding: '10px 12px', display: 'flex', justifyContent: 'space-between', fontSize: 14 }}>
                        <strong>{money(result.price.unit_cents, currency)}</strong>
                        <span>{result.valid ? 'Add to cart' : 'Next →'}</span>
                    </div>
                </div>
                {result.errors.length > 0 && <Text variant="bodySm" tone="caution">{result.errors.map((e) => (e.field || 'configurator') + ': ' + e.code.replaceAll('_', ' ')).join(' · ')}</Text>}
                <Button size="slim" variant="plain" onClick={() => setAnswers({})}>Reset test answers</Button>
            </BlockStack>
        </Card>
    );
}
