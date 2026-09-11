<?php

namespace Database\Seeders;

use App\Domain\Settings\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Create the single settings row; the column defaults carry the starting values.
     */
    public function run(): void
    {
        if (! Setting::query()->exists()) {
            Setting::query()->create();
        }
    }
}
