import { BlockStack, InlineGrid, InlineStack, ProgressBar, Text } from '@shopify/polaris';
import { LIMIT_LABELS } from '@/lib/format';

const tone = (state) => (state === 'reached' ? 'critical' : state === 'warning' ? 'highlight' : 'primary');

/** Usage against plan limits (shared "usage" prop). */
export default function UsageMeters({ meters, keys, columns = { xs: 1, sm: 2, md: 4 } }) {
    if (!meters) return null;
    const list = (keys || Object.keys(LIMIT_LABELS)).filter((k) => meters[k]);
    return (
        <InlineGrid columns={columns} gap="400">
            {list.map((key) => {
                const m = meters[key];
                const unlimited = m.limit === null;
                return (
                    <BlockStack gap="100" key={key}>
                        <InlineStack align="space-between">
                            <Text as="span" variant="bodySm" tone="subdued">
                                {LIMIT_LABELS[key]}
                            </Text>
                            <Text as="span" variant="bodySm" fontWeight="semibold">
                                {m.used} / {unlimited ? 'No limit' : m.limit}
                            </Text>
                        </InlineStack>
                        <ProgressBar progress={unlimited ? 0 : Math.min(100, m.ratio * 100)} tone={tone(m.state)} size="small" />
                        <Text as="span" variant="bodyXs" tone={m.state === 'ok' ? 'subdued' : 'caution'}>
                            {m.state === 'reached' ? 'Limit reached — new ones are paused' : m.state === 'warning' ? 'Nearing limit' : unlimited ? 'Included' : Math.round(m.ratio * 100) + '% used'}
                        </Text>
                    </BlockStack>
                );
            })}
        </InlineGrid>
    );
}
