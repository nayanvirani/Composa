<?php

namespace App\Support;

/**
 * Default plans. Prices and trials here are only seed values: the live ones come from Shopify App
 * Pricing (Partner Dashboard) and are synced read-only. Limits and features are edited in super
 * admin. null = no limit.
 */
final class PlanCatalog
{
    public const PRICING_TYPES = ['fixed', 'percent', 'per_char', 'per_unit', 'tiered', 'table', 'formula'];

    /** @return list<array<string,mixed>> */
    public static function defaults(): array
    {
        return [
            [
                'handle' => 'free-dev', 'name' => 'Free (dev)', 'price_cents' => 0, 'trial_days' => 0, 'dev_only' => true, 'sort' => 0,
                'tagline' => 'For development stores and agencies building for clients',
                'limits' => ['configurators' => 1, 'drafts' => 3, 'steps' => 5, 'options' => 15, 'rules' => 5, 'products' => 10, 'storage_mb' => 0, 'languages' => 1],
                'features' => ['rules_level' => 'basic', 'pricing_types' => ['fixed', 'percent'], 'nested_steps' => false, 'uploads' => false, 'pdf' => false, 'pdf_branding' => false, 'analytics' => 'none', 'support' => 'Docs'],
            ],
            [
                'handle' => 'starter', 'name' => 'Starter', 'price_cents' => 1900, 'trial_days' => 14, 'sort' => 1,
                'tagline' => 'Your first custom product',
                'limits' => ['configurators' => 3, 'drafts' => 10, 'steps' => 10, 'options' => 30, 'rules' => 15, 'products' => 50, 'storage_mb' => 1024, 'languages' => 2],
                'features' => ['rules_level' => 'basic', 'pricing_types' => ['fixed', 'percent', 'per_char'], 'nested_steps' => false, 'uploads' => true, 'pdf' => true, 'pdf_branding' => false, 'analytics' => 'basic', 'support' => 'Email'],
            ],
            [
                'handle' => 'growth', 'name' => 'Growth', 'price_cents' => 4900, 'trial_days' => 14, 'sort' => 2, 'is_featured' => true,
                'tagline' => 'Complex rules and price grids',
                'limits' => ['configurators' => 15, 'drafts' => 30, 'steps' => 25, 'options' => 100, 'rules' => 75, 'products' => 500, 'storage_mb' => 10240, 'languages' => null],
                'features' => ['rules_level' => 'advanced', 'pricing_types' => ['fixed', 'percent', 'per_char', 'per_unit', 'tiered', 'table'], 'nested_steps' => true, 'uploads' => true, 'pdf' => true, 'pdf_branding' => false, 'analytics' => 'full', 'support' => 'Priority'],
            ],
            [
                'handle' => 'pro', 'name' => 'Pro', 'price_cents' => 14900, 'trial_days' => 14, 'sort' => 3,
                'tagline' => 'High volume, B2B and formula pricing',
                'limits' => ['configurators' => 50, 'drafts' => 100, 'steps' => 50, 'options' => 250, 'rules' => 250, 'products' => 5000, 'storage_mb' => 51200, 'languages' => null],
                'features' => ['rules_level' => 'advanced', 'pricing_types' => self::PRICING_TYPES, 'nested_steps' => true, 'uploads' => true, 'pdf' => true, 'pdf_branding' => true, 'analytics' => 'export', 'support' => 'Priority + setup call'],
            ],
        ];
    }

    /** @return array<string,string> */
    public static function limitLabels(): array
    {
        return [
            'configurators' => 'Published configurators',
            'drafts' => 'Draft configurators',
            'steps' => 'Steps per configurator',
            'options' => 'Options per field',
            'rules' => 'Rules per configurator',
            'products' => 'Assigned products',
            'storage_mb' => 'File storage (MB)',
            'languages' => 'Languages',
        ];
    }
}
