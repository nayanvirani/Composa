<?php

namespace App\Http\Controllers\App;

use App\Models\Configurator;
use App\Services\AssignmentService;
use App\Services\PlanLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function __construct(private readonly AssignmentService $assignments) {}

    public function store(Request $request, Configurator $configurator): JsonResponse
    {
        $data = $request->validate(['targets' => 'required|array|min:1|max:50', 'targets.*.type' => 'required|in:product,collection,tag,vendor', 'targets.*.id' => 'required|string|max:255', 'targets.*.label' => 'nullable|string|max:255']);
        $c = $this->configurator($configurator->id);
        foreach ($data['targets'] as $t) {
            $this->assignments->add($c, $t['type'], $t['id'], $t['label'] ?? null);
        }

        return $this->list($c);
    }

    public function destroy(Configurator $configurator, int $assignment): JsonResponse
    {
        $c = $this->configurator($configurator->id);
        $this->assignments->remove($c->assignments()->findOrFail($assignment));

        return $this->list($c);
    }

    public function reorder(Request $request, Configurator $configurator): JsonResponse
    {
        $data = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $c = $this->configurator($configurator->id);
        $this->assignments->reorder($c, $data['ids']);

        return $this->list($c);
    }

    private function list(Configurator $c): JsonResponse
    {
        $limits = PlanLimits::for($c->shop);

        return response()->json([
            'assignments' => $c->assignments()->get()->map->only(['id', 'target_type', 'target_id', 'target_label', 'product_count', 'priority']),
            'conflicts' => $this->assignments->conflicts($c),
            'usage' => ['products' => $limits->usage()['products'], 'limit' => $limits->limit('products')],
        ]);
    }
}
