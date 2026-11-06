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
        'page_hero' => 'Page header',
        'composer' => 'Home hero with Fig. 1 composer',
        'ledger' => 'Without / with ledger',
        'routing' => 'Routing sheet (dark steps)',
        'bom' => 'Bill of materials table',
        'formula' => 'Pricing formula',
        'patterns' => 'Template cards (pinned)',
        'template_library' => 'Template library (filter + spec)',
        'spec_card' => 'Production spec card',
        'beta_inline' => 'Order form: beta access',
        'pricing' => 'Plan finder, plans & spec table',
        'chapters' => 'Feature chapters (A–J)',
        'input_types' => 'Input types grid',
        'tolerances' => 'Tolerances (principles)',
        'drawn_for' => 'Drawn for (audiences)',
        'team' => 'Team',
        'contact' => 'Contact request form',
        'beta' => 'Beta application wizard',
        'gets' => 'Numbered benefits',
        'notes' => 'Notes (FAQ)',
        'changelog' => 'Revision history',
        'status' => 'System status',
        'rich_text' => 'Text',
        'newsletter_band' => 'Newsletter band',
        'cta_band' => 'Call to action band',
    ];

    public const FAQ_PLACEMENTS = ['home' => 'Home', 'pricing' => 'Pricing', 'help' => 'Help centre', 'app' => 'Merchant app'];

    public static function label(string $type): string
    {
        return self::TYPES[$type] ?? $type;
    }
}
