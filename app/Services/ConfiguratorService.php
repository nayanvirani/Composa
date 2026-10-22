<?php

namespace App\Services;

use App\Engine\DefinitionValidator;
use App\Jobs\SyncStorefrontMetafields;
use App\Models\Configurator;
use App\Models\ConfiguratorVersion;
use App\Models\Shop;
use App\Models\Template;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Configurator lifecycle. Edits always go to the draft version; publishing freezes it, makes it
 * live and republishes the storefront metafields. Published versions are never edited, so carts
 * and orders always point at exactly what the shopper saw.
 */
class ConfiguratorService
{
    public function __construct(private readonly TemplateLibrary $templates) {}

    /** @param array{name:string,display_mode?:string,base_product_id?:?string,base_variant_id?:?string,base_product_title?:?string,base_product_image?:?string,template?:?string,copy_from?:?int} $data */
    public function create(Shop $shop, array $data): Configurator
    {
        $limits = PlanLimits::for($shop);
        if (! $limits->allows('drafts')) {
            throw ValidationException::withMessages(['name' => 'You’ve reached '.$limits->limit('drafts').' draft configurators on your plan. Publish or delete a draft, or upgrade.']);
        }

        $definition = self::blankDefinition();
        $templateKey = null;
        if (! empty($data['copy_from'])) {
            $source = $shop->configurators()->findOrFail($data['copy_from']);
            $definition = ($source->draftVersion ?? $source->publishedVersion)?->definition ?? $definition;
        } elseif (! empty($data['template'])) {
            $template = Template::query()->where('key', $data['template'])->where('status', 'published')->firstOrFail();
            $definition = $this->templates->definitionFor($template);
            $templateKey = $template->key;
            $template->increment('install_count');
        }

        return DB::transaction(function () use ($shop, $data, $definition, $templateKey) {
            $configurator = $shop->configurators()->create([
                'name' => $data['name'],
                'handle' => $this->uniqueHandle($shop, $data['name']),
                'template_key' => $templateKey,
                'status' => 'draft',
                'display_mode' => $data['display_mode'] ?? 'popup',
                'variant_mode' => $data['variant_mode'] ?? 'variants',
                'base_product_id' => $data['base_product_id'] ?? null,
                'base_variant_id' => $data['base_variant_id'] ?? null,
                'base_product_title' => $data['base_product_title'] ?? null,
                'base_product_image' => $data['base_product_image'] ?? null,
                'display' => self::defaultDisplay(),
            ]);
            $version = $configurator->versions()->create(['version' => 1, 'status' => 'draft', 'definition' => $definition]);
            $configurator->forceFill(['draft_version_id' => $version->id])->save();

            return $configurator->refresh();
        });
    }

    /** The editable version: the current draft, or a new draft copied from the live version. */
    public function draft(Configurator $configurator): ConfiguratorVersion
    {
        $draft = $configurator->draftVersion;
        if ($draft && $draft->isDraft()) {
            return $draft;
        }
        $source = $configurator->publishedVersion ?? $configurator->versions()->first();
        $next = (int) $configurator->versions()->max('version') + 1;
        $draft = $configurator->versions()->create(['version' => $next, 'status' => 'draft', 'definition' => $source?->definition ?? self::blankDefinition()]);
        $configurator->forceFill(['draft_version_id' => $draft->id])->save();

        return $draft;
    }

    /** Saves the builder's definition and settings into the draft. Validation problems are returned, not thrown. */
    public function saveDraft(Configurator $configurator, array $definition, array $meta = []): array
    {
        $draft = $this->draft($configurator);
        $draft->forceFill(['definition' => self::normalise($definition)])->save();

        $configurator->fill(array_intersect_key($meta, array_flip(['name', 'display_mode', 'variant_mode', 'base_product_id', 'base_variant_id', 'base_product_title', 'base_product_image'])));
        if (array_key_exists('display', $meta)) {
            $configurator->display = array_replace_recursive(self::defaultDisplay(), (array) $meta['display']);
        }
        $configurator->save();

        return [
            'problems' => DefinitionValidator::validate($draft->definition),
            'violations' => PlanLimits::for($configurator->shop)->violations($draft->definition, $configurator),
        ];
    }

    /** @return array{ok:bool,problems:list<array>,violations:list<array>,version?:int} */
    public function publish(Configurator $configurator, ?string $notes = null): array
    {
        $draft = $this->draft($configurator);
        $problems = DefinitionValidator::validate($draft->definition);
        $violations = PlanLimits::for($configurator->shop)->violations($draft->definition, $configurator);
        if ($problems || $violations) {
            return ['ok' => false, 'problems' => $problems, 'violations' => $violations];
        }

        DB::transaction(function () use ($configurator, $draft, $notes) {
            $configurator->versions()->where('status', 'published')->update(['status' => 'archived']);
            $draft->forceFill(['status' => 'published', 'published_at' => now(), 'notes' => $notes])->save();
            $configurator->forceFill([
                'status' => 'published',
                'published_version_id' => $draft->id,
                'draft_version_id' => null,
                'published_at' => now(),
            ])->save();
        });
        SyncStorefrontMetafields::dispatch($configurator->shop_id);

        return ['ok' => true, 'problems' => [], 'violations' => [], 'version' => $draft->version];
    }

    public function unpublish(Configurator $configurator): void
    {
        $configurator->forceFill(['status' => 'draft'])->save();
        SyncStorefrontMetafields::dispatch($configurator->shop_id);
    }

    /** Makes an earlier version the new draft (published versions stay untouched). */
    public function restore(Configurator $configurator, ConfiguratorVersion $version): ConfiguratorVersion
    {
        $draft = $this->draft($configurator);
        $draft->forceFill(['definition' => $version->definition])->save();

        return $draft;
    }

    public function duplicate(Configurator $configurator): Configurator
    {
        return $this->create($configurator->shop, [
            'name' => $configurator->name.' (copy)',
            'display_mode' => $configurator->display_mode,
            'variant_mode' => $configurator->variant_mode,
            'copy_from' => $configurator->id,
        ]);
    }

    public function delete(Configurator $configurator): void
    {
        $wasLive = $configurator->isPublished();
        $configurator->assignments()->delete();
        $configurator->delete();
        if ($wasLive) {
            SyncStorefrontMetafields::dispatch($configurator->shop_id);
        }
    }

    public static function blankDefinition(): array
    {
        return [
            'schema' => 1,
            'settings' => ['markup_percent' => 0, 'rounding' => ['mode' => 'none'], 'variables' => (object) []],
            'steps' => [[
                'key' => 'step_1', 'title' => 'Step 1', 'help' => '', 'layout' => 'stack',
                'fields' => [['key' => 'choice', 'type' => 'buttons', 'label' => 'Choose an option', 'required' => true, 'options' => [
                    ['value' => 'option_a', 'label' => 'Option A', 'default' => true],
                    ['value' => 'option_b', 'label' => 'Option B', 'price_rule' => ['type' => 'fixed', 'amount' => 10]],
                ]]],
            ]],
            'rules' => [],
            'price_rules' => [],
            'tables' => (object) [],
            'layers' => [],
            'translations' => (object) [],
        ];
    }

    public static function defaultDisplay(): array
    {
        return [
            'button' => ['label' => 'Customize', 'style' => 'theme', 'product_position' => 'replace_add_to_cart', 'collection_cards' => true],
            'steps' => ['progress' => true, 'chips' => true, 'sticky_price' => true, 'breakdown' => true, 'from_price' => true, 'review' => true],
            'after_add' => 'drawer',
            'appearance' => ['inherit_theme' => true],
        ];
    }

    /** Keeps JSON objects as objects (PHP turns empty ones into lists) and trims labels. */
    public static function normalise(array $definition): array
    {
        $definition['schema'] = 1;
        foreach (['settings', 'tables', 'translations'] as $k) {
            if (empty($definition[$k])) {
                $definition[$k] = (object) [];
            }
        }
        if (isset($definition['settings']) && is_array($definition['settings']) && empty($definition['settings']['variables'])) {
            $definition['settings']['variables'] = (object) [];
        }
        foreach (['steps', 'rules', 'price_rules', 'layers'] as $k) {
            $definition[$k] = array_values((array) ($definition[$k] ?? []));
        }

        return $definition;
    }

    private function uniqueHandle(Shop $shop, string $name): string
    {
        $base = Str::slug($name) ?: 'configurator';
        $handle = $base;
        for ($i = 2; $shop->configurators()->withTrashed()->where('handle', $handle)->exists(); $i++) {
            $handle = $base.'-'.$i;
        }

        return $handle;
    }
}
