import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import { Badge, BlockStack, Button, Card, Collapsible, FormLayout, InlineGrid, InlineStack, Layout, Page, Select, Text, TextField } from '@shopify/polaris';
import { api, toast } from '@/lib/api';
import { date } from '@/lib/format';

export default function Support({ faqs, articles, tickets, supportLevel }) {
    const { app } = usePage().props;
    const [form, setForm] = useState({ subject: '', message: '', tag: 'question' });
    const [open, setOpen] = useState(null);
    const [busy, setBusy] = useState(false);

    const send = async () => {
        setBusy(true);
        try {
            await api('support', { method: 'POST', body: form });
            toast('Sent — we’ll reply by email');
            setForm({ subject: '', message: '', tag: 'question' });
        } catch (e) {
            toast(e.message, true);
        } finally {
            setBusy(false);
        }
    };

    return (
        <Page title="Help">
            <Layout>
                <Layout.Section>
                    <BlockStack gap="400">
                        <Card>
                            <BlockStack gap="300">
                                <InlineStack align="space-between"><Text as="h2" variant="headingMd">Contact support</Text><Badge>{supportLevel} support</Badge></InlineStack>
                                <FormLayout>
                                    <FormLayout.Group>
                                        <TextField label="Subject" value={form.subject} onChange={(v) => setForm({ ...form, subject: v })} autoComplete="off" />
                                        <Select label="About" value={form.tag} onChange={(v) => setForm({ ...form, tag: v })} options={[{ label: 'Question', value: 'question' }, { label: 'Setup help', value: 'setup' }, { label: 'Theme / storefront', value: 'theme' }, { label: 'Pricing or checkout', value: 'checkout' }, { label: 'Billing', value: 'billing' }]} />
                                    </FormLayout.Group>
                                    <TextField label="Message" value={form.message} onChange={(v) => setForm({ ...form, message: v })} multiline={5} autoComplete="off" />
                                    <InlineStack><Button variant="primary" onClick={send} loading={busy} disabled={!form.subject || !form.message}>Send</Button></InlineStack>
                                </FormLayout>
                            </BlockStack>
                        </Card>
                        <Card>
                            <BlockStack gap="200">
                                <Text as="h2" variant="headingMd">Questions</Text>
                                {faqs.map((f) => (
                                    <BlockStack key={f.id} gap="100">
                                        <Button variant="plain" textAlign="left" onClick={() => setOpen(open === f.id ? null : f.id)}>{f.question}</Button>
                                        <Collapsible open={open === f.id} id={'faq-' + f.id}><Text tone="subdued">{f.answer}</Text></Collapsible>
                                    </BlockStack>
                                ))}
                            </BlockStack>
                        </Card>
                    </BlockStack>
                </Layout.Section>
                <Layout.Section variant="oneThird">
                    <BlockStack gap="400">
                        <Card>
                            <BlockStack gap="200">
                                <Text as="h2" variant="headingMd">Start here</Text>
                                {articles.map((a) => <Button key={a.slug} variant="plain" textAlign="left" url={`${window.location.origin}/docs/${a.slug}`} external>{a.title} · {a.read_minutes} min</Button>)}
                            </BlockStack>
                        </Card>
                        <Card>
                            <BlockStack gap="200">
                                <Text as="h2" variant="headingMd">Your requests</Text>
                                {tickets.length === 0 && <Text tone="subdued">None yet.</Text>}
                                {tickets.map((t) => (
                                    <InlineGrid key={t.id} columns="1fr auto" gap="200">
                                        <BlockStack gap="050"><Text>{t.subject}</Text><Text variant="bodySm" tone="subdued">{date(t.created_at)}</Text></BlockStack>
                                        <Badge tone={t.status === 'closed' ? 'success' : 'attention'}>{t.status}</Badge>
                                    </InlineGrid>
                                ))}
                            </BlockStack>
                        </Card>
                    </BlockStack>
                </Layout.Section>
            </Layout>
        </Page>
    );
}
