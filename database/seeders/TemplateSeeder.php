<?php

namespace Database\Seeders;

use App\Models\Template;
use App\Support\StarterTemplates;
use Illuminate\Database\Seeder;

/** Publishes the starter templates once; super admin manages versions afterwards. */
class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (StarterTemplates::all() as $i => $t) {
            $template = Template::query()->firstOrCreate(['key' => $t['key']], [
                'name' => $t['name'], 'category' => $t['category'], 'description' => $t['description'],
                'min_plan' => $t['min_plan'], 'featured' => $t['featured'], 'sort' => $i, 'status' => 'published',
            ]);
            if (! $template->current_version_id) {
                $version = $template->versions()->create(['version' => 1, 'definition' => $t['definition'], 'changelog' => 'Initial release', 'rollout' => 'all', 'published_at' => now()]);
                $template->forceFill(['current_version_id' => $version->id])->save();
            }
        }
    }
}
