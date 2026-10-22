<?php

namespace App\Services;

use App\Models\Shop;
use App\Shopify\AdminApi;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Uploads merchant images (option images, preview layers) to Shopify Files so shoppers load them
 * from Shopify's CDN — Railway buckets are private and egress from the app costs money.
 */
class ShopifyFiles
{
    public function upload(Shop $shop, UploadedFile $file, ?string $alt = null): string
    {
        $api = AdminApi::for($shop);
        $staged = $api->mutate(
            'mutation($input: [StagedUploadInput!]!) { stagedUploadsCreate(input: $input) { stagedTargets { url resourceUrl parameters { name value } } userErrors { field message } } }',
            ['input' => [['resource' => 'IMAGE', 'filename' => $file->getClientOriginalName(), 'mimeType' => $file->getMimeType(), 'httpMethod' => 'POST', 'fileSize' => (string) $file->getSize()]]],
            'stagedUploadsCreate',
        )['stagedTargets'][0] ?? throw new RuntimeException('No staged upload target.');

        $request = Http::asMultipart()->timeout(60);
        foreach ($staged['parameters'] as $p) {
            $request = $request->attach($p['name'], $p['value']);
        }
        $response = $request->attach('file', fopen($file->getRealPath(), 'r'), $file->getClientOriginalName())->post($staged['url']);
        if (! $response->successful()) {
            throw new RuntimeException('Upload to Shopify failed ('.$response->status().').');
        }

        $created = $api->mutate(
            'mutation($files: [FileCreateInput!]!) { fileCreate(files: $files) { files { id ... on MediaImage { image { url } } } userErrors { field message } } }',
            ['files' => [['originalSource' => $staged['resourceUrl'], 'contentType' => 'IMAGE', 'alt' => $alt ?? '']]],
            'fileCreate',
        );
        $id = $created['files'][0]['id'] ?? throw new RuntimeException('File was not created.');

        // Shopify processes images asynchronously; wait briefly for the CDN URL.
        for ($i = 0; $i < 10; $i++) {
            $url = $created['files'][0]['image']['url'] ?? null;
            if ($url) {
                return $url;
            }
            usleep(500_000);
            $created['files'][0] = $api->query('query($id: ID!) { node(id: $id) { ... on MediaImage { image { url } } } }', ['id' => $id])['node'] ?? [];
        }
        throw new RuntimeException('Shopify is still processing the image; try again in a moment.');
    }
}
