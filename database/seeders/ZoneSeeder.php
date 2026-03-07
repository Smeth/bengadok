<?php

namespace Database\Seeders;

use App\Models\Zone;
use Illuminate\Database\Seeder;

class ZoneSeeder extends Seeder
{
    public function run(): void
    {
        $zonesData = [
            ['designation' => 'Moungali', 'latitude' => -4.2720, 'longitude' => 15.2780],
            ['designation' => 'Poto-Poto', 'latitude' => -4.2667, 'longitude' => 15.2789],
            ['designation' => 'Bacongo', 'latitude' => -4.2911, 'longitude' => 15.2611],
            ['designation' => 'Makélékélé', 'latitude' => -4.2806, 'longitude' => 15.2839],
            ['designation' => 'Ovenzé', 'latitude' => -4.2500, 'longitude' => 15.2700],
            ['designation' => 'Mfilou', 'latitude' => -4.2900, 'longitude' => 15.2400],
        ];
        foreach ($zonesData as $z) {
            Zone::updateOrCreate(['designation' => $z['designation']], $z);
        }
    }
}
