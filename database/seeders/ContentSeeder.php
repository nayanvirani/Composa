<?php

namespace Database\Seeders;

use App\Models\ChangelogEntry;
use App\Models\DocArticle;
use App\Models\DocCategory;
use App\Models\Faq;
use App\Models\NavigationMenu;
use App\Models\Page;
use App\Models\Post;
use App\Models\SiteSetting;
use App\Models\StatusComponent;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;

/**
 * Default website content. Everything here is created only when missing, so edits made in super
 * admin are never overwritten by a deploy.
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->menus();
        $this->pages();
        $this->retireDpa();
        $this->faqs();
        $this->docs();
        $this->journal();
        $this->changelog();
        $this->status();
        $this->call(EmailTemplateSeeder::class);

        // Placeholder so the section has something to preview; stays a draft until a real quote replaces it.
        Testimonial::query()->firstOrCreate(['name' => 'Replace with a real merchant'], ['quote' => 'Add quotes from real merchants in super admin, then publish them.', 'store' => 'Store name', 'status' => 'draft']);
    }

    private function settings(): void
    {
        $defaults = [
            'launch' => [
                'coming_soon' => true,
                'title' => 'Something made-to-order is *on the drawing board.*',
                'intro' => 'Composa is a product configurator for Shopify: shoppers build custom products step by step, you get one cart line, a signed price and a spec your workshop can read. We’re finishing the last sheets before the Shopify App Store listing.',
                'badge' => 'LAUNCHING ON THE SHOPIFY APP STORE',
                'date_label' => 'Soon',
            ],
            'brand' => ['name' => 'Composa', 'tagline' => 'Step-by-step product configurators for Shopify, priced to the cent.', 'strip' => 'product configurator for Shopify'],
            'contact' => ['support_email' => 'support@composa.app', 'sales_email' => 'hello@composa.app', 'privacy_email' => 'privacy@composa.app', 'response_time' => 'Replies within one business day'],
            'links' => ['app_store' => 'https://apps.shopify.com/composa', 'install' => 'https://apps.shopify.com/composa', 'login' => 'https://admin.shopify.com', 'status' => '/status', 'strip_label' => 'Beta open → apply', 'strip_url' => '/early-access'],
            'seo' => ['title_suffix' => ' — Composa', 'description' => 'Composa turns any Shopify product into a guided configurator: variants, options, conditional rules and exact pricing, verified at checkout.'],
            'analytics' => ['cookie_banner' => false],
            'company' => ['legal_name' => 'Composa'],
            'footer' => [
                'drawn_for' => 'Made-to-order and customizable products on any Shopify store — jewelry, furniture, print, apparel, gifts, B2B.',
                'note' => 'Composa is not affiliated with Shopify Inc.',
                'meta' => [['k' => 'Rev', 'v' => '2026.11'], ['k' => 'Status', 'v' => 'Beta'], ['k' => 'Scale', 'v' => 'Any product'], ['k' => 'Locale', 'v' => 'English · USD'], ['k' => 'Built on', 'v' => 'Shopify'], ['k' => '©', 'v' => 'Composa']],
            ],
        ];
        // Missing keys are added; values set in super admin are kept.
        foreach ($defaults as $key => $value) {
            $setting = SiteSetting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
            $merged = ($setting->value ?? []) + $value;
            if ($merged != $setting->value) {
                $setting->forceFill(['value' => $merged])->save();
            }
        }
    }

    private function menus(): void
    {
        $menus = [
            'header' => ['Header', [['Product', '/'], ['Templates', '/templates'], ['Pricing', '/pricing'], ['Docs', '/docs'], ['Journal', '/journal']]],
            'footer_product' => ['Footer · Product', [['Features', '/features'], ['Templates', '/templates'], ['Pricing', '/pricing']]],
            'footer_resources' => ['Footer · Resources', [['Docs', '/docs'], ['Journal', '/journal'], ['Changelog', '/changelog'], ['Status', '/status']]],
            'footer_company' => ['Footer · Company', [['About', '/about'], ['Contact', '/contact'], ['Beta program', '/early-access']]],
            'footer_legal' => ['Footer · Legal', [['Privacy', '/legal/privacy'], ['Terms', '/legal/terms'], ['GDPR', '/legal/gdpr']]],
        ];
        foreach ($menus as $key => [$title, $items]) {
            $items = array_map(fn ($i) => ['label' => $i[0], 'url' => $i[1]], $items);
            $menu = NavigationMenu::query()->firstOrCreate(['key' => $key], ['title' => $title, 'items' => $items]);
            if ($menu->updated_at->equalTo($menu->created_at) && $menu->items !== $items) {
                // Keep updated_at = created_at so the menu still counts as untouched next deploy.
                $menu->timestamps = false;
                $menu->items = $items;
                $menu->save();
            }
        }
    }

    /** True when nobody on the team has edited the record since it was seeded. */
    private function untouched(Page $page): bool
    {
        return ! $page->revisions()->whereNotNull('user_id')->exists();
    }

    protected function page(string $slug, string $title, array $sections, ?string $description = null, string $template = 'sections'): void
    {
        $attributes = [
            'title' => $title,
            'template' => $template,
            'status' => 'published',
            'sections' => array_map(fn ($s) => ['type' => $s[0], 'data' => $s[1]], $sections),
            'seo' => $description ? ['description' => $description] : null,
        ];
        $page = Page::query()->where('slug', $slug)->where('locale', 'en')->first();
        if (! $page) {
            Page::query()->create(['slug' => $slug, 'locale' => 'en'] + $attributes);
        } elseif ($this->untouched($page) && $page->sections !== $attributes['sections']) {
            $page->fill($attributes)->save();
        }
    }

    /** The DPA now lives in the GDPR document. */
    private function retireDpa(): void
    {
        $dpa = Page::query()->where('slug', 'legal/dpa')->where('locale', 'en')->first();
        if ($dpa && $this->untouched($dpa)) {
            $dpa->delete();
        }
    }

    protected function pages(): void
    {
        $install = 'https://apps.shopify.com/composa';

        $this->page('home', 'Home', [
            ['composer', [
                'title' => "Compose anything.\n*Sell it as one.*",
                'intro' => 'A product configurator for Shopify. Shoppers build it step by step; you get one cart line, a signed price and a spec your workshop can read.',
                'primary_label' => 'Install on Shopify', 'primary_url' => $install,
                'secondary_label' => 'See the patterns', 'secondary_url' => '/templates',
            ]],
            ['ledger', [
                'before_label' => '§1 Without Composa', 'after_label' => '§2 With Composa',
                'before' => [['text' => 'Hitting Shopify’s variant limits on every custom product'], ['text' => 'Fee products cluttering the cart, catalog and reports'], ['text' => 'Workshops decoding cart notes to find out what to make']],
                'after' => [
                    ['title' => 'Deep step flows.', 'text' => 'Nested and repeatable steps, conditional rules, any input type.'],
                    ['title' => 'One clean cart line.', 'text' => 'Cart Transform prices it exactly. Nothing hidden in your store.'],
                    ['title' => 'A spec on every order.', 'text' => 'Summary, configured image and a production PDF.'],
                ],
            ]],
            ['routing', [
                'title' => 'From template to *first order* in four moves.', 'meta' => 'ROUTING SHEET · TARGET < 30 MIN', 'anchor' => 'how',
                'items' => [
                    ['title' => 'Pick a pattern', 'body' => 'Ring, necklace, furniture, apparel, print, gift box — or blank.'],
                    ['title' => 'Compose the steps', 'body' => 'Map Shopify variants, add custom fields, rules and prices.'],
                    ['title' => 'Assign & switch on', 'body' => 'Products, collections, tags or vendors. One-click app embed.'],
                    ['title' => 'Sell & produce', 'body' => 'One cart line at checkout. Spec, PDF and analytics after.'],
                ],
            ]],
            ['bom', [
                'title' => 'Bill of materials', 'intro' => 'Every part that ships with Composa, and the plan it starts on.',
                'rows' => [
                    ['no' => 'C-01', 'component' => 'Step builder', 'body' => 'Drag steps and options; nest sub-flows; repeat “add another item”.', 'plan' => 'Free'],
                    ['no' => 'C-02', 'component' => 'Shopify variant mapping', 'body' => 'Use existing options like Color, Size or Carat as steps — exact variant and stock go to cart.', 'plan' => 'Free'],
                    ['no' => 'C-03', 'component' => '11 input types', 'body' => 'Swatches, image tiles, buttons, dropdown, text, number, dimensions, file upload, product picker…', 'plan' => 'Free'],
                    ['no' => 'C-04', 'component' => 'Visual rules', 'body' => 'IF/THEN show, hide, disable, require, set default, set price. Nested on Growth.', 'plan' => 'Free'],
                    ['no' => 'C-05', 'component' => 'Pricing engine', 'body' => 'Fixed, %, per character, per unit, tiered, lookup table, formula.', 'plan' => 'Free → Pro'],
                    ['no' => 'C-06', 'component' => 'Layered live preview', 'body' => 'Images swap per choice; text overlays for engraving and print.', 'plan' => 'Free'],
                    ['no' => 'C-07', 'component' => 'One-line checkout', 'body' => 'Signed price, configured title and image; no hidden products.', 'plan' => 'Free'],
                    ['no' => 'C-08', 'component' => 'Production spec & PDF', 'body' => 'Spec view, PDF, order tags and production status.', 'plan' => 'Starter'],
                    ['no' => 'C-09', 'component' => 'Funnel analytics', 'body' => 'Opens, step drop-off, add-to-carts, revenue per configurator.', 'plan' => 'Starter'],
                    ['no' => 'C-10', 'component' => 'Global by default', 'body' => 'Shopify Markets currencies, store languages, regional units and size systems.', 'plan' => 'Free'],
                ],
            ]],
            ['formula', [
                'eyebrow' => 'Pricing engine', 'title' => "One formula,\nrun twice.",
                'body' => 'The same rules run in the shopper’s browser for instant prices and on our server to sign the final one. Checkout always matches what the shopper saw.',
                'formula' => 'P = (B + Σoᵢ) × (1 + m) + f(x)', 'badge' => 'BROWSER = SERVER ✓',
                'terms' => [['symbol' => 'B', 'text' => 'Base price — the Shopify variant'], ['symbol' => 'o', 'text' => 'Option add-ons from price rules'], ['symbol' => 'm', 'text' => 'Percentage markup, e.g. rush'], ['symbol' => 'f', 'text' => 'Your formula — min, max, round, ceil, if']],
            ]],
            ['patterns', ['title' => 'Start from a pattern', 'link_label' => 'All templates →', 'link_url' => '/templates', 'count' => 6]],
            ['spec_card', [
                'eyebrow' => 'For the workshop', 'title' => 'Orders that read like a drawing, not a puzzle.',
                'body' => 'Every configured order arrives with a clean spec, a production PDF, order tags and a status your team can move along.',
                'card_title' => 'PRODUCTION SPEC · ORDER #1042', 'badge' => 'IN PRODUCTION',
                'rows' => [['k' => 'PRODUCT', 'v' => 'Solitaire Ring — Custom'], ['k' => 'SHAPE', 'v' => 'Oval'], ['k' => 'METAL', 'v' => '18K Rose Gold'], ['k' => 'STONE', 'v' => '1.0 ct · VS1 · G'], ['k' => 'ENGRAVING', 'v' => '“Forever” · script'], ['k' => 'SIGNED PRICE', 'v' => '$2,100.50 USD']],
                'footer_left' => 'Checked by ______', 'footer_right' => 'Download PDF ↓',
            ]],
            ['beta_inline', ['title' => "Order form:\n*beta access*", 'body' => 'We’re onboarding a small group of made-to-order stores. Beta stores get hands-on setup from our team and a direct say in what we build next.']],
            ['notes', ['title' => 'Notes', 'placement' => 'home', 'prefix' => 'N']],
            ['cta_band', ['title' => "Your first composition\nships today.", 'style' => 'orange', 'size' => 'xl', 'button_label' => 'Install on Shopify', 'button_url' => $install]],
        ], 'Composa turns any Shopify product into a guided configurator with variants, rules and exact, checkout-verified pricing.');

        $this->page('templates', 'Templates', [
            ['page_hero', ['title' => 'Pattern *library*', 'intro' => 'Each pattern is a working configurator. Install it, swap in your options and prices, publish.', 'layout' => 'split', 'size' => 'l', 'paper' => false]],
            ['template_library', []],
            ['cta_band', ['title' => 'Nothing here fits? Start from a blank sheet.', 'style' => 'orange', 'button_label' => 'See plans', 'button_url' => '/pricing']],
        ], 'Ring builders, apparel, furniture, print, gift boxes and B2B — working configurators ready to edit.');

        $this->page('pricing', 'Pricing', [
            ['pricing', [
                'title' => 'Specify your *requirements.*',
                'intro' => 'Tell us what you’re building and we’ll mark the plan that fits. 14-day free trial, billed through Shopify, no per-order fees.',
                'footnote' => 'Prices in USD · Shopify Plus stores get exact-price Update mode on any paid plan · live configurators never stop at a limit',
                'table_title' => 'Full specification',
            ]],
            ['notes', ['title' => 'Billing notes', 'placement' => 'pricing', 'prefix' => 'B']],
        ], 'Composa plans and limits. Free on development stores, 14-day trial on paid plans, billed through Shopify.');

        $this->page('features', 'Features', [
            ['page_hero', ['sheet' => 'Sheet 06 — Detail drawings', 'title' => 'Every part, *drawn to scale.*', 'layout' => 'stacked', 'size' => 'xl', 'paper' => true]],
            ['chapters', ['index' => true, 'items' => [
                ['letter' => 'A', 'anchor' => 'builder', 'title' => 'Step builder', 'plan' => 'All plans', 'body' => 'A three-pane editor — steps tree, step and option editor, live preview. Drag to reorder, duplicate, version and publish.', 'spec' => "Up to 50 steps per configurator on Pro\nNested sub-flows and repeatable steps (Growth+)\nDraft and published versions; restore any version\nPopup, drawer or dedicated page", 'doc_url' => '/docs/first-configurator'],
                ['letter' => 'B', 'anchor' => 'variants', 'title' => 'Shopify variant mapping', 'plan' => 'All plans', 'body' => 'Already sell Color, Size or Carat as variants? Each option becomes a step and the exact variant goes to cart with its own price and stock.', 'spec' => "Order options and choose who picks them\nSold-out and missing combinations disabled\nMix variant steps with custom fields\nLive sync when products change", 'doc_url' => '/docs/variant-mapping'],
                ['letter' => 'C', 'anchor' => 'inputs', 'title' => 'Input types', 'plan' => 'All plans', 'body' => 'Eleven input types cover everything from colour swatches to dimensions and file uploads.', 'spec' => "Swatches, image tiles, buttons, dropdown, radio, checkbox\nText with live preview, number, W × H\nFile upload (Starter+)\nShopify product picker for real SKUs", 'doc_url' => '/docs/first-configurator'],
                ['letter' => 'D', 'anchor' => 'rules', 'title' => 'Visual rules', 'plan' => 'All plans', 'body' => 'IF/THEN rules without code. Conditions combine with AND/OR and nest on Growth and above.', 'spec' => "Show, hide, disable, require\nSet default, add price, set preview layer\nSet a Shopify variant option from a custom answer\nRe-validated on the server", 'doc_url' => '/docs/conditional-rules'],
                ['letter' => 'E', 'anchor' => 'pricing', 'title' => 'Pricing engine', 'plan' => 'Free → Pro', 'body' => 'Seven price rule types, run in the browser for speed and on the server to sign the price.', 'spec' => "Fixed and percent (Free)\nPer character (Starter)\nPer unit, tiered, lookup table (Growth)\nFormula with min, max, round, ceil, if (Pro)", 'doc_url' => '/docs/pricing-types'],
                ['letter' => 'F', 'anchor' => 'preview', 'title' => 'Live preview', 'plan' => 'All plans', 'body' => 'Layered images swap with every choice; text overlays show engraving or print as the shopper types.', 'spec' => "Layers driven by any field\nImages served from Shopify’s CDN\nComposite image on cart line and order\nVariant images used automatically", 'doc_url' => ''],
                ['letter' => 'G', 'anchor' => 'checkout', 'title' => 'One-line checkout', 'plan' => 'All plans', 'body' => 'Every configuration reaches the cart as one line, priced by Cart Transform. Composa never creates hidden or fee products.', 'spec' => "HMAC-signed price, verified at checkout\nUpdate mode on Shopify Plus, Expand elsewhere\nLinked SKUs track inventory\nTampered prices fall back to the normal price", 'doc_url' => '/docs/signed-prices'],
                ['letter' => 'H', 'anchor' => 'production', 'title' => 'Production spec', 'plan' => 'Starter+', 'body' => 'Orders arrive with a clean spec, configured image, production PDF and a status your workshop can update.', 'spec' => "Spec view per order line\nPDF with your branding (Pro)\nOrder tags and production status\nEmail to your workshop", 'doc_url' => ''],
                ['letter' => 'I', 'anchor' => 'analytics', 'title' => 'Funnel analytics', 'plan' => 'Starter+', 'body' => 'See where shoppers stop, which options sell and what each configurator earns.', 'spec' => "Opens, step drop-off, add-to-cart rate\nMost chosen options per field\nRevenue and average order per configurator\nCSV export (Pro)", 'doc_url' => ''],
                ['letter' => 'J', 'anchor' => 'global', 'title' => 'Global and fast', 'plan' => 'All plans', 'body' => 'Built for every market from day one, and light enough not to slow your product pages.', 'spec' => "Shopify Markets currencies\nEvery store language\nRegional units and size systems\nStorefront script loads on click, under 60 KB", 'doc_url' => ''],
            ]]],
            ['input_types', ['title' => 'Input types, all eleven', 'label' => 'Detail C — schedule', 'extra_title' => 'Nest & repeat', 'extra_body' => 'Any step can open a sub-flow or repeat.', 'items' => [
                ['glyph' => '●', 'name' => 'Swatches', 'desc' => 'Colours, metals, fabrics'], ['glyph' => '▦', 'name' => 'Image tiles', 'desc' => 'Big visual choices'], ['glyph' => '▭', 'name' => 'Buttons', 'desc' => 'Short text choices'],
                ['glyph' => '▾', 'name' => 'Dropdown', 'desc' => 'Long lists'], ['glyph' => '◉', 'name' => 'Radio', 'desc' => 'With descriptions'], ['glyph' => '☑', 'name' => 'Checkbox', 'desc' => 'Pick several add-ons'],
                ['glyph' => 'Aa', 'name' => 'Text', 'desc' => 'Names, engraving, messages'], ['glyph' => '12', 'name' => 'Number', 'desc' => 'Quantities, weights'], ['glyph' => 'W×H', 'name' => 'Dimensions', 'desc' => 'Priced per unit'],
                ['glyph' => '↑', 'name' => 'File upload', 'desc' => 'Artwork, photos, logos'], ['glyph' => '⌘', 'name' => 'Product picker', 'desc' => 'Real stocked SKUs'],
            ]]],
            ['cta_band', ['title' => 'See every part working together.', 'style' => 'dark', 'secondary_label' => 'Templates', 'secondary_url' => '/templates', 'button_label' => 'Install on Shopify', 'button_url' => $install]],
        ], 'Steps, variant mapping, rules, seven pricing types, layered previews and a signed checkout.');

        $this->page('about', 'About', [
            ['page_hero', ['sheet' => 'Sheet 08 — About', 'layout' => 'statement', 'paper' => true, 'title' => 'Most things worth buying are *made for someone.* Selling them online shouldn’t mean squeezing them into a dropdown.', 'intro' => 'Composa exists for the jewellers, furniture makers, printers and small workshops who make things to order. We build the part between the shopper’s idea and the workshop’s bench: a configurator that asks good questions, prices honestly and hands over a spec that makes sense.']],
            ['tolerances', ['title' => 'Tolerances', 'label' => 'Rules we don’t bend', 'items' => [
                ['code' => 'T1', 'title' => 'No hidden products. Ever.', 'body' => 'We price the cart line directly. Your catalog, inventory, reports and marketing feeds stay exactly as you built them.'],
                ['code' => 'T2', 'title' => 'The price the shopper sees is the price they pay.', 'body' => 'Every price is calculated twice and signed. If anything is tampered with, the line falls back to the normal price instead of selling below cost.'],
                ['code' => 'T3', 'title' => 'Limits are honest, and never break a sale.', 'body' => 'Plans have clear limits. Hitting one pauses new work — it never takes a live configurator offline.'],
                ['code' => 'T4', 'title' => 'Global from the first sheet.', 'body' => 'Currencies, languages, units and size systems are part of the product, not an add-on.'],
                ['code' => 'T5', 'title' => 'The workshop is a customer too.', 'body' => 'An order isn’t finished at checkout. We design the spec, PDF and status for the people who make the thing.'],
            ]]],
            ['drawn_for', ['title' => 'Drawn for', 'items' => [
                ['code' => 'D-01', 'title' => 'Jewelry', 'desc' => 'Rings, pendants, names'], ['code' => 'D-02', 'title' => 'Furniture', 'desc' => 'Sizes, fabrics, finishes'], ['code' => 'D-03', 'title' => 'Print', 'desc' => 'Uploads, papers, frames'],
                ['code' => 'D-04', 'title' => 'Apparel', 'desc' => 'Variants and embroidery'], ['code' => 'D-05', 'title' => 'Gifts', 'desc' => 'Boxes and messages'], ['code' => 'D-06', 'title' => 'B2B', 'desc' => 'Quotes and quantity tiers'],
            ]]],
            ['cta_band', ['title' => 'Make something to order? Let’s talk.', 'style' => 'orange', 'button_label' => 'Join the beta', 'button_url' => '/early-access']],
        ], 'Why we built Composa and the rules we don’t bend.');

        $this->page('contact', 'Contact', [
            ['contact', [
                'sheet' => 'Sheet 09 — Contact', 'title' => 'Send us a *request.*',
                'intro' => 'Pick what it’s about and we’ll route it to the right person. Merchants already using Composa can also reach support from inside the app.',
                'success' => 'Thanks — it’s on the board.',
                'channels' => [['k' => 'Support', 'v' => 'support@composa.app'], ['k' => 'Sales', 'v' => 'hello@composa.app'], ['k' => 'In the app', 'v' => 'Help › Contact support (fastest for merchants)'], ['k' => 'Status', 'v' => 'Live system status on the Status page']],
            ]],
        ], 'Contact the Composa team.');

        $this->page('early-access', 'Beta program', [
            ['beta', [
                'sheet' => 'Sheet 10 — Beta program', 'title' => 'Help us draw *version one.*',
                'intro' => 'We’re working closely with a small group of made-to-order stores before launch. You get hands-on setup; we get honest feedback from people who make things for a living.',
                'success' => 'You’re on the drawing board.',
                'timeline' => [['title' => 'Apply', 'desc' => 'three short steps, about two minutes'], ['title' => 'Call', 'desc' => 'we learn how you take custom orders today'], ['title' => 'Set up', 'desc' => 'we build your first configurator with you'], ['title' => 'Shape it', 'desc' => 'regular check-ins until launch']],
            ]],
            ['gets', ['title' => 'What beta stores get', 'items' => [
                ['code' => 'G1', 'title' => 'Hands-on setup', 'body' => 'Our team builds your first configurator with you, including variant mapping and pricing rules.'],
                ['code' => 'G2', 'title' => 'A direct line', 'body' => 'Priority support and a shared channel with the people building Composa.'],
                ['code' => 'G3', 'title' => 'A say in the roadmap', 'body' => 'Your feedback decides what we build next. Pricing is the same published Shopify plans as everyone else.'],
            ]]],
        ], 'Apply to the Composa beta program.');

        $this->page('changelog', 'Changelog', [
            ['page_hero', ['sheet' => 'Sheet 07 — Revision history', 'title' => 'Revisions', 'layout' => 'split', 'size' => 'xl', 'paper' => false, 'secondary_label' => 'Email me updates', 'secondary_url' => '#updates']],
            ['changelog', ['count' => 30]],
            ['newsletter_band', ['title' => 'Release notes by email.', 'source' => 'changelog']],
        ], 'New features, improvements and fixes in Composa.');

        $this->page('status', 'Status', [
            ['status', ['sheet' => 'Sheet 12 — System status']],
        ], 'Current status of Composa services.');

        $legal = [
            'privacy' => ['Privacy policy', "## What we collect\n> Only what we need to run your configurators and orders.\n\nStore details from Shopify (name, domain, contact email, plan), the configurators you create, and the answers and files shoppers submit so configured orders can be made. Checkout and payment details stay with Shopify.\n\n## How we use it\n> To make Composa work. We don’t sell data.\n\nTo provide the service, calculate and verify prices, generate production documents, send service emails and provide support.\n\n## Where it’s stored\n> Your data is kept separate from other shops, and files are private.\n\nData is stored per shop in managed databases and private file storage. Shopper files are served through short-lived signed links.\n\n## How long we keep it\n> Unused data is cleaned up automatically.\n\nConfigurations that never become orders expire after 30 days. Uploaded files that are not ordered are deleted after 30 days. All shop data is deleted 48 hours after uninstall.\n\n## Sub-processors\n> A short list of vendors help us run the service.\n\nHosting and storage, error monitoring and email delivery providers. The current list is available on request.\n\n## Your rights\n> Ask and we’ll show, fix or delete your data.\n\nAccess, correction and deletion requests are handled through Shopify’s data request flow or by contacting privacy@composa.app."],
            'terms' => ['Terms of service', "## The service\n> You get the features of your plan.\n\nComposa provides a product configurator app for Shopify stores under the plan you select in the app.\n\n## Plans and billing\n> Billed by Shopify. No per-order fees.\n\nFees are billed through Shopify after any free trial. Plan limits apply as published on the pricing page. Upgrades, downgrades and cancellations are made in the app and handled by Shopify.\n\n## Plan limits\n> Limits pause new work; they never stop a live sale.\n\nWhen you reach a limit, new items of that kind pause until you upgrade. After a downgrade you have 7 days before extra configurators return to draft.\n\n## Your content\n> What you build is yours.\n\nYou own your configurators, images and product data. You grant us the licence needed to host and display them.\n\n## Acceptable use\n> Play fair; don’t tamper with prices.\n\nDon’t misuse the service, attempt to bypass price signing or plan limits, or use it for products that break Shopify’s Acceptable Use Policy.\n\n## Ending the service\n> Leave anytime; we clean up after you.\n\nYou can uninstall at any time. Shop data is removed 48 hours after uninstall."],
            'gdpr' => ['GDPR & data requests', "## Shopify compliance webhooks\n> We answer every data request Shopify sends us.\n\nWe process the customers/data_request, customers/redact and shop/redact webhooks for every installed store.\n\n## Customer data requests\n> Shoppers can get a copy of their configurations.\n\nRequested customer data, including configurations and uploads, is compiled and provided to the merchant within 30 days.\n\n## Redaction\n> When asked to delete, we delete.\n\nCustomer and shop redaction requests remove personal data from configurations, orders and uploads.\n\n## Uninstall cleanup\n> Uninstall = gone in 48 hours.\n\nShop data is deleted 48 hours after uninstall; abandoned uploads are deleted after 30 days.\n\n## Data processing agreement\n> Need a DPA? Just ask.\n\nThe merchant is the controller of shopper data and Composa is the processor. A DPA is available on request from privacy@composa.app."],
        ];
        foreach ($legal as $slug => [$title, $body]) {
            $this->page('legal/'.$slug, $title, [['legal', ['body' => $body]]], null, 'legal');
        }
    }

    private function faqs(): void
    {
        $faqs = [
            ['Does it work with my theme?', 'Yes — any Online Store 2.0 theme, as an app block and app embed. No code edits.', ['home', 'help']],
            ['My products already have variants. Do I start again?', 'No. Composa maps your Shopify options (Color, Size, Carat…) to steps, and the exact variant goes to cart with its stock.', ['home', 'help', 'app']],
            ['Will it add fee products to my store?', 'Never. The configured item is priced by Cart Transform on its own line.', ['home']],
            ['How many configurators do I get?', 'From 1 on Free to 50 on Pro, with step, option, rule and product limits per plan. See [Pricing](/pricing).', ['home']],
            ['Other currencies and languages?', 'Prices convert with your Shopify Markets rates; labels translate into every store language.', ['home']],
            ['Can shoppers upload files?', 'Yes, on paid plans. Uploads are stored privately and linked to the order.', ['home']],
            ['How does the free trial work?', 'Every paid plan starts with 14 days free. After that you’re billed on your Shopify invoice.', ['pricing', 'app']],
            ['What happens when I reach a limit?', 'We email you at 80%. At 100%, new publishes or assignments of that kind pause. Live configurators keep selling.', ['pricing', 'app']],
            ['Can I change plans later?', 'Any time, from Plan & usage in the app. Downgrades get a 7-day grace period.', ['pricing']],
            ['Do you charge per order?', 'No. No per-order or transaction fees.', ['pricing']],
            ['What if I uninstall?', 'Shop data is removed 48 hours after uninstall, as Shopify’s GDPR rules require.', ['pricing']],
        ];
        $questions = array_column($faqs, 0);
        // Seeded questions nobody edited and that are no longer in the list are retired.
        Faq::query()->where('locale', 'en')->whereNotIn('question', $questions)->whereColumn('updated_at', 'created_at')->delete();
        foreach ($faqs as $i => [$q, $a, $placements]) {
            Faq::query()->firstOrCreate(['question' => $q, 'locale' => 'en'], ['answer' => $a, 'placements' => $placements, 'sort' => $i, 'status' => 'published']);
        }
    }

    private function docs(): void
    {
        $docs = [
            'getting-started' => ['Getting started', 'Install, enable and publish your first configurator.', [
                ['install-composa', 'Install Composa and enable the app embed', true, "## 1. Install\n\nInstall Composa from the Shopify App Store and approve the permissions.\n\n## 2. Enable the app embed\n\nIn the app, open **Setup** and click **Enable in theme**. The theme editor opens with Composa selected — click **Save**.\n\n## 3. Check it\n\nThe setup checklist turns green when the embed is active on your live theme."],
                ['first-configurator', 'Build your first configurator from a template', true, "## Pick a template\n\nGo to **Configurators → New** and choose a template close to your product. Templates that need a higher plan are marked.\n\n## Choose the base product\n\nPick the Shopify product shoppers will buy. Its options become your first steps.\n\n## Preview\n\nUse the preview on the right to try every combination before you publish."],
                ['assign-products', 'Assign a configurator to products, collections or tags', false, "## Assignment rules\n\nOpen the **Assign** tab. Add products one by one, or whole collections, tags or vendors.\n\n## Priority\n\nWhen a product matches several configurators, the rule with the lowest priority number wins."],
            ]],
            'variants' => ['Variants & options', 'Use the options your products already have.', [
                ['variant-mapping', 'How variant mapping works', true, "## From options to steps\n\nEach Shopify option (Color, Size, Carat…) can become a step. Choose how it looks — swatches, buttons, a size grid or a dropdown.\n\n## Sold-out combinations\n\nComposa checks availability in order. If 18k White in size 9 is sold out, size 9 is disabled once 18k White is picked.\n\n## Mixed mode\n\nKeep your variants for the main choices and add custom steps (engraving, add-ons) on top."],
                ['add-on-products', 'Sell add-ons as separate products', false, "Link an option to another product's variant to track its inventory. In Expand mode the add-on appears as its own line inside the bundle at checkout."],
            ]],
            'pricing-rules' => ['Pricing & rules', 'Price every option exactly.', [
                ['pricing-types', 'The seven pricing types', true, "## Fixed and percent\n\nAdd a flat amount or a percentage.\n\n## Per character\n\nCharge by text length — choose whether spaces count.\n\n## Per unit and area\n\nMultiply by a number or by width × height, with an optional divisor.\n\n## Tiers\n\nQuantity breaks or value bands.\n\n## Tables\n\nLook up a price from two answers.\n\n## Formulas\n\nUse variables, `min`, `max`, `round`, `ceil`, `floor`, `abs` and `if`. Dividing by zero returns 0."],
                ['conditional-rules', 'Conditional rules', false, 'Rules run in order. Each has conditions (all or any) and actions: show or hide a step, field or option; require; set a default; add a price; switch a preview layer.'],
            ]],
            'checkout' => ['Cart & checkout', 'How prices reach checkout safely.', [
                ['signed-prices', 'Signed prices and the Cart Transform', false, "When a shopper adds to cart, Composa recalculates the price on its server and signs it. At checkout, the Composa Cart Transform checks the signature before setting the price.\n\n- **Update mode** (Shopify Plus and development stores) changes the line price.\n- **Expand mode** (all other plans) expands the line into its own variant at the configured price.\n\nIf a store already uses another app's cart transform, Composa switches to a discount-based fallback."],
                ['cart-display', 'How configured items look in the cart', false, 'The cart shows the configured image and a short summary. Hidden properties starting with an underscore carry the signature and are not shown to shoppers.'],
            ]],
        ];
        $sort = 0;
        foreach ($docs as $slug => [$title, $description, $articles]) {
            $category = DocCategory::query()->firstOrCreate(['slug' => $slug], ['title' => $title, 'description' => $description, 'sort' => $sort++]);
            foreach ($articles as $i => [$aSlug, $aTitle, $start, $body]) {
                DocArticle::query()->firstOrCreate(['slug' => $aSlug], [
                    'doc_category_id' => $category->id, 'title' => $aTitle, 'body' => $body, 'start_here' => $start,
                    'read_minutes' => max(1, (int) ceil(str_word_count($body) / 200)), 'sort' => $i, 'status' => 'published',
                ]);
            }
        }
    }

    private function journal(): void
    {
        $posts = [
            ['variants-vs-options', 'Variants or options? How to model a custom product on Shopify', 'Guides', 'Shopify allows 3 options and 2,048 variants per product. Here is how to decide what belongs in variants and what belongs in a configurator.', "Shopify products can have up to three options and 2,048 variants. That is enough for most catalogues — and not enough for most custom products.\n\n## Put inventory in variants\n\nIf it changes what you stock or what SKU you pick, it is a variant: metal, size, frame colour.\n\n## Put personalisation in steps\n\nEngraving, uploaded artwork, gift messages and add-ons do not need their own SKU. They belong in configurator steps with their own prices.\n\n## Mixing both\n\nComposa reads your variants as the first steps and adds your custom steps after them, so a shopper sees one flow."],
            ['prices-you-can-trust', 'Why configured prices must be verified at checkout', 'Product', 'Cart attributes can be edited by anyone with a browser console. Here is how Composa keeps prices honest.', "Many option apps pass the price to checkout in a way shoppers can edit. Composa signs every configured price on its server and checks the signature in a Shopify Cart Transform.\n\n## What happens if someone edits the cart\n\nThe signature no longer matches, so the line is charged at the product's normal price. You never sell below your configured price."],
        ];
        foreach ($posts as $i => [$slug, $title, $tag, $excerpt, $body]) {
            Post::query()->firstOrCreate(['slug' => $slug], [
                'title' => $title, 'tag' => $tag, 'excerpt' => $excerpt, 'body' => $body, 'featured' => $i === 0,
                'read_minutes' => max(2, (int) ceil(str_word_count($body) / 200)), 'status' => 'published', 'published_at' => now()->subDays(7 * ($i + 1)),
            ]);
        }
    }

    private function changelog(): void
    {
        ChangelogEntry::query()->firstOrCreate(['version' => '1.0.0'], [
            'title' => 'Composa 1.0',
            'released_at' => now()->toDateString(),
            'status' => 'published',
            'items' => [
                ['kind' => 'added', 'text' => 'Configurator builder with steps, eleven field types and repeatable steps.'],
                ['kind' => 'added', 'text' => 'Variant mapping for products with up to three Shopify options.'],
                ['kind' => 'added', 'text' => 'Seven pricing types and conditional rules.'],
                ['kind' => 'added', 'text' => 'Signed checkout prices with Cart Transform (update and expand modes).'],
                ['kind' => 'added', 'text' => 'Production PDF, order specs and analytics.'],
            ],
        ]);
    }

    private function status(): void
    {
        $components = [
            ['Storefront configurator', 'Customize button and configurator on your store'],
            ['Pricing API', 'Price checks and add to cart through the App Proxy'],
            ['Checkout pricing', 'Cart Transform and discount fallback'],
            ['Merchant app', 'The Composa app inside Shopify admin'],
            ['File uploads', 'Shopper uploads and production files'],
            ['Webhooks & sync', 'Orders, products and billing updates from Shopify'],
        ];
        foreach ($components as $i => [$name, $description]) {
            StatusComponent::query()->firstOrCreate(['name' => $name], ['description' => $description, 'status' => 'operational', 'sort' => $i]);
        }
    }
}
