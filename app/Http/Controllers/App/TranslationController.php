<?php

namespace App\Http\Controllers\App;

use App\Models\Configurator;
use App\Services\ConfiguratorService;
use App\Services\PlanLimits;
use App\Services\TranslationKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TranslationController extends Controller
{
    public function index(Request $request, ConfiguratorService $service): Response
    {
        $shop = $this->shop();
        $configurators = $shop->configurators()->with(['draftVersion', 'publishedVersion'])->get();
        $current = $configurators->firstWhere('id', $request->integer('configurator')) ?? $configurators->first();
        $definition = $current ? $service->draft($current)->definition : [];
        $locales = array_values(array_diff($shop->locales ?? [], [$shop->primary_locale]));

        return Inertia::render('Translations', [
            'configurators' => $configurators->map->only(['id', 'name']),
            'current' => $current?->id,
            'primary' => $shop->primary_locale,
            'locales' => $locales,
            'limit' => PlanLimits::for($shop)->limit('languages'),
            'keys' => $current ? TranslationKeys::extract($definition) : [],
            'translations' => $definition['translations'] ?? [],
            'coverage' => collect($locales)->mapWithKeys(fn ($l) => [$l => TranslationKeys::coverage($definition, $l)]),
        ]);
    }

    public function update(Request $request, Configurator $configurator, ConfiguratorService $service): JsonResponse
    {
        $data = $request->validate(['locale' => 'required|string|max:12', 'strings' => 'required|array']);
        $c = $this->configurator($configurator->id);
        $draft = $service->draft($c);
        $definition = $draft->definition;
        $allowed = array_keys(TranslationKeys::extract($definition));
        $definition['translations'] = (array) ($definition['translations'] ?? []);
        $definition['translations'][$data['locale']] = array_filter(array_intersect_key(array_map(fn ($v) => is_string($v) ? mb_substr($v, 0, 500) : '', $data['strings']), array_flip($allowed)), fn ($v) => $v !== '');
        $result = $service->saveDraft($c, $definition);

        return response()->json($result + ['coverage' => TranslationKeys::coverage($definition, $data['locale'])]);
    }
}
