<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/** Formatting for CMS text on the website. */
final class SiteText
{
    /** Escapes text and turns *words* into <em>words</em> (used for highlighted headline words). */
    public static function inline(?string $text): HtmlString
    {
        $safe = e((string) $text);

        return new HtmlString(nl2br(preg_replace('/\*([^*\n]+)\*/', '<em>$1</em>', $safe)));
    }

    /** Markdown from the CMS; raw HTML is stripped and unsafe links are dropped. */
    public static function markdown(?string $text): HtmlString
    {
        return new HtmlString(Str::markdown((string) $text, ['html_input' => 'strip', 'allow_unsafe_links' => false, 'heading_permalink' => []]));
    }

    /** Headings of a markdown document for a table of contents: [[id, text], …] */
    public static function headings(?string $markdown): array
    {
        preg_match_all('/^##\s+(.+)$/m', (string) $markdown, $m);

        return array_map(fn ($h) => [Str::slug($h), trim($h)], $m[1]);
    }

    /** Markdown with ids on h2 so the table of contents can link to them. */
    public static function document(?string $markdown): HtmlString
    {
        $html = (string) self::markdown($markdown);
        $html = preg_replace_callback('/<h2>(.*?)<\/h2>/', fn ($m) => '<h2 id="'.Str::slug(strip_tags(html_entity_decode($m[1]))).'">'.$m[1].'</h2>', $html);

        return new HtmlString($html);
    }

    public static function url(?string $url): string
    {
        $url = (string) $url;

        return preg_match('#^(https?://|/|\#|mailto:)#i', $url) ? $url : '/'.ltrim($url, '/');
    }
}
