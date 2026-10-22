import { Box, InlineGrid, InlineStack, Text } from '@shopify/polaris';

const STEPS = ['Choose plan', 'Pick a template', 'Enable app embed', 'Assign & publish'];

/** Onboarding progress (1–4). */
export default function SetupSteps({ current }) {
    return (
        <InlineGrid columns={{ xs: 2, md: 4 }} gap="200">
            {STEPS.map((label, i) => {
                const done = i < current;
                const on = i === current;
                return (
                    <Box key={label} padding="300" background="bg-surface" borderRadius="200" borderWidth="025" borderColor={on ? 'border-emphasis' : 'border'}>
                        <InlineStack gap="200" blockAlign="center" wrap={false}>
                            <Box background={on ? 'bg-fill-inverse' : done ? 'bg-fill-success' : 'bg-fill-secondary'} borderRadius="full" minWidth="24px" minHeight="24px">
                                <div style={{ width: 24, height: 24, display: 'flex', alignItems: 'center', justifyContent: 'center', color: on || done ? '#fff' : 'inherit', fontSize: 12, fontWeight: 600 }}>{done ? '✓' : i + 1}</div>
                            </Box>
                            <Text fontWeight={on ? 'semibold' : 'regular'}>{label}</Text>
                        </InlineStack>
                    </Box>
                );
            })}
        </InlineGrid>
    );
}
