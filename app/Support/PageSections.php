<?php

namespace App\Support;

/**
 * The section blocks a website page is built from. Super admin edits them with the page builder;
 * resources/views/site/sections/{type}.blade.php renders each one. Sections that list records
 * (pricing, FAQs, testimonials, templates, changelog, posts) read them live from the database.
 */
final class PageSections
{
    public const TYPES = [
        'hero' => 'Hero',
        'spec_strip' => 'Spec strip (numbers)',
        'features' => 'Feature grid',
        'steps' => 'How it works',
        'variant_demo' => 'Variant mapping demo',
        'templates' => 'Template gallery',
        'pricing' => 'Pricing table',
        'comparison' => 'Comparison table',
        'testimonials' => 'Testimonials',
        'faq' => 'FAQ',
        'posts' => 'Latest journal posts',
        'changelog' => 'Changelog',
        'status' => 'System status',
        'rich_text' => 'Text',
        'form' => 'Form',
        'cta' => 'Call to action',
    ];

    public const FORMS = ['contact' => 'Contact', 'beta' => 'Beta application', 'newsletter' => 'Newsletter'];

    public const FAQ_PLACEMENTS = ['home' => 'Home', 'pricing' => 'Pricing', 'help' => 'Help centre', 'app' => 'Merchant app'];

    public static function label(string $type): string
    {
        return self::TYPES[$type] ?? $type;
    }
}
