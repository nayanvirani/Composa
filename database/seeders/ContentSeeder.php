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
            'brand' => ['name' => 'Composa', 'tagline' => 'Step-by-step product configurators for Shopify, priced to the cent.'],
            'contact' => ['support_email' => 'support@composa.app', 'sales_email' => 'hello@composa.app', 'privacy_email' => 'privacy@composa.app', 'response_time' => 'Replies within one business day'],
            'links' => ['app_store' => 'https://apps.shopify.com/composa', 'install' => 'https://apps.shopify.com/composa', 'status' => '/status'],
            'seo' => ['title_suffix' => ' — Composa', 'description' => 'Composa turns any Shopify product into a guided configurator: variants, options, conditional rules and exact pricing, verified at checkout.'],
            'analytics' => ['cookie_banner' => false],
            'company' => ['legal_name' => 'Composa'],
            'footer' => ['note' => 'Composa is not affiliated with Shopify Inc.'],
        ];
        foreach ($defaults as $key => $value) {
            SiteSetting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    private function menus(): void
    {
        $menus = [
            'header' => ['Header', [['Features', '/features'], ['Templates', '/templates'], ['Pricing', '/pricing'], ['Docs', '/docs'], ['Journal', '/journal']]],
            'footer_product' => ['Footer · Product', [['Features', '/features'], ['Templates', '/templates'], ['Pricing', '/pricing'], ['Changelog', '/changelog'], ['Early access', '/early-access']]],
            'footer_resources' => ['Footer · Resources', [['Docs', '/docs'], ['Journal', '/journal'], ['Status', '/status']]],
            'footer_company' => ['Footer · Company', [['About', '/about'], ['Contact', '/contact']]],
            'footer_legal' => ['Footer · Legal', [['Privacy policy', '/legal/privacy'], ['Terms of service', '/legal/terms'], ['Data processing', '/legal/dpa']]],
        ];
        foreach ($menus as $key => [$title, $items]) {
            NavigationMenu::query()->firstOrCreate(['key' => $key], ['title' => $title, 'items' => array_map(fn ($i) => ['label' => $i[0], 'url' => $i[1]], $items)]);
        }
    }

    private function page(string $slug, string $title, array $sections, ?string $description = null, string $template = 'sections'): void
    {
        Page::query()->firstOrCreate(['slug' => $slug, 'locale' => 'en'], [
            'title' => $title,
            'template' => $template,
            'status' => 'published',
            'sections' => array_map(fn ($s) => ['type' => $s[0], 'data' => $s[1]], $sections),
            'seo' => $description ? ['description' => $description] : null,
        ]);
    }

    private function pages(): void
    {
        $cta = ['cta', ['title' => 'Your first configurator, *live this afternoon*.', 'body' => 'Free on development stores. 14-day trial on every paid plan, billed through Shopify.', 'button_label' => 'Install on Shopify', 'button_url' => 'https://apps.shopify.com/composa']];

        $this->page('home', 'Home', [
            ['hero', [
                'eyebrow' => 'Product configurator for Shopify',
                'title' => 'Every option. Every variant. *Priced to the cent.*',
                'subtitle' => 'Composa turns any product into a guided, step-by-step configurator — built on your existing Shopify variants, with rules, live prices and a checkout that can’t be tampered with.',
                'primary_label' => 'Install on Shopify', 'primary_url' => 'https://apps.shopify.com/composa',
                'secondary_label' => 'See the templates', 'secondary_url' => '/templates',
                'note' => 'Free on development stores · 14-day trial · Billed through Shopify',
            ]],
            ['spec_strip', ['items' => [
                ['value' => '11', 'label' => 'field types, from swatches to file uploads'],
                ['value' => '7', 'label' => 'ways to price an option'],
                ['value' => '<20 KB', 'label' => 'storefront script, loaded on first click'],
                ['value' => '0', 'label' => 'hidden products or variants created'],
            ]]],
            ['variant_demo', [
                'eyebrow' => 'Built on your variants',
                'title' => 'Already have Color, Size and Carat? *Keep them.*',
                'intro' => 'Composa reads your product’s options and turns each one into a step. Shoppers pick from your real variants, sold-out combinations are disabled, and the order goes through on the right SKU.',
                'options' => [['name' => 'Metal', 'values' => '14k Yellow, 18k White, Platinum'], ['name' => 'Size', 'values' => '5, 6, 7, 8, 9'], ['name' => 'Carat', 'values' => '1.0, 1.5, 2.0']],
            ]],
            ['features', [
                'eyebrow' => 'What’s in the box',
                'title' => 'A configurator builder that thinks like a *product sheet*.',
                'items' => [
                    ['code' => 'F-01', 'title' => 'Steps and fields', 'body' => 'Image tiles, swatches, buttons, dropdowns, text with live preview, numbers, dimensions, file uploads and add-on products.'],
                    ['code' => 'F-02', 'title' => 'Conditional rules', 'body' => 'Show, hide, require or disable options based on earlier answers, quantity or the shopper’s country.'],
                    ['code' => 'F-03', 'title' => 'Exact pricing', 'body' => 'Fixed and percentage add-ons, per character, per unit or area, quantity tiers, price tables and formulas.'],
                    ['code' => 'F-04', 'title' => 'Signed checkout', 'body' => 'The server signs every price and a Shopify Cart Transform checks it. Edited carts fall back to the normal price.'],
                    ['code' => 'F-05', 'title' => 'Layered preview', 'body' => 'Stack images per choice and render engraving text on the product — the composite becomes the cart image.'],
                    ['code' => 'F-06', 'title' => 'Production ready', 'body' => 'Every order carries a readable spec, a production PDF and the shopper’s uploaded files.'],
                ],
            ]],
            ['steps', [
                'eyebrow' => 'How it works',
                'title' => 'From install to live in *four steps*.',
                'items' => [
                    ['title' => 'Pick a template', 'body' => 'Start from a ring builder, apparel, furniture, print or a blank sheet.'],
                    ['title' => 'Map your variants', 'body' => 'Choose which Shopify options become steps and how each one looks.'],
                    ['title' => 'Add rules and prices', 'body' => 'Preview every combination with the same engine the storefront runs.'],
                    ['title' => 'Assign and publish', 'body' => 'Attach it to products, collections or tags and turn on the app embed.'],
                ],
            ]],
            ['templates', ['eyebrow' => 'Templates', 'title' => 'Start from a *proven* layout.', 'count' => 6]],
            ['faq', ['eyebrow' => 'Questions', 'title' => 'Before you install', 'placement' => 'home']],
            $cta,
        ], 'Composa turns any Shopify product into a guided configurator with variants, rules and exact, checkout-verified pricing.');

        $this->page('features', 'Features', [
            ['features', [
                'eyebrow' => 'Builder',
                'title' => 'Everything a custom product needs, *nothing it doesn’t*.',
                'items' => [
                    ['code' => 'B-01', 'title' => 'Eleven field types', 'body' => 'Tiles, swatches, buttons, dropdowns, radio, checkboxes, text, number, dimensions, file upload and product add-ons.'],
                    ['code' => 'B-02', 'title' => 'Repeatable steps', 'body' => 'Let shoppers add several charms, panels or names, each with its own options and price.'],
                    ['code' => 'B-03', 'title' => 'Variant mapping', 'body' => 'Use your existing options, add your own steps on top, or mix both in one configurator.'],
                    ['code' => 'B-04', 'title' => 'Translations', 'body' => 'Translate every label, option and message into your store’s languages.'],
                    ['code' => 'B-05', 'title' => 'Display options', 'body' => 'Popup, side drawer or a dedicated, shareable page; progress bar, step chips and a sticky price.'],
                    ['code' => 'B-06', 'title' => 'Version history', 'body' => 'Published configurators are fixed; drafts can be previewed, compared and restored.'],
                ],
            ]],
            ['features', [
                'eyebrow' => 'Pricing',
                'title' => 'Seven ways to price, *one engine* everywhere.',
                'intro' => 'The builder preview, the storefront and the server all run the same rules engine, so the price a shopper sees is the price they pay.',
                'items' => [
                    ['code' => 'P-01', 'title' => 'Fixed & percent', 'body' => 'Add a flat amount or a percentage of the base price.'],
                    ['code' => 'P-02', 'title' => 'Per character', 'body' => 'Price engraving by length, with or without spaces.'],
                    ['code' => 'P-03', 'title' => 'Per unit or area', 'body' => 'Width × height, length or any number field, with a divisor.'],
                    ['code' => 'P-04', 'title' => 'Tiers', 'body' => 'Quantity breaks or value bands.'],
                    ['code' => 'P-05', 'title' => 'Price tables', 'body' => 'Look up a price from two answers — size by material, width by height.'],
                    ['code' => 'P-06', 'title' => 'Formulas', 'body' => 'Safe expressions with min, max, round, ceil and if — no code runs on your store.'],
                ],
            ]],
            ['comparison', [
                'eyebrow' => 'Compared',
                'title' => 'Why not a product options app?',
                'us_label' => 'Composa', 'them_label' => 'Typical options app',
                'rows' => [
                    ['feature' => 'Uses your Shopify variants', 'us' => 'Yes — inventory and SKUs stay correct', 'them' => 'Often duplicates them as options'],
                    ['feature' => 'Price verified at checkout', 'us' => 'Signed price checked by Cart Transform', 'them' => 'Hidden products or editable prices'],
                    ['feature' => 'Step-by-step flow', 'us' => 'Steps, progress and review', 'them' => 'One long form'],
                    ['feature' => 'Conditional logic', 'us' => 'Show, hide, require, disable, set price', 'them' => 'Show/hide only'],
                    ['feature' => 'Production output', 'us' => 'Spec, PDF and files on every order', 'them' => 'Line item properties'],
                ],
            ]],
            $cta,
        ], 'Steps, rules, seven pricing types, layered previews and a signed checkout.');

        $this->page('templates', 'Templates', [
            ['templates', ['eyebrow' => 'Library', 'title' => 'Starter *templates*', 'intro' => 'Each template is a complete configurator you can edit: steps, rules and prices included. Templates that need a higher plan say so before you install them.', 'count' => 24]],
            $cta,
        ], 'Ring builders, apparel, furniture, print, gift boxes and B2B — ready to edit.');

        $this->page('pricing', 'Pricing', [
            ['pricing', ['eyebrow' => 'Plans', 'title' => 'Simple plans, *clear limits*.', 'intro' => 'Every plan includes the full storefront and signed checkout. Plans differ in how many configurators, options and products you can use.', 'footnote' => 'Billed monthly through Shopify. Prices in USD; taxes may apply.', 'show_limits' => true]],
            ['faq', ['eyebrow' => 'Billing', 'title' => 'Pricing questions', 'placement' => 'pricing']],
            $cta,
        ], 'Composa plans and limits. Free on development stores, 14-day trial on paid plans, billed through Shopify.');

        $this->page('about', 'About', [
            ['rich_text', ['title' => 'Why Composa', 'body' => "Custom products are where small brands win — engagement rings sized and set to order, sofas in the customer's fabric, signage cut to the wall.\n\nSelling them online usually means a pile of hidden products, prices that can be edited in the cart, and orders that arrive as a wall of text. Composa exists to fix that: a configurator that uses the variants you already have, prices exactly, and hands your workshop a clean spec.\n\n## How we build\n\n- **Correct before clever.** One pricing engine runs in the builder, the storefront and on our server.\n- **Fast storefronts.** The script on your theme is tiny; the configurator loads when a shopper opens it.\n- **No lock-in.** Your products, variants and orders stay standard Shopify data."]],
            $cta,
        ], 'Why we built Composa and how we work.');

        $this->page('contact', 'Contact', [
            ['form', ['eyebrow' => 'Contact', 'title' => 'Talk to a *person*.', 'intro' => 'Questions about a configurator, a plan or a partnership. We reply within one business day.', 'kind' => 'contact', 'success' => 'Thanks — we’ve got your message and will reply within one business day.']],
        ], 'Contact the Composa team.');

        $this->page('early-access', 'Early access', [
            ['form', ['eyebrow' => 'Early access', 'title' => 'Help shape *what’s next*.', 'intro' => 'We invite a small group of stores to try new features before release — 3D previews, B2B quotes and bundles. Tell us about your products.', 'kind' => 'beta', 'success' => 'Thanks for applying. We review applications every week and will email you.']],
        ], 'Apply for early access to new Composa features.');

        $this->page('changelog', 'Changelog', [
            ['changelog', ['eyebrow' => 'Releases', 'title' => 'What’s *new*', 'count' => 30]],
            ['form', ['eyebrow' => 'Updates', 'title' => 'Get release notes by email', 'kind' => 'newsletter', 'success' => 'You’re subscribed.']],
        ], 'New features, improvements and fixes in Composa.');

        $this->page('status', 'Status', [
            ['status', ['eyebrow' => 'Live', 'title' => 'System *status*']],
        ], 'Current status of Composa services.');

        $legal = [
            'privacy' => ['Privacy policy', "## Who we are\n\nComposa provides a product configurator app for Shopify merchants. This policy explains what we collect when merchants install Composa and when their customers use a Composa configurator.\n\n## Data we collect from merchants\n\nStore name, domain, contact email, plan and the configuration data you create in the app. We receive this from Shopify when you install.\n\n## Data we process for shoppers\n\nThe choices a shopper makes in a configurator, any files they upload, and anonymous usage events (opens, steps reached). We do not collect payment details — checkout is handled by Shopify.\n\n## How long we keep it\n\nConfigurations that never become orders expire after 30 days. Uploaded files that are not ordered are deleted after 30 days. All store data is deleted 48 hours after uninstall.\n\n## Your rights\n\nWe respond to Shopify's customer data request and redaction webhooks within 30 days. Contact privacy@composa.app for anything else.\n\n## Sub-processors\n\nHosting and storage (Railway), error monitoring (Sentry) and email delivery. A current list is available on request."],
            'terms' => ['Terms of service', "## The service\n\nComposa is a Shopify app. By installing it you agree to these terms and to Shopify's terms for apps.\n\n## Billing\n\nPlans are billed by Shopify through your Shopify invoice. Upgrades, downgrades and cancellations are made in the app and handled by Shopify. We do not offer refunds outside Shopify's billing process.\n\n## Plan limits\n\nEach plan has limits shown on the pricing page. When you reach a limit, new items of that kind pause until you upgrade. After a downgrade you have 7 days before extra configurators return to draft.\n\n## Your content\n\nYou own your products, configurators and orders. You give us permission to process them only to run the service.\n\n## Acceptable use\n\nDon't use Composa to sell products that break Shopify's Acceptable Use Policy, and don't try to bypass pricing or limits.\n\n## Liability\n\nThe service is provided as is. Our liability is limited to the fees you paid in the last 12 months."],
            'dpa' => ['Data processing addendum', "## Roles\n\nThe merchant is the controller of shopper data; Composa is the processor.\n\n## Processing\n\nWe process configurator answers, uploaded files and usage events only to provide the service and on the merchant's instructions.\n\n## Security\n\nData is encrypted in transit and at rest. Access tokens and signing secrets are encrypted in the database. Staff access is role-based, and every support session is logged.\n\n## Deletion\n\nOn uninstall, store data is deleted after 48 hours. Shopify redaction requests are completed within 30 days."],
        ];
        foreach ($legal as $slug => [$title, $body]) {
            $this->page('legal/'.$slug, $title, [['legal', ['body' => $body]]], null, 'legal');
        }
    }

    private function faqs(): void
    {
        $faqs = [
            ['Does Composa work with my existing variants?', 'Yes. Composa reads your product’s options (for example Color, Size and Carat) and turns them into steps. The order is placed on the matching variant, so inventory and SKUs stay correct.', ['home', 'help', 'app']],
            ['Will it slow down my store?', 'The script on your theme is about 2 KB compressed and only wires up the Customize button. The configurator itself loads when a shopper opens it.', ['home']],
            ['Can shoppers change the price in the cart?', 'No. The price is calculated and signed on our server; a Shopify Cart Transform checks the signature at checkout. If anything was edited, the line falls back to the product’s normal price.', ['home', 'help']],
            ['Does it work with my theme?', 'Composa uses a theme app extension, so it works with any Online Store 2.0 theme without editing code. You can place the button with a block or let the app embed add it.', ['home', 'help']],
            ['How am I billed?', 'Through Shopify. Your plan appears on your Shopify invoice; you can change or cancel it from the app at any time.', ['pricing', 'app']],
            ['Is there a free plan?', 'Composa is free on development stores, so agencies and developers can build before a store launches. Paid plans include a 14-day free trial.', ['pricing']],
            ['What happens when I reach a limit?', 'You’ll see a warning at 80%. At 100%, new items of that kind pause — for example you can’t publish another configurator — until you upgrade. Nothing already live is switched off.', ['pricing', 'app']],
            ['What happens if I downgrade?', 'You have 7 days to fit the new plan. After that, the most recently published configurators above the limit return to draft.', ['pricing']],
            ['Do you offer discounts or coupons?', 'Pricing is set in Shopify’s app billing, which doesn’t support coupons, so everyone pays the listed price. Every paid plan has a free trial.', ['pricing']],
        ];
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
