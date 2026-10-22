<?php

namespace App\Shopify;

use App\Models\Shop;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Shopify Admin GraphQL client for one shop. Retries when throttled and throws on GraphQL or
 * user errors so callers never silently continue with half-applied changes.
 */
class AdminApi
{
    public function __construct(private readonly Shop $shop) {}

    public static function for(Shop $shop): static
    {
        return new static($shop);
    }

    /**
     * @param  array<string,mixed>  $variables
     * @return array<string,mixed> the data object
     */
    public function query(string $query, array $variables = []): array
    {
        if (! $this->shop->access_token) {
            throw new RuntimeException('Shop '.$this->shop->domain.' has no access token.');
        }
        $url = sprintf('https://%s/admin/api/%s/graphql.json', $this->shop->domain, config('composa.shopify.api_version'));

        for ($attempt = 1; ; $attempt++) {
            /** @var Response $response */
            $response = Http::withHeaders(['X-Shopify-Access-Token' => $this->shop->access_token])
                ->acceptJson()->asJson()->timeout(30)
                ->post($url, ['query' => $query, 'variables' => (object) $variables]);

            $throttled = $response->status() === 429 || collect($response->json('errors', []))->contains(fn ($e) => ($e['extensions']['code'] ?? null) === 'THROTTLED');
            if ($throttled && $attempt < 4) {
                usleep(400_000 * $attempt);

                continue;
            }
            if ($response->status() === 401) {
                throw new RuntimeException('Shopify rejected the access token for '.$this->shop->domain.'.');
            }
            if (! $response->successful() || $response->json('errors')) {
                throw new RuntimeException('Shopify GraphQL error: '.json_encode($response->json('errors') ?? $response->status()));
            }

            return (array) $response->json('data');
        }
    }

    /**
     * Runs a mutation and throws when it reports userErrors.
     *
     * @return array<string,mixed> the mutation payload
     */
    public function mutate(string $query, array $variables, string $root): array
    {
        $payload = $this->query($query, $variables)[$root] ?? [];
        $errors = $payload['userErrors'] ?? [];
        if ($errors) {
            throw new RuntimeException($root.': '.collect($errors)->pluck('message')->implode('; '));
        }

        return $payload;
    }
}
