<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Support\PlanCatalog;
use Illuminate\Database\Seeder;

/** Creates the four plans once. Existing plans keep whatever super admin has changed. */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PlanCatalog::defaults() as $plan) {
            Plan::query()->firstOrCreate(['handle' => $plan['handle']], $plan + ['is_public' => true]);
        }
    }
}
