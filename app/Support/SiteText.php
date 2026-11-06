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

    /**
     * A legal document as clauses: each "## Heading" starts one. A first "> ..." line inside a
     * clause is its plain-words summary, shown beside the legal text.
     *
     * @return list<array{id:string,title:string,html:HtmlString,plain:?string}>
     */
    public static function clauses(?string $markdown): array
    {
        $parts = preg_split('/^##\s+/m', (string) $markdown);
        $intro = trim(array_shift($parts));
        $out = [];
        foreach ($parts as $part) {
            [$title, $body] = array_pad(explode("\n", $part, 2), 2, '');
            $plain = null;
            if (preg_match('/^>\s?(.+)$/m', $body, $m)) {
                $plain = trim($m[1]);
                $body = preg_replace('/^>\s?.+$/m', '', $body, 1);
            }
            $out[] = ['id' => Str::slug($title), 'title' => trim($title), 'html' => self::markdown(trim($body)), 'plain' => $plain];
        }
        if ($intro !== '' && $out === []) {
            $out[] = ['id' => 'document', 'title' => 'Document', 'html' => self::markdown($intro), 'plain' => null];
        }

        return $out;
    }

    public static function url(?string $url): string
    {
        $url = (string) $url;

        return preg_match('#^(https?://|/|\#|mailto:)#i', $url) ? $url : '/'.ltrim($url, '/');
    }
}
