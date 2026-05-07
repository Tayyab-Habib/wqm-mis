<?php

namespace Database\Seeders;

use App\Models\Laboratories\Laboratory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $roleNames = Role::query()
            ->select('name')
            ->orderBy('id')
            ->pluck('name')
            ->toArray();

        DB::beginTransaction();

        $index = 0;
        foreach ($roleNames as $roleName) {
            User::factory(1)
                ->create([
                    'name' => ucwords(str_replace('-', ' ', $roleName)),
                    'email' => str_replace('-', '.', $roleName) . '@mis.com',
                    'password' => bcrypt('Wqm+Mis1=2'),
                    'district_id' => 1
                ])
                ->each(function ($user, int $key) use ($roleName, $index) {
                    $laboratory = Laboratory::class;

                    if ($index === 0) {
                        $laboratory = $laboratory::factory(1)->create([
                            'focal_person_id' => $user->id,
                        ])[0];
                    } else {
                        $laboratory = $laboratory::first();
                    }

                    $user->assignRole($roleName);
                    $user->laboratories()->sync([$laboratory->id => ['present_duty' => $user->name]]);
                });
            $index++;
        }
        DB::commit();
    }
}
