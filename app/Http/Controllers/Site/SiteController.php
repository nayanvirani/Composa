<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\DocArticle;
use App\Models\DocCategory;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/** Public website. Every page's content comes from the CMS in super admin. */
class SiteController extends Controller
{
    public const LOCALES = ['fr', 'de', 'es', 'it', 'nl', 'pt'];

    public function page(Request $request, ?string $locale = null, string $slug = 'home'): View|Response
    {
        if ($locale !== null && ! in_array($locale, self::LOCALES, true)) {
            [$slug, $locale] = [trim($locale.'/'.($slug === 'home' ? '' : $slug), '/'), null];
        }
        $locale ??= 'en';
        $page = Page::query()->live()->where('slug', $slug)->where('locale', $locale)->first()
            ?? ($locale !== 'en' ? Page::query()->live()->where('slug', $slug)->where('locale', 'en')->first() : null);

        if (! $page) {
            return $this->notFound();
        }
        app()->setLocale($locale);

        return view($page->template === 'legal' ? 'site.legal' : 'site.page', ['page' => $page, 'locale' => $locale]);
    }

    public function docs(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $results = $q !== '' ? DocArticle::query()->where('status', 'published')
            ->where(fn ($w) => $w->where('title', 'ilike', "%{$q}%")->orWhere('body', 'ilike', "%{$q}%"))
            ->with('category')->limit(20)->get() : null;

        return view('site.docs', [
            'q' => $q,
            'results' => $results,
            'categories' => DocCategory::query()->orderBy('sort')->with(['articles' => fn ($a) => $a->where('status', 'published')->orderBy('sort')])->get(),
            'startHere' => DocArticle::query()->where('status', 'published')->where('start_here', true)->orderBy('sort')->limit(4)->get(),
        ]);
    }

    public function doc(string $slug): View|Response
    {
        $article = DocArticle::query()->where('status', 'published')->where('slug', $slug)->with('category')->first();
        if (! $article) {
            return $this->notFound();
        }
        $siblings = DocArticle::query()->where('status', 'published')->where('doc_category_id', $article->doc_category_id)->orderBy('sort')->get(['id', 'slug', 'title']);

        return view('site.doc', ['article' => $article, 'siblings' => $siblings]);
    }

    public function docVote(Request $request, string $slug): Response
    {
        $request->validate(['helpful' => 'required|boolean']);
        $key = 'doc_vote:'.$slug.':'.sha1((string) $request->ip());
        if (Cache::add($key, 1, now()->addDay())) {
            DocArticle::query()->where('slug', $slug)->increment($request->boolean('helpful') ? 'helpful_yes' : 'helpful_no');
        }

        return response()->noContent();
    }

    public function journal(Request $request): View
    {
        $tag = $request->query('tag');
        $posts = Post::query()->live()->when($tag, fn ($q) => $q->where('tag', $tag))->orderByDesc('published_at')->paginate(12)->withQueryString();

        return view('site.journal', [
            'posts' => $posts,
            'tag' => $tag,
            'tags' => Post::query()->live()->whereNotNull('tag')->distinct()->orderBy('tag')->pluck('tag'),
            'featured' => $tag || $posts->currentPage() > 1 ? null : Post::query()->live()->where('featured', true)->orderByDesc('published_at')->first(),
        ]);
    }

    public function post(string $slug): View|Response
    {
        $post = Post::query()->live()->where('slug', $slug)->first();
        if (! $post) {
            return $this->notFound();
        }

        return view('site.post', [
            'post' => $post,
            'related' => Post::query()->live()->whereKeyNot($post->id)->when($post->tag, fn ($q) => $q->where('tag', $post->tag))->orderByDesc('published_at')->limit(3)->get(),
        ]);
    }

    public function sitemap(): Response
    {
        $urls = Page::query()->live()->get(['slug', 'locale', 'updated_at'])->map(fn ($p) => [url(($p->locale === 'en' ? '' : $p->locale.'/').($p->slug === 'home' ? '' : $p->slug)), $p->updated_at])
            ->concat(Post::query()->live()->get(['slug', 'updated_at'])->map(fn ($p) => [url('journal/'.$p->slug), $p->updated_at]))
            ->concat(DocArticle::query()->where('status', 'published')->get(['slug', 'updated_at'])->map(fn ($a) => [url('docs/'.$a->slug), $a->updated_at]))
            ->push([url('docs'), null])->push([url('journal'), null]);

        return response()->view('site.sitemap', ['urls' => $urls], 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(): Response
    {
        return response("User-agent: *\nDisallow: /admin\nDisallow: /app\nDisallow: /proxy\nSitemap: ".url('sitemap.xml')."\n", 200, ['Content-Type' => 'text/plain']);
    }

    public function notFound(): Response
    {
        return response()->view('site.404', [], 404);
    }
}
