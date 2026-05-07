<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class WaterSchemeExport implements FromCollection, withHeadings
{
    protected $waterSchemes;

    public function __construct($waterSchemes)
    {
        $this->waterSchemes = $waterSchemes;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->waterSchemes->transform(function ($waterScheme) {
            $waterScheme->union_council_id = $waterScheme->unionCouncil?->name ?? '';
            $waterScheme->tehsil_id = $waterScheme->tehsil?->name ?? '';
            $waterScheme->district_id = $waterScheme->district?->name ?? '';
            $waterScheme->division_id = $waterScheme->division?->name ?? '';
            $waterScheme->province_id = $waterScheme->province?->name ?? '';
            $waterScheme->created_by = $waterScheme->createdByUser?->name ?? '';
            $waterScheme->is_active = $waterScheme->is_active ? 'Yes' : 'No';
            $waterScheme->remarks = $waterScheme->remarks ?? '';
            $waterScheme->source_type = $waterScheme->source_type ?? '';
            $waterScheme->years_of_installation = $waterScheme->years_of_installation ?? '';
            $waterScheme->mode = $waterScheme->mode ?? '';
            $waterScheme->operation = $waterScheme->operation ?? '';
            $waterScheme->type_of_machine = $waterScheme->type_of_machine ?? '';
            $waterScheme->horse_power_motor = $waterScheme->horse_power_motor ?? '';
            $waterScheme->storage = $waterScheme->storage ?? '';
            $waterScheme->capacity = $waterScheme->capacity ?? '';
            $waterScheme->depth = $waterScheme->depth ?? '';
            $waterScheme->population = $waterScheme->population ?? '';
            $waterScheme->chamber = $waterScheme->chamber ?? '';
            $waterScheme->pipe_type = $waterScheme->pipe_type ?? '';

            unset($waterScheme->id);
            return $waterScheme;
        });
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Name',
            'Latitude',
            'Longitude',
            'Slug',
            'Address',
            'Created By',
            'Is Active',
            'Source Type',
            'Years Of Installation',
            'Mode',
            'Operation',
            'Type Of Machine',
            'Horse Power Motor',
            'Storage',
            'Capacity',
            'Depth',
            'Population',
            'Chamber',
            'Pipe Type',
            'Remarks',
            'Union Council',
            'Tehsil',
            'District',
            'Division',
            'Province',
        ];
    }
}
