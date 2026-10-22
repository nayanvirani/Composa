// Composa storefront loader (app embed, every page). Must stay under 10 KB: it only wires up the
// Customize buttons, collection-card links, the dedicated page and the cart display. The full
// configurator UI (composa-ui.js) is fetched the first time a shopper opens it.
(function () {
  var w = window, d = document;
  if (w.__composaLoader) return;
  w.__composaLoader = true;

  var embed = d.querySelector('script[data-composa-embed]');
  var settings = readJSON('[data-composa-settings]') || {};
  var uiUrl = embed && embed.getAttribute('data-ui');
  var cssUrl = embed && embed.getAttribute('data-css');
  var uiPromise = null;
  var t0 = performance.now();

  function readJSON(sel) {
    var el = typeof sel === 'string' ? d.querySelector(sel) : sel;
    if (!el) return null;
    try { return JSON.parse(el.textContent); } catch (e) { return null; }
  }

  function loadUI() {
    if (uiPromise) return uiPromise;
    uiPromise = new Promise(function (resolve, reject) {
      if (w.ComposaUI) return resolve(w.ComposaUI);
      if (cssUrl && !d.querySelector('link[data-composa-css]')) {
        var l = d.createElement('link');
        l.rel = 'stylesheet'; l.href = cssUrl; l.setAttribute('data-composa-css', '');
        d.head.appendChild(l);
      }
      var s = d.createElement('script');
      s.src = uiUrl; s.async = true;
      s.onload = function () { w.ComposaUI ? resolve(w.ComposaUI) : reject(new Error('Composa UI missing')); };
      s.onerror = reject;
      d.head.appendChild(s);
    });
    return uiPromise;
  }

  function configFor(key) {
    return readJSON('script[data-composa-config="' + key + '"]');
  }

  function productFor(el) {
    var id = el.getAttribute('data-product-json');
    return readJSON(id ? d.getElementById(id) : 'script[data-composa-product]');
  }

  function selectedVariantId() {
    var input = d.querySelector('form[action*="/cart/add"] [name="id"]');
    if (input && input.value) return input.value;
    var m = location.search.match(/[?&]variant=(\d+)/);
    return m ? m[1] : null;
  }

  function open(el, mode) {
    var key = el.getAttribute('data-configurator-key');
    var config = configFor(key);
    if (!config) return;
    // Configurators set to "dedicated page" open there from every Customize button.
    if (config.display_mode === 'page' && mode !== 'page' && config.handle) {
      location.href = (settings.proxy || '/apps/composa') + '/configure/' + config.handle;
      return;
    }
    el.setAttribute('aria-busy', 'true');
    loadUI().then(function (UI) {
      el.removeAttribute('aria-busy');
      UI.open({
        config: config,
        product: productFor(el),
        variantId: el.getAttribute('data-variant-id') || selectedVariantId(),
        settings: settings,
        mount: mode === 'page' ? el : null,
        locale: (d.documentElement.lang || 'en').slice(0, 2),
        currency: (w.Shopify && w.Shopify.currency) || null,
        loadStartedAt: t0,
      });
    }).catch(function () {
      el.removeAttribute('aria-busy');
      // If the UI can't load, fall back to the dedicated page.
      if (config.handle) location.href = (settings.proxy || '/apps/composa') + '/configure/' + config.handle;
    });
  }

  // Product page buttons (app block) and any element with data-composa-open.
  d.addEventListener('click', function (e) {
    var el = e.target.closest && e.target.closest('[data-composa-open]');
    if (!el) return;
    e.preventDefault();
    open(el, 'popup');
  });

  // App embed without the block: put the button next to the theme's Add to cart.
  function placeAutoButton() {
    var tpl = d.querySelector('template[data-composa-auto]');
    if (!tpl || d.querySelector('[data-composa-open]')) return;
    var form = d.querySelector('product-form form[action*="/cart/add"], form[action*="/cart/add"]');
    var anchor = form && (form.querySelector('[type="submit"]') || form.lastElementChild);
    if (!anchor) return;
    var node = tpl.content.firstElementChild.cloneNode(true);
    anchor.parentNode.insertBefore(node, tpl.getAttribute('data-position') === 'below_add_to_cart' ? anchor.nextSibling : anchor);
  }

  // Hide the theme's Add to cart where the block asks to replace it.
  function hideAddToCart() {
    if (!d.querySelector('[data-composa-open][data-replace-atc="true"]')) return;
    var btns = d.querySelectorAll('form[action*="/cart/add"] [type="submit"], form[action*="/cart/add"] .shopify-payment-button');
    for (var i = 0; i < btns.length; i++) btns[i].style.display = 'none';
  }

  // Collection cards: add a "Customize" link to products that have a configurator.
  function decorateCards() {
    var map = readJSON('[data-composa-cards]');
    if (!map) return;
    var links = d.querySelectorAll('a[href*="/products/"]');
    for (var i = 0; i < links.length; i++) {
      var a = links[i];
      var m = a.getAttribute('href').match(/\/products\/([^/?#]+)/);
      if (!m || !map[m[1]] || a.closest('[data-composa-card]')) continue;
      var card = a.closest('li, .card, .product-card, .grid__item') || a.parentNode;
      if (!card || card.querySelector('.composa-card-link')) continue;
      card.setAttribute('data-composa-card', '');
      var link = d.createElement('a');
      link.className = 'composa-card-link';
      link.href = '/products/' + m[1] + '?composa=open';
      link.textContent = map[m[1]];
      card.appendChild(link);
    }
  }

  // Dedicated page (/apps/composa/configure/{handle}) and ?composa=open deep links.
  function autoOpen() {
    var page = d.querySelector('[data-composa-page]');
    if (page) return open(page, 'page');
    if (/[?&]composa=open/.test(location.search)) {
      var btn = d.querySelector('[data-composa-open]');
      if (btn) open(btn, 'popup');
    }
  }

  // Cart: themes that don't render line properties well still show the configured image and summary.
  function enhanceCart() {
    var rows = d.querySelectorAll('[data-composa-cart-line]');
    if (rows.length) return;
    if (!d.querySelector('cart-items, cart-drawer, #CartDrawer, form[action="/cart"]')) return;
    fetch('/cart.js', { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (cart) {
      cart.items.forEach(function (item, index) {
        var p = item.properties || {};
        if (!p._config_id) return;
        var cell = d.querySelector('[data-cart-item-key="' + item.key + '"], #CartItem-' + (index + 1) + ', #CartDrawer-Item-' + (index + 1));
        if (!cell || cell.querySelector('.composa-cart-summary')) return;
        var shown = cell.textContent || '';
        var visible = Object.keys(p).filter(function (k) { return k.charAt(0) !== '_' && shown.indexOf(p[k]) === -1; });
        if (p._img) {
          var img = cell.querySelector('img');
          if (img && img.src.indexOf('/apps/') === -1) { img.src = p._img; img.removeAttribute('srcset'); }
        }
        if (!visible.length) return;
        var box = d.createElement('dl');
        box.className = 'composa-cart-summary';
        visible.forEach(function (k) {
          var dt = d.createElement('dt'); dt.textContent = k;
          var dd = d.createElement('dd'); dd.textContent = p[k];
          box.appendChild(dt); box.appendChild(dd);
        });
        (cell.querySelector('.cart-item__details, .product-option, td') || cell).appendChild(box);
      });
    }).catch(function () {});
  }

  function boot() {
    placeAutoButton();
    hideAddToCart();
    decorateCards();
    autoOpen();
    enhanceCart();
  }
  d.readyState === 'loading' ? d.addEventListener('DOMContentLoaded', boot) : boot();
  d.addEventListener('cart:updated', enhanceCart);
  d.addEventListener('shopify:section:load', boot);
  w.Composa = { open: function (el) { open(el, 'popup'); }, refreshCart: enhanceCart };
})();
