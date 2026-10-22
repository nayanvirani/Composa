{{--
  Dedicated configurator page. Served as Liquid through the App Proxy, so Shopify renders it inside
  the store's theme layout. The app embed (theme app extension) loads the Composa storefront script,
  which mounts on [data-composa-page].
--}}
{% layout 'theme' %}
<div class="page-width" style="padding: 32px 0">
  <h1 class="h2">{{ $configurator->name }}</h1>
  <div
    data-composa-page
    data-configurator-key="cfg_{{ $configurator->id }}"
    data-product-id="{{ \App\Services\ConfigurationSigner::numericId((string) $configurator->base_product_id) }}"
    data-variant-id="{{ \App\Services\ConfigurationSigner::numericId((string) $configurator->base_variant_id) }}"
  ></div>
  @if ($productHandle)
  {%- assign composa_product = all_products['{{ $productHandle }}'] -%}
  <script type="application/json" data-composa-product>@{{ composa_product | json }}</script>
  @endif
  {% raw %}<script type="application/json" data-composa-config="cfg_{{ $configurator->id }}">@json($payload)</script>{% endraw %}
  {% if shop.metafields.composa.settings == blank %}
    <p>Enable the Composa app embed in your theme editor to show this configurator.</p>
  {% endif %}
</div>
