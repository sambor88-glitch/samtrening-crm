<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. Safe to run again — every seeder skips what already exists.
     */
    public function run(): void
    {
        $this->call([
            OwnerSeeder::class,
            SettingsSeeder::class,
            MessageTemplateSeeder::class,
        ]);
    }
}
