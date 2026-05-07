<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            RoleSeeder::class,
            ModuleSeeder::class,
            PermissionSeeder::class,
            AssignRolePermissionsSeeder::class,
            ProvinceSeeder::class,
            UnionCouncilSeeder::class,
            LaboratorySeeder::class,
            TestSeeder::class,
            ClientSeeder::class,
            UnitSeeder::class,
            ComplaintTypeSeeder::class,
        ]);

        if (App::isLocal()) {
            $this->call([
                DesignationSeeder::class,
                UserSeeder::class,
                AbbreviationSeeder::class,
                AssetSeeder::class,
                MaterialSeeder::class,
                SettingSeeder::class,
                WaterSchemesSeeder::class,
                WaterSampleSeeder::class,
                WaterSampleDetailSeeder::class,
                InvoiceSeeder::class,
                InvoiceDetailSeeder::class,
                PaymentSeeder::class,
                TermAndConditionSeeder::class,
                HandingTakingSeeder::class,
                FolderSeeder::class,
                DiaryDispatchSeeder::class
            ]);
        }
    }
}
