<?php

namespace App\Services;

use App\Jobs\SyncStorefrontMetafields;
use App\Models\Assignment;
use App\Models\Configurator;
use Illuminate\Validation\ValidationException;

/**
 * Which products a configurator appears on. Collections, tags and vendors count every product they
 * cover toward the plan's "assigned products" limit. When several configurators match a product,
 * the lowest priority number wins (resolved on the storefront from the assignments metafield).
 */
class AssignmentService
{
    public function __construct(private readonly ProductCatalog $catalog) {}

    public function add(Configurator $configurator, string $type, string $id, ?string $label = null): Assignment
    {
        if (! in_array($type, ['product', 'collection', 'tag', 'vendor'], true)) {
            throw ValidationException::withMessages(['type' => 'Unknown assignment type.']);
        }
        $shop = $configurator->shop;
        $count = $this->catalog->countFor($shop, $type, $id);
        $limits = PlanLimits::for($shop);
        if (! $limits->allows('products', $count)) {
            throw ValidationException::withMessages(['target' => 'This adds '.$count.' products; your plan allows '.$limits->limit('products').' assigned products in total. Upgrade or remove an assignment.']);
        }

        $assignment = $configurator->assignments()->updateOrCreate(
            ['target_type' => $type, 'target_id' => $id],
            ['shop_id' => $shop->id, 'target_label' => $label ?? $id, 'product_count' => $count, 'priority' => ((int) $configurator->assignments()->max('priority')) + 1],
        );
        $this->changed($configurator);

        return $assignment;
    }

    public function remove(Assignment $assignment): void
    {
        $configurator = $assignment->configurator;
        $assignment->delete();
        $this->changed($configurator);
    }

    /** @param list<int> $orderedIds */
    public function reorder(Configurator $configurator, array $orderedIds): void
    {
        foreach (array_values($orderedIds) as $i => $id) {
            $configurator->assignments()->whereKey($id)->update(['priority' => $i + 1]);
        }
        $this->changed($configurator);
    }

    /** Products matched by more than one live configurator (the lower priority number wins). */
    public function conflicts(Configurator $configurator): array
    {
        $mine = $configurator->assignments()->get(['target_type', 'target_id']);
        $others = Assignment::query()->where('shop_id', $configurator->shop_id)->where('configurator_id', '!=', $configurator->id)->with('configurator:id,name,status')->get();

        return $others->filter(fn ($o) => $mine->contains(fn ($m) => $m->target_type === $o->target_type && $m->target_id === $o->target_id))
            ->map(fn ($o) => ['configurator' => $o->configurator?->name, 'target' => $o->target_label, 'type' => $o->target_type])->values()->all();
    }

    private function changed(Configurator $configurator): void
    {
        if ($configurator->isPublished()) {
            SyncStorefrontMetafields::dispatch($configurator->shop_id);
        }
    }
}
