<?php

namespace Database\Seeders;

use App\Models\Config;
use Illuminate\Database\Seeder;

class ConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run() {
        Config::insert([
            'nominatim_is_busy' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
