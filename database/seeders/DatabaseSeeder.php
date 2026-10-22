<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** php artisan db:seed — safe to run on every deploy; seeders only create what is missing. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            RoleSeeder::class,
        ]);
    }
}
