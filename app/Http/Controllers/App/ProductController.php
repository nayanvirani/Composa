<?php

namespace App\Http\Controllers\App;

use App\Services\ProductCatalog;
use App\Services\ShopifyFiles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function search(Request $request, ProductCatalog $catalog): JsonResponse
    {
        $data = $request->validate(['type' => 'required|in:product,collection,tag,vendor', 'q' => 'nullable|string|max:120', 'after' => 'nullable|string|max:255']);

        return response()->json($catalog->search($this->shop(), $data['type'], $data['q'] ?? '', $data['after'] ?? null));
    }

    public function show(Request $request, ProductCatalog $catalog): JsonResponse
    {
        $id = (string) $request->validate(['id' => 'required|string|max:120'])['id'];
        $product = $catalog->product($this->shop(), $id);
        abort_unless($product, 404);

        return response()->json($product);
    }

    /** Option and layer images go to Shopify Files and are served from Shopify's CDN. */
    public function uploadImage(Request $request, ShopifyFiles $files): JsonResponse
    {
        $request->validate(['image' => 'required|image|max:10240', 'alt' => 'nullable|string|max:255']);

        return response()->json(['url' => $files->upload($this->shop(), $request->file('image'), $request->input('alt'))]);
    }
}
