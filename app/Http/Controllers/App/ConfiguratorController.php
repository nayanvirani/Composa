<?php

namespace App\Http\Controllers\App;

use App\Engine\DefinitionValidator;
use App\Engine\Engine;
use App\Models\Configurator;
use App\Services\AssignmentService;
use App\Services\ConfiguratorService;
use App\Services\PlanLimits;
use App\Services\ProductCatalog;
use App\Services\TemplateLibrary;
use App\Services\TranslationKeys;
use App\Services\VariantMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConfiguratorController extends Controller
{
    public function __construct(private readonly ConfiguratorService $service) {}

    public function index(): Response
    {
        $shop = $this->shop();
        $limits = PlanLimits::for($shop);

        return Inertia::render('Configurators/Index', [
            'configurators' => $shop->configurators()->with(['assignments', 'draftVersion', 'publishedVersion'])->latest('updated_at')->get()->map(fn (Configurator $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'handle' => $c->handle,
                'status' => $c->status,
                'display_mode' => $c->display_mode,
                'unpublished' => $c->hasUnpublishedChanges() && $c->published_version_id,
                'version' => $c->publishedVersion?->version,
                'draft_version' => $c->draftVersion?->version,
                'base' => ['title' => $c->base_product_title, 'image' => $c->base_product_image],
                'steps' => count(($c->draftVersion ?? $c->publishedVersion)?->definition['steps'] ?? []),
                'assigned' => $c->assignments->map(fn ($a) => ucfirst($a->target_type).': '.$a->target_label)->implode(', '),
                'products' => (int) $c->assignments->sum('product_count'),
                'updated_at' => $c->updated_at?->toIso8601String(),
            ]),
            'limits' => ['published' => $limits->limit('configurators'), 'drafts' => $limits->limit('drafts'), 'steps' => $limits->limit('steps')],
        ]);
    }

    public function create(TemplateLibrary $templates): Response
    {
        return Inertia::render('Configurators/Create', [
            'templates' => $templates->forShop($this->shop()),
            'existing' => $this->shop()->configurators()->get(['id', 'name']),
            'preselect' => request('template'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'display_mode' => 'in:popup,page',
            'variant_mode' => 'in:variants,mixed,base',
            'template' => 'nullable|string|max:64',
            'copy_from' => 'nullable|integer',
            'base_product_id' => 'nullable|string|max:120',
            'base_variant_id' => 'nullable|string|max:120',
            'base_product_title' => 'nullable|string|max:255',
            'base_product_image' => 'nullable|string|max:1024',
            'assign_base' => 'boolean',
        ]);
        $configurator = $this->service->create($this->shop(), $data);
        if (($data['assign_base'] ?? false) && $configurator->base_product_id) {
            app(AssignmentService::class)->add($configurator, 'product', $configurator->base_product_id, $configurator->base_product_title);
        }

        return response()->json(['id' => $configurator->id, 'redirect' => route('app.configurators.show', $configurator)]);
    }

    public function show(Configurator $configurator, ProductCatalog $catalog, AssignmentService $assignments): Response
    {
        $c = $this->configurator($configurator->id);
        $draft = $this->service->draft($c);
        $limits = PlanLimits::for($c->shop);
        $tab = request('tab', 'steps');

        return Inertia::render('Configurators/Builder', [
            'configurator' => [
                'id' => $c->id, 'name' => $c->name, 'handle' => $c->handle, 'status' => $c->status,
                'display_mode' => $c->display_mode, 'variant_mode' => $c->variant_mode, 'display' => $c->display ?? ConfiguratorService::defaultDisplay(),
                'base_product_id' => $c->base_product_id, 'base_variant_id' => $c->base_variant_id,
                'base_product_title' => $c->base_product_title, 'base_product_image' => $c->base_product_image,
                'published_version' => $c->publishedVersion?->version, 'draft_version' => $draft->version,
                'variant_changes' => $c->display['variant_changes'] ?? [], 'variant_synced_at' => $c->display['variant_synced_at'] ?? null,
            ],
            'definition' => $draft->definition,
            'tab' => $tab,
            'problems' => DefinitionValidator::validate($draft->definition),
            'violations' => $limits->violations($draft->definition, $c),
            'limits' => ['steps' => $limits->limit('steps'), 'options' => $limits->limit('options'), 'rules' => $limits->limit('rules'), 'languages' => $limits->limit('languages')],
            'features' => $c->shop->plan?->features ?? [],
            'product' => fn () => $c->base_product_id ? $catalog->product($c->shop, $c->base_product_id) : null,
            'assignments' => fn () => $c->assignments()->get()->map->only(['id', 'target_type', 'target_id', 'target_label', 'product_count', 'priority']),
            'conflicts' => fn () => $assignments->conflicts($c),
            'versions' => fn () => $c->versions()->get(['id', 'version', 'status', 'notes', 'published_at', 'updated_at']),
            'translationKeys' => fn () => TranslationKeys::extract($draft->definition),
            'locales' => $c->shop->locales ?? [$c->shop->primary_locale],
        ]);
    }

    /** Builder autosave. */
    public function saveDraft(Request $request, Configurator $configurator): JsonResponse
    {
        $data = $request->validate(['definition' => 'required|array', 'meta' => 'array']);
        $result = $this->service->saveDraft($this->configurator($configurator->id), $data['definition'], $data['meta'] ?? []);

        return response()->json($result + ['saved_at' => now()->toIso8601String()]);
    }

    public function publish(Request $request, Configurator $configurator): JsonResponse
    {
        $result = $this->service->publish($this->configurator($configurator->id), $request->string('notes')->toString() ?: null);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function unpublish(Configurator $configurator): JsonResponse
    {
        $this->service->unpublish($this->configurator($configurator->id));

        return response()->json(['ok' => true]);
    }

    public function duplicate(Configurator $configurator): JsonResponse
    {
        $copy = $this->service->duplicate($this->configurator($configurator->id));

        return response()->json(['id' => $copy->id, 'redirect' => route('app.configurators.show', $copy)]);
    }

    public function destroy(Configurator $configurator): JsonResponse
    {
        $this->service->delete($this->configurator($configurator->id));

        return response()->json(['ok' => true]);
    }

    public function restore(Configurator $configurator, int $version): JsonResponse
    {
        $c = $this->configurator($configurator->id);
        $draft = $this->service->restore($c, $c->versions()->where('version', $version)->firstOrFail());

        return response()->json(['definition' => $draft->definition, 'draft_version' => $draft->version]);
    }

    /** Runs the server engine on test answers (the "Test with answers" panel and the publish check). */
    public function evaluate(Request $request, Configurator $configurator, ProductCatalog $catalog): JsonResponse
    {
        $data = $request->validate(['definition' => 'array', 'answers' => 'array', 'quantity' => 'integer|min:1']);
        $c = $this->configurator($configurator->id);
        $definition = $data['definition'] ?? $this->service->draft($c)->definition;
        $product = $c->base_product_id ? $catalog->product($c->shop, $c->base_product_id) : null;
        $base = $catalog->variant($product, $c->base_variant_id)['price_cents'] ?? ($product['variants'][0]['price_cents'] ?? 0);

        return response()->json(Engine::evaluate($definition, $data['answers'] ?? [], [
            'base_price_cents' => $base, 'quantity' => $data['quantity'] ?? 1, 'variants' => $product ? $catalog->engineVariants($product) : null,
        ]));
    }

    /** Maps the base product's Shopify options to steps (Shopify variants tab). */
    public function importVariants(Request $request, Configurator $configurator, ProductCatalog $catalog, VariantMapper $mapper): JsonResponse
    {
        $data = $request->validate(['definition' => 'required|array', 'choices' => 'array', 'variant_mode' => 'in:variants,mixed,base']);
        $c = $this->configurator($configurator->id);
        $product = $c->base_product_id ? $catalog->product($c->shop, $c->base_product_id) : null;
        abort_unless($product, 422, 'Choose a base product first.');

        $definition = $data['definition'];
        if (($data['variant_mode'] ?? 'variants') === 'base') {
            $definition['steps'] = array_values(array_filter($definition['steps'] ?? [], fn ($s) => ($s['source'] ?? 'custom') !== 'shopify_option'));
        } else {
            $definition = $mapper->apply($definition, $product, $data['choices'] ?? [], $c->shop);
        }
        $c->forceFill(['variant_mode' => $data['variant_mode'] ?? 'variants'])->save();

        return response()->json(['definition' => $definition, 'product' => $product]);
    }

    /** Pre-publish checklist. */
    public function checks(Configurator $configurator, ProductCatalog $catalog): JsonResponse
    {
        $c = $this->configurator($configurator->id);
        $draft = $this->service->draft($c);
        $def = $draft->definition;
        $limits = PlanLimits::for($c->shop);
        $problems = DefinitionValidator::validate($def);
        $violations = $limits->violations($def, $c);
        $product = $c->base_product_id ? $catalog->product($c->shop, $c->base_product_id) : null;
        $locales = array_diff($c->shop->locales ?? [], [$c->shop->primary_locale]);

        $checks = [
            ['key' => 'structure', 'ok' => ! $problems, 'detail' => $problems ? count($problems).' problem(s) to fix' : count($def['steps'] ?? []).' steps checked'],
            ['key' => 'plan', 'ok' => ! $violations, 'detail' => $violations ? implode(' ', array_column($violations, 'message')) : 'Within your plan limits'],
            ['key' => 'product', 'ok' => (bool) $product, 'detail' => $product ? $product['title'] : 'Choose a base product'],
            ['key' => 'selling_plans', 'ok' => true, 'detail' => 'Configured items hide subscription options'],
            ['key' => 'assignment', 'ok' => $c->assignments()->exists(), 'warning' => true, 'detail' => $c->assignments()->exists() ? (int) $c->assignments()->sum('product_count').' products' : 'Not assigned to any products yet'],
        ];
        foreach ($locales as $locale) {
            $cov = TranslationKeys::coverage($def, $locale);
            $checks[] = ['key' => 'translation_'.$locale, 'ok' => $cov['percent'] === 100, 'warning' => true, 'detail' => strtoupper($locale).' translation '.$cov['percent'].'% complete'];
        }

        $previous = $c->publishedVersion?->definition;

        return response()->json([
            'checks' => $checks,
            'can_publish' => ! $problems && ! $violations && $product,
            'diff' => $previous ? self::diff($previous, $def) : [],
            'problems' => $problems,
            'violations' => $violations,
        ]);
    }

    /** A short human summary of what changed since the live version. */
    private static function diff(array $old, array $new): array
    {
        $index = function (array $def) {
            $out = [];
            foreach ($def['steps'] ?? [] as $s) {
                $out['step:'.$s['key']] = $s['title'] ?? $s['key'];
                foreach ($s['fields'] ?? [] as $f) {
                    $out['field:'.$f['key']] = $f['label'] ?? $f['key'];
                    foreach ($f['options'] ?? [] as $o) {
                        $out['option:'.$f['key'].':'.$o['value']] = ($f['label'] ?? $f['key']).' · '.($o['label'] ?? $o['value']).'|'.json_encode($o['price_rule'] ?? null);
                    }
                }
            }
            foreach ($def['rules'] ?? [] as $r) {
                $out['rule:'.($r['id'] ?? md5(json_encode($r)))] = ($r['name'] ?? 'Rule').'|'.json_encode($r);
            }

            return $out;
        };
        $a = $index($old);
        $b = $index($new);
        $changes = [];
        foreach (array_diff_key($b, $a) as $k => $v) {
            $changes[] = ['kind' => 'added', 'text' => explode('|', $v)[0]];
        }
        foreach (array_diff_key($a, $b) as $k => $v) {
            $changes[] = ['kind' => 'removed', 'text' => explode('|', $v)[0]];
        }
        foreach (array_intersect_key($b, $a) as $k => $v) {
            if ($a[$k] !== $v) {
                $changes[] = ['kind' => 'changed', 'text' => explode('|', $v)[0]];
            }
        }
        if (json_encode($old['settings'] ?? []) !== json_encode($new['settings'] ?? [])) {
            $changes[] = ['kind' => 'changed', 'text' => 'Pricing settings'];
        }

        return array_slice($changes, 0, 40);
    }
}
