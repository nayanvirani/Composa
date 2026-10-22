<?php

namespace App\Services;

use App\Engine\Engine;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\Shop;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Add to cart, server side: recalculates the price from the published definition and live Shopify
 * variant prices (never from the browser), stores the Configuration and signs it. The storefront
 * then adds only the base variant with the returned line properties; the Cart Transform verifies
 * the signature and sets the price.
 */
class ConfigurationFactory
{
    public function __construct(private readonly ProductCatalog $catalog) {}

    /** @return array{configurator:Configurator,product:?array,result:array,base_variant:?array} */
    public function evaluate(Shop $shop, int $configuratorId, array $answers, int $quantity, ?string $productId = null, ?string $variantId = null, ?string $country = null): array
    {
        $configurator = $shop->configurators()->with('publishedVersion')->where('status', 'published')->findOrFail($configuratorId);
        $definition = $configurator->publishedVersion->definition;
        $productId = $configurator->base_product_id ?: $productId;
        $product = $productId ? $this->catalog->product($shop, $productId) : null;
        $hasVariantSteps = (bool) array_filter(Engine::fieldsOf($definition), fn ($x) => ! empty($x['field']['variant_option']));

        $baseVariant = $this->catalog->variant($product, $variantId ?: $configurator->base_variant_id) ?? ($product['variants'][0] ?? null);
        $result = Engine::evaluate($definition, $answers, [
            'base_price_cents' => (int) ($baseVariant['price_cents'] ?? 0),
            'quantity' => $quantity,
            'variants' => $hasVariantSteps && $product ? $this->catalog->engineVariants($product) : null,
            'country' => $country,
        ]);

        return ['configurator' => $configurator, 'product' => $product, 'result' => $result, 'base_variant' => $result['variant_id'] ? $this->catalog->variant($product, $result['variant_id']) : $baseVariant];
    }

    /** @return array<string,mixed> what the storefront needs to add the line */
    public function create(Shop $shop, int $configuratorId, array $answers, int $quantity, ?string $productId, ?string $variantId, ?string $country = null): array
    {
        ['configurator' => $configurator, 'result' => $result, 'base_variant' => $variant] = $this->evaluate($shop, $configuratorId, $answers, $quantity, $productId, $variantId, $country);
        if (! $result['valid']) {
            throw ValidationException::withMessages(['answers' => array_map(fn ($e) => ($e['field'] ? $e['field'].': ' : '').$e['code'], $result['errors'])]);
        }
        if (! $variant) {
            throw ValidationException::withMessages(['variant' => 'This configurator has no base product.']);
        }
        if (($variant['available'] ?? true) === false) {
            throw ValidationException::withMessages(['variant' => 'This combination is sold out.']);
        }
        $this->checkUploads($shop, $configurator, $answers);

        $id = 'cfg_'.Str::lower(Str::random(18));
        $price = (int) $result['price']['unit_cents'];
        $base = (int) $variant['price_cents'];
        $components = $shop->cart_mode === 'expand' ? array_values(array_filter($result['price']['components'], fn ($c) => $c['cents'] > 0)) : [];
        $signature = ConfigurationSigner::sign((string) $shop->ensureSigningSecret(), ConfigurationSigner::message($shop->domain, $id, $price, $base, $shop->currency, $components));
        $definition = $configurator->publishedVersion->definition;
        $summary = array_slice(SpecSheet::rows($definition, $result['answers'], onlySummary: true), 0, max(1, (int) $shop->setting('cart.summary_lines', 6)));
        $imageUrl = $this->imageUrl($shop, $id, $result['layers']);
        $title = ($configurator->display['cart_title'] ?? null) ?: (($shop->setting('cart.line_title', 'product_custom') === 'product' ? null : ($configurator->base_product_title ?: $configurator->name).' — Custom'));

        $result['price']['components'] = $components;
        $configuration = Configuration::query()->create([
            'id' => $id,
            'shop_id' => $shop->id,
            'configurator_id' => $configurator->id,
            'version_id' => $configurator->published_version_id,
            'answers' => $result['answers'],
            'result' => ['price' => $result['price'], 'layers' => $result['layers'], 'variant_id' => $result['variant_id']],
            'summary' => $summary,
            'variant_id' => $variant['id'],
            'quantity' => $quantity,
            'base_price_cents' => $base,
            'price_cents' => $price,
            'currency' => $shop->currency,
            'signature' => $signature,
            'image_url' => $imageUrl,
            'status' => 'cart',
            'expires_at' => now()->addDays(config('composa.cart.configuration_ttl_days')),
        ]);
        $this->attachUploads($shop, $configuration, $answers);

        $properties = [];
        foreach ($summary as $row) {
            $properties[$row['label']] = $row['value'];
        }
        $properties += [
            '_config_id' => $id,
            '_price' => (string) $price,
            '_base' => (string) $base,
            '_cur' => $shop->currency,
            '_sig' => $signature,
        ];
        if ($components) {
            $properties['_lnk'] = ConfigurationSigner::encodeComponents($components);
        }
        if ($title) {
            $properties['_title'] = mb_substr($title, 0, 250);
        }
        if ($imageUrl) {
            $properties['_img'] = $imageUrl;
        }

        return [
            'config_id' => $id,
            'variant_id' => ConfigurationSigner::numericId($variant['id']),
            'quantity' => $quantity,
            'properties' => $properties,
            'price_cents' => $price,
            'currency' => $shop->currency,
            'image_url' => $imageUrl,
            'title' => $title,
        ];
    }

    /** Public cart image URL on the shop's own domain, served by the App Proxy (null without image layers). */
    private function imageUrl(Shop $shop, string $configId, array $layers): ?string
    {
        if (! array_filter($layers, fn ($l) => ($l['type'] ?? '') === 'image')) {
            return null;
        }

        return sprintf('https://%s/%s/%s/image/%s.png', $shop->domain, config('composa.shopify.proxy_prefix'), config('composa.shopify.proxy_subpath'), $configId);
    }

    private function checkUploads(Shop $shop, Configurator $configurator, array $answers): void
    {
        foreach (Engine::fieldsOf($configurator->publishedVersion->definition) as ['field' => $field]) {
            if (($field['type'] ?? '') !== 'file' || empty($answers[$field['key']])) {
                continue;
            }
            $id = is_array($answers[$field['key']]) ? ($answers[$field['key']]['id'] ?? null) : $answers[$field['key']];
            $exists = $id && $shop->uploads()->whereKey($id)->whereIn('status', ['uploaded', 'attached'])->exists();
            if (! $exists) {
                throw ValidationException::withMessages([$field['key'] => 'Upload the file again — it was not received.']);
            }
        }
    }

    private function attachUploads(Shop $shop, Configuration $configuration, array $answers): void
    {
        foreach ($answers as $key => $value) {
            $id = is_array($value) ? ($value['id'] ?? null) : (is_string($value) && str_starts_with($value, 'upl_') ? $value : null);
            if ($id) {
                $shop->uploads()->whereKey($id)->update(['configuration_id' => $configuration->id, 'field_key' => $key, 'status' => 'attached']);
            }
        }
    }
}
