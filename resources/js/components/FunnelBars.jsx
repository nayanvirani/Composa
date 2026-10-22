import { BlockStack, Box, InlineGrid, Text } from '@shopify/polaris';

/** Share of opens reaching each step; the biggest drop is called out. */
export default function FunnelBars({ rows = [] }) {
    if (!rows.length || !rows[0].count) return <Text tone="subdued">No opens yet. Data appears once shoppers start using a published configurator.</Text>;
    let worst = -1;
    let worstDrop = 0;
    rows.forEach((r, i) => {
        if (i > 0 && rows[i - 1].percent - r.percent > worstDrop) {
            worstDrop = rows[i - 1].percent - r.percent;
            worst = i;
        }
    });
    return (
        <BlockStack gap="200">
            {rows.map((r, i) => (
                <InlineGrid key={r.step + i} columns="120px 1fr 56px" gap="300" alignItems="center">
                    <Text variant="bodySm">{r.step}</Text>
                    <div title={`${r.step}: ${r.percent}% of opens`} style={{ height: 16, background: 'var(--p-color-bg-fill-secondary)', borderRadius: 4, overflow: 'hidden' }}>
                        <div style={{ height: 16, width: `${Math.max(0, Math.min(100, r.percent))}%`, background: 'var(--p-color-bg-fill-brand)', borderRadius: '0 4px 4px 0' }} />
                    </div>
                    <Text variant="bodySm" alignment="end" fontWeight={i === worst ? 'bold' : 'regular'} tone={i === worst ? 'critical' : undefined}>{r.percent}%</Text>
                </InlineGrid>
            ))}
            {worst > 0 && (
                <Box paddingBlockStart="200">
                    <Text variant="bodySm" tone="caution">Biggest drop: {rows[worst - 1].step} → {rows[worst].step} (−{Math.round(worstDrop)} pts).</Text>
                </Box>
            )}
        </BlockStack>
    );
}
