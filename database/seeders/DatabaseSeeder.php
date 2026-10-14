<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SchoolProfileSeeder::class,
        ]);

        if (config('epsv.seed_demo')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
