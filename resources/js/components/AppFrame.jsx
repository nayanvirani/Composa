import { useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Banner, BlockStack, Box } from '@shopify/polaris';
import { date, relative } from '@/lib/format';
import { openTop } from '@/lib/api';

const NAV = [
    ['/app', 'Home', true],
    ['/app/configurators', 'Configurators'],
    ['/app/orders', 'Orders'],
    ['/app/analytics', 'Analytics'],
    ['/app/translations', 'Translations'],
    ['/app/settings', 'Settings'],
    ['/app/billing', 'Plan & usage'],
    ['/app/support', 'Help'],
];

/** Wraps every merchant page: App Bridge navigation menu plus app-wide banners. */
export default function AppFrame({ children }) {
    const { props } = usePage();
    const { shop, impersonation, announcement } = props;

    // App Bridge menu links: route them through Inertia instead of reloading the iframe.
    useEffect(() => {
        const onNavigate = (event) => {
            const href = event?.target?.getAttribute?.('href');
            if (href && href.startsWith('/app')) {
                event.preventDefault?.();
                router.visit(href);
            }
        };
        document.addEventListener('shopify:navigate', onNavigate);
        return () => document.removeEventListener('shopify:navigate', onNavigate);
    }, []);

    const banners = [];
    if (impersonation) {
        banners.push(
            <Banner key="imp" tone="warning" title={`Support session (${impersonation.mode === 'write' ? 'write access' : 'read-only'})`}>
                Opened by {impersonation.admin}. Ends {relative(impersonation.expiresAt) || date(impersonation.expiresAt)}. Every action is logged.
            </Banner>,
        );
    }
    if (shop && !shop.plan) {
        banners.push(
            <Banner key="plan" tone="warning" title="Choose a plan to publish configurators" action={{ content: 'Choose plan on Shopify', onAction: () => openTop(shop.pricingUrl) }}>
                Plans start with a 14-day free trial and are billed on your Shopify invoice.
            </Banner>,
        );
    }
    if (shop?.graceUntil) {
        banners.push(
            <Banner key="grace" tone="warning" title="Your plan has fewer published configurators" action={{ content: 'Plan & usage', url: '/app/billing' }}>
                Extra configurators move to draft on {date(shop.graceUntil)} unless you unpublish some or upgrade. Live configurators keep selling until then.
            </Banner>,
        );
    }
    if (shop?.cartTransformConflict) {
        banners.push(
            <Banner key="ct" tone="critical" title="Another app is using this store’s cart transform">
                Shopify allows one cart transform per store, so configured prices can’t apply at checkout. Contact support and we’ll help you resolve it.
            </Banner>,
        );
    }
    if (announcement) {
        banners.push(
            <Banner key="ann" tone="info" action={announcement.linkUrl ? { content: announcement.linkLabel || 'Learn more', url: announcement.linkUrl, external: true } : undefined}>
                {announcement.message}
            </Banner>,
        );
    }

    return (
        <>
            <ui-nav-menu>
                {NAV.map(([href, label, home]) => (
                    <a key={href} href={href} rel={home ? 'home' : undefined}>
                        {label}
                    </a>
                ))}
            </ui-nav-menu>
            {banners.length > 0 && (
                <Box paddingInline="400" paddingBlockStart="400">
                    <BlockStack gap="200">{banners}</BlockStack>
                </Box>
            )}
            {children}
        </>
    );
}
