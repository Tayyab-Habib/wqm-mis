<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LaboratoryExport implements FromCollection, WithHeadings
{

    protected $laboratories;

    public function __construct($laboratories)
    {
        $this->laboratories = $laboratories;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->laboratories->transform(function ($laboratory) {
            $laboratory->union_council_id = $laboratory->unionCouncil->name ?? '';
                $laboratory->tehsil_id = $laboratory->tehsil?->name ?? '';
                $laboratory->district_id = $laboratory->district?->name ?? '';
                $laboratory->division_id = $laboratory->division?->name ?? '';
                $laboratory->province_id = $laboratory->province?->name ?? '';
                $laboratory->focal_person_id = $laboratory->focalPerson?->name ?? '';
                $laboratory->created_by = $laboratory->createdByUser?->name ?? '';
                $laboratory->is_active = $laboratory->is_active ? 'Yes' : 'No';
            unset($laboratory->id);
            return $laboratory;
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
            'Phone',
            'Fax',
            'Email',
            'Address',
            'Created By',
            'Focal Person',
            'Logo',
            'Is Active',
            'Union Council',
            'Tehsil',
            'District',
            'Division',
            'Province',
        ];
    }
}
