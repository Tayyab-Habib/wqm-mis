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
            // RBAC extension: add SRS §1.2 roles missing from RoleSeeder,
            // then assign them their fine-grained permission bundles.
            RbacRolesExpansionSeeder::class,
            RbacRolePermissionsSeeder::class,
            ProvinceSeeder::class,
            UnionCouncilSeeder::class,
            LaboratorySeeder::class,
            // PHE hierarchy from PHE Heirarchy (3).xlsx — canonicalises the
            // names + FKs of regions/divisions/circles/districts/phed_divisions/
            // sub_divisions/laboratories to match the production xlsx exactly.
            // MUST run after the legacy locality seeders so we can rename
            // existing rows in-place rather than creating duplicates.
            PheHierarchySeeder::class,
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
                DiaryDispatchSeeder::class,
                // RBAC test users (one per role) — local only, predictable creds for testing
                RbacTestUsersSeeder::class,
            ]);
        }
    }
}
