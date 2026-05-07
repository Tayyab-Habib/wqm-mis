<?php

namespace App\Imports;

use App\Models\District;
use App\Models\WaterScheme;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;


class WaterScehemeImport implements ToModel, WithHeadingRow, WithValidation
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        $district = District::query()
            ->select(['id', 'division_id'])
            ->where('name', '=', trim($row['district_name']))
            ->first();

        return new WaterScheme([
            'name' => $row['name'],
            'latitude' => $row['latitude'],
            'longitude' => $row['longitude'],
            'address' => $row['address'],
            'district_id' => $district->id ?? 1,
            'division_id' => $district->division_id ?? 1,
            'province_id' => 1,
            'source_type' => $row['source_type'],
            'years_of_installation' => $row['years_of_installation'],
            'mode' => $row['mode'],
            'operation' => $row['operation'],
            'type_of_machine' => $row['type_of_machine'],
            'horse_power_motor' => $row['horse_power_motor'],
            'storage' => $row['storage'],
            'capacity' => $row['capacity'],
            'depth' => $row['depth'],
            'chamber' => $row['chamber'],
            'pipe_type' => $row['pipe_type'],
            'remarks' => $row['remarks'],
        ]);
    }
    public function rules(): array
    {
        return [
            'name' => ['required'],
            'latitude' => ['required'],
            'longitude' => ['required'],
            'address' => ['required'],
            'district_name' => ['required'],
        ];
    }
}
