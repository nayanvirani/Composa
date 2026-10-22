<?php

namespace App\Http\Controllers\App;

use App\Models\Plan;
use App\Services\BillingSync;
use App\Services\TemplateLibrary;
use App\Services\ThemeStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Onboarding: choose a plan (on Shopify's hosted plan page), pick a template, enable the app
 * embed, then assign and publish. Plans are billed only through Shopify App Pricing.
 */
class OnboardingController extends Controller
{
    public function welcome(): Response
    {
        $shop = $this->shop();

        return Inertia::render('Onboarding/Welcome', [
            'plans' => Plan::query()->where('is_public', true)->orderBy('sort')->get()
                ->reject(fn (Plan $p) => $p->dev_only && ! $shop->is_dev)
                ->map(fn (Plan $p) => ['handle' => $p->handle, 'name' => $p->name, 'price' => $p->priceLabel(), 'tagline' => $p->tagline, 'featured' => $p->is_featured, 'trial_days' => $p->trial_days, 'limits' => $p->limits, 'features' => $p->features])->values(),
            'current' => $shop->plan?->handle,
        ]);
    }

    public function template(TemplateLibrary $templates): Response
    {
        return Inertia::render('Onboarding/PickTemplate', ['templates' => $templates->forShop($this->shop())]);
    }

    public function embed(ThemeStatus $theme): Response
    {
        return Inertia::render('Onboarding/Embed', ['status' => $theme->check($this->shop())]);
    }

    public function checkTheme(ThemeStatus $theme): JsonResponse
    {
        return response()->json($theme->check($this->shop()));
    }

    /** After returning from Shopify's plan page: read the new subscription. */
    public function syncPlan(BillingSync $billing): JsonResponse
    {
        $billing->sync($this->shop());
        $shop = $this->shop()->refresh()->load('plan');

        return response()->json(['plan' => $shop->plan?->only(['handle', 'name'])]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['key' => 'required|string|max:40', 'value' => 'nullable']);
        $shop = $this->shop();
        $shop->forceFill(['onboarding' => array_merge($shop->onboarding ?? [], [$data['key'] => $data['value'] ?? true])])->save();

        return response()->json(['onboarding' => $shop->onboarding]);
    }
}
