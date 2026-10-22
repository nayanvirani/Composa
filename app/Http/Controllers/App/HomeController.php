<?php

namespace App\Http\Controllers\App;

use App\Services\AnalyticsReport;
use App\Services\TemplateLibrary;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(AnalyticsReport $analytics, TemplateLibrary $templates): Response|RedirectResponse
    {
        $shop = $this->shop();
        // A shop without a plan goes through onboarding first (Shopify's plan page).
        if (! $shop->plan_id && ! request()->boolean('skip')) {
            return redirect()->route('app.onboarding.welcome');
        }
        $onboarding = $shop->onboarding ?? [];
        $configurators = $shop->configurators()->latest('updated_at')->take(5)->get();
        $theme = $shop->setting('theme', []);

        return Inertia::render('Home', [
            'checklist' => [
                ['key' => 'plan', 'done' => (bool) $shop->plan_id],
                ['key' => 'template', 'done' => $shop->configurators()->exists()],
                ['key' => 'embed', 'done' => (bool) ($theme['embed_enabled'] ?? false)],
                ['key' => 'assign', 'done' => $shop->assignments()->exists()],
                ['key' => 'publish', 'done' => $shop->configurators()->where('status', 'published')->exists()],
            ],
            'dismissed' => (bool) ($onboarding['checklist_dismissed'] ?? false),
            'configurators' => $configurators->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'status' => $c->status, 'unpublished' => $c->hasUnpublishedChanges(),
                'assigned' => $c->assignments()->get()->map(fn ($a) => ucfirst($a->target_type).': '.$a->target_label)->implode(', '),
                'steps' => count(($c->draftVersion ?? $c->publishedVersion)?->definition['steps'] ?? []),
            ]),
            'analytics' => fn () => $analytics->summary($shop, 30),
            'templates' => fn () => array_slice($templates->forShop($shop), 0, 6),
        ]);
    }
}
