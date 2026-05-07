<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\WaterScheme;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class WaterSchemesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $waterSchemesJson = Storage::disk('public')
            ->get('/water-supply-schemes.json');

        collect(json_decode($waterSchemesJson))
            ->groupBy('district_name')
            ->map(function ($waterSchemes, $key) {
                $district = District::query()
                    ->where('name', '=', $key)
                    ->with('division')
                    ->withExists('waterSchemes as has_water_schemes')
                    ->first();

                if ($district && !$district?->has_water_schemes) {
                    collect($waterSchemes)->map(fn($waterScheme) => WaterScheme::query()->create([
                        'name' => $waterScheme->name,
                        'latitude' => $waterScheme->latitude,
                        'longitude' => $waterScheme->longitude,
                        'is_active' => true,
                        'source_type' => $waterScheme->source_type,
                        'mode' => $waterScheme->mode,
                        'operation' => $waterScheme->operation,
                        'type_of_machine' => $waterScheme->type_of_machine,
                        'district_id' => $district->id,
                        'division_id' => $district->division_id,
                        'province_id' => $district->division->province_id,
                    ]));
                }
            });
    }
}
