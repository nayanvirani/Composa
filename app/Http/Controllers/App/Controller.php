<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Configurator;
use App\Models\Shop;

/** Base for the embedded merchant app: everything is scoped to the authenticated shop. */
abstract class Controller extends BaseController
{
    protected function shop(): Shop
    {
        return request()->attributes->get('shop');
    }

    protected function configurator(int|string $id): Configurator
    {
        return $this->shop()->configurators()->with(['draftVersion', 'publishedVersion'])->findOrFail($id);
    }

    protected function money(int $cents): string
    {
        return number_format($cents / 100, 2).' '.$this->shop()->currency;
    }
}
