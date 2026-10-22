<?php

namespace App\Services;

use App\Models\Shop;
use App\Shopify\AdminApi;
use Throwable;

/**
 * Checks the live theme for Composa's app embed and the Customize button app block, and builds the
 * theme editor deep links the onboarding uses ("Enable in theme editor").
 */
class ThemeStatus
{
    private const QUERY = <<<'GQL'
    query ComposaTheme {
      themes(first: 1, roles: [MAIN]) {
        nodes {
          id name
          files(filenames: ["config/settings_data.json", "templates/product.json"], first: 2) {
            nodes { filename body { ... on OnlineStoreThemeFileBodyText { content } } }
          }
        }
      }
    }
    GQL;

    /** @return array{theme:?string,embed_enabled:bool,product_block:bool,checked_at:string} */
    public function check(Shop $shop): array
    {
        $result = ['theme' => null, 'embed_enabled' => false, 'product_block' => false, 'checked_at' => now()->toIso8601String()];
        try {
            $theme = AdminApi::for($shop)->query(self::QUERY)['themes']['nodes'][0] ?? null;
            if ($theme) {
                $result['theme'] = $theme['name'];
                foreach ($theme['files']['nodes'] ?? [] as $file) {
                    $json = self::parseJson($file['body']['content'] ?? '');
                    if ($file['filename'] === 'config/settings_data.json') {
                        $result['embed_enabled'] = self::hasBlock($json['current']['blocks'] ?? [], 'app-embed', checkDisabled: true);
                    } else {
                        $blocks = [];
                        foreach ($json['sections'] ?? [] as $section) {
                            $blocks = array_merge($blocks, array_values($section['blocks'] ?? []));
                        }
                        $result['product_block'] = self::hasBlock($blocks, 'customize-button');
                    }
                }
            }
        } catch (Throwable) {
            // Keep the previous status; the merchant can press "Check again".
            return $shop->setting('theme', $result);
        }
        $shop->forceFill(['settings' => array_merge($shop->settings ?? [], ['theme' => $result])])->save();

        return $result;
    }

    private static function hasBlock(array $blocks, string $handle, bool $checkDisabled = false): bool
    {
        foreach ($blocks as $block) {
            $type = (string) ($block['type'] ?? '');
            if (str_contains($type, '/blocks/'.$handle.'/') && str_contains($type, config('composa.shopify.app_handle'))) {
                if (! $checkDisabled || empty($block['disabled'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Theme JSON files may start with a comment block. */
    private static function parseJson(string $content): array
    {
        $content = preg_replace('#^\s*/\*.*?\*/#s', '', $content) ?? '';

        return json_decode($content, true) ?: [];
    }

    public static function embedLink(Shop $shop): string
    {
        return sprintf('https://%s/admin/themes/current/editor?context=apps&activateAppId=%s/app-embed', $shop->domain, config('composa.shopify.api_key'));
    }

    public static function blockLink(Shop $shop): string
    {
        return sprintf('https://%s/admin/themes/current/editor?template=product&addAppBlockId=%s/customize-button&target=mainSection', $shop->domain, config('composa.shopify.api_key'));
    }
}
