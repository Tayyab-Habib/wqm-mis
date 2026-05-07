<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class UserExport implements FromCollection, WithHeadings
{
    protected $users;

    public function __construct($users)
    {
        $this->users = $users;
    }

    /**
     * @return Collection
     */
    public function collection(): Collection
    {
        return $this->users->transform(function ($user) {
            $user->date_of_joining = $user->date_of_joining ?? '';
            $user->district_id = $user->district->name ?? '';
            $user->designation_id = $user->designation?->name ?? '';
            $user->created_by = $user->createdByUser?->name ?? '';
            $user->is_active = $user->is_active ? 'Yes' : 'No';


            $division = $user->district?->division?->name ?? '';

            $user->setAttribute('Division', $division);

            unset($user->id);
            return $user;
        });
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Name',
            'Email',
            'Phone',
            'Image',
            'Gender',
            'Date Of Birth',
            'Date Of Joining',
            'Is Active',
            'Employee Status',
            'Created By',
            'Career Background',
            'Educational Background',
            'Basic Pay Scale',
            'Designation',
            'District',
            'Division',
        ];
    }
}
