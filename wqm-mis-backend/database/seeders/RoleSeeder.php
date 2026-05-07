<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $roles = [
            [
                'name' => 'system-administrator',
                'guard_name' => 'web',
                'created_at' => now()
            ],
            [
                'name' => 'system-manager',
                'guard_name' => 'web',
                'created_at' => now()
            ],
            [
                'name' => 'junior-clerk',
                'guard_name' => 'web',
                'created_at' => now()
            ],
            [
                'name' => 'laboratory-assistant',
                'guard_name' => 'web',
                'created_at' => now()
            ],
        ];

        if (0 === Role::query()->count()) {
            Role::query()->insert($roles);
        }
    }
}
