<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Composa plan. Price and trial come from Shopify App Pricing (Partner Dashboard) and are
 * synced read-only; limits, features and pricing-page copy are managed in super admin.
 *
 * limits: configurators, drafts, steps, options, rules, products, storage_mb, languages (null = no limit)
 * features: rules_level (basic|advanced), pricing_types[], nested_steps, uploads, pdf, pdf_branding,
 *           analytics (none|basic|full|export), support
 */
class Plan extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'limits' => 'array',
            'features' => 'array',
            'dev_only' => 'boolean',
            'is_public' => 'boolean',
            'is_featured' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }

    public function limit(string $key): ?int
    {
        $value = $this->limits[$key] ?? null;

        return $value === null || $value === '' ? null : (int) $value;
    }

    public function feature(string $key, mixed $default = null): mixed
    {
        return $this->features[$key] ?? $default;
    }

    public function allowsPricingType(string $type): bool
    {
        return in_array($type, $this->features['pricing_types'] ?? [], true);
    }

    public function priceLabel(): string
    {
        return '$'.rtrim(rtrim(number_format($this->price_cents / 100, 2), '0'), '.');
    }
}
