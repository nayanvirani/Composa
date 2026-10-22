<?php

namespace App\Services;

use App\Models\Configuration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Alignment;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Stacks a configuration's preview layers (image layers from Shopify's CDN, plus text overlays)
 * into one PNG for the cart line and the order. Generated once and cached in the bucket.
 */
class PreviewCompositor
{
    private const SIZE = 800;

    public function png(Configuration $configuration): string
    {
        $disk = Storage::disk(config('composa.uploads.disk'));
        $path = sprintf('shops/%d/previews/%s.png', $configuration->shop_id, $configuration->id);
        if ($disk->exists($path)) {
            return (string) $disk->get($path);
        }

        $manager = new ImageManager(new Driver);
        $canvas = $manager->createImage(self::SIZE, self::SIZE)->fill('f7f3ed');
        foreach ($configuration->result['layers'] ?? [] as $layer) {
            try {
                if (($layer['type'] ?? '') === 'image' && $this->allowed($layer['image'] ?? '')) {
                    $bytes = Http::timeout(8)->get($layer['image'])->throw()->body();
                    $img = $manager->decode($bytes)->contain(self::SIZE, self::SIZE);
                    $canvas->insert($img, 0, 0, Alignment::CENTER);
                } elseif (($layer['type'] ?? '') === 'text' && ($layer['text'] ?? '') !== '') {
                    $style = $layer['style'] ?? [];
                    $canvas->text($layer['text'], self::SIZE / 2, (int) (self::SIZE * (($style['top'] ?? 70) / 100)), function ($font) use ($style) {
                        $font->file(base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSerif.ttf'));
                        $font->size((int) (($style['size'] ?? 20) * 2));
                        $font->color(ltrim((string) ($style['color'] ?? '333333'), '#'));
                        $font->align(Alignment::CENTER, Alignment::CENTER);
                    });
                }
            } catch (Throwable) {
                // A missing layer image should never break the cart; skip it.
            }
        }
        $png = (string) $canvas->encodeUsingFormat(Format::PNG);
        $disk->put($path, $png);

        return $png;
    }

    /** Only fetch layer images from Shopify's CDN (no arbitrary URLs from definitions). */
    private function allowed(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';

        return str_starts_with($url, 'https://') && ($host === 'cdn.shopify.com' || str_ends_with($host, '.shopifycdn.com') || str_ends_with($host, '.myshopify.com'));
    }
}
