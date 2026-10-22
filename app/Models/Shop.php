<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A Shopify store with Composa installed. One per store; the offline access token and the
 * per-shop signing secret (shared with the Cart Transform Function) are stored encrypted.
 */
class Shop extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['access_token', 'signing_secret'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'signing_secret' => 'encrypted',
            'locales' => 'array',
            'onboarding' => 'array',
            'settings' => 'array',
            'limit_overrides' => 'array',
            'is_plus' => 'boolean',
            'is_dev' => 'boolean',
            'trial_ends_at' => 'datetime',
            'installed_at' => 'datetime',
            'uninstalled_at' => 'datetime',
            'purge_after' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function configurators(): HasMany
    {
        return $this->hasMany(Configurator::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function configurations(): HasMany
    {
        return $this->hasMany(Configuration::class);
    }

    public function configuredOrders(): HasMany
    {
        return $this->hasMany(ConfiguredOrder::class);
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(Upload::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ShopNote::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->access_token !== null;
    }

    /** The storefront-facing handle, e.g. maison-oro for maison-oro.myshopify.com. */
    public function handle(): string
    {
        return Str::before($this->domain, '.myshopify.com');
    }

    /** Admin URL of Shopify's hosted plan selection page for this app (Shopify App Pricing). */
    public function pricingPageUrl(): string
    {
        return sprintf('https://admin.shopify.com/store/%s/charges/%s/pricing_plans', $this->handle(), config('composa.shopify.app_handle'));
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings ?? [], $key, $default);
    }

    public function ensureSigningSecret(): string
    {
        if (! $this->signing_secret) {
            $this->signing_secret = Str::random(48);
            $this->save();
        }

        return $this->signing_secret;
    }
}
