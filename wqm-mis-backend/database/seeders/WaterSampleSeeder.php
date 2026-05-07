<?php

namespace Database\Seeders;

use App\Enums\CollectedByEnum;
use App\Enums\CollectedInEnum;
use App\Enums\DesiredTestEnum;
use App\Enums\ReasonForTestingEnum;
use App\Enums\SamplingPointEnum;
use App\Enums\SourceTypeEnum;
use App\Enums\TestFrequencyEnum;
use App\Enums\WaterSampleStatusEnum;
use App\Models\Client;
use App\Models\District;
use App\Models\Division;
use App\Models\Laboratories\Laboratory;
use App\Models\Province;
use App\Models\Tehsil;
use App\Models\UnionCouncil;
use App\Models\User;
use App\Models\WaterSamples\WaterSample;
use App\Models\WaterScheme;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WaterSampleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // real data
        $waterSample = [
            'test_type' => TestFrequencyEnum::FRESH,
            'qr_code' => 'test qr-code',
            'water_scheme_id' => '1',
            'source_type' => SourceTypeEnum::GRAVITY,
            'sampling_point' => SamplingPointEnum::SOURCE,
            'collected_by' => CollectedByEnum::CLIENT,
            'latitude' => '39.70',
            'longitude' => '23.45',
            'status' => WaterSampleStatusEnum::NEW,
            'temperature_in_celsius' => '31',
            'sampled_at' => '2022-01-13 02:16:00',
            'analyzed_at' => '2022-01-13 02:16:00',
            'reported_at' => '2022-01-15 02:16:00',
            'collected_in' => CollectedInEnum::KIT,
            'complaint' => ReasonForTestingEnum::GENERAL_Q_ANALYSIS,
            'desired_test' => DesiredTestEnum::Physical,
            'laboratory_id' => '1',
            'union_council_id' => null,
            'tehsil_id' => null,
            'district_id' => '26',
            'division_id' => '5',
            'province_id' => '1',
            'remarks' => null,
            'collectable_id' => '1',
            'collectable_type' => 'App\Models\User'
        ];

        try {
            DB::beginTransaction();
            WaterSample::query()->create($waterSample);
            DB::commit();
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            DB::rollBack();
        }

        // fake data
        $waterSchemeIds = WaterScheme::query()->select('id')->pluck('id')->toArray();
        $laboratoryIds = Laboratory::query()->select('id')->pluck('id')->toArray();
        $unionCouncilIds = UnionCouncil::query()->select('id')->pluck('id')->toArray();
        $tehsilIds = Tehsil::query()->select('id')->pluck('id')->toArray();
        $districtIds = District::query()->select('id')->pluck('id')->toArray();
        $divisionIds = Division::query()->select('id')->pluck('id')->toArray();
        $provinceIds = Province::query()->select('id')->pluck('id')->toArray();
        $labInchargeIds = User::query()->select('id')->pluck('id')->toArray();
        $researchOfficerIds = User::query()->select('id')->pluck('id')->toArray();

        $userIds = User::query()->select('id')->pluck('id')->toArray();
        $clientIds = Client::query()->select('id')->pluck('id')->toArray();

        if (1 === WaterSample::query()->count()) {
            WaterSample::factory(10)
                ->sequence(fn($sequence) => ['water_scheme_id' => $waterSchemeIds[array_rand($waterSchemeIds)]])
                ->sequence(fn($sequence) => ['laboratory_id' => $laboratoryIds[array_rand($laboratoryIds)]])
                ->sequence(fn($sequence) => ['union_council_id' => count($unionCouncilIds) === 0 ? null : $unionCouncilIds[array_rand($unionCouncilIds)]])
                ->sequence(fn($sequence) => ['tehsil_id' => $tehsilIds[array_rand($tehsilIds)]])
                ->sequence(fn($sequence) => ['district_id' => $districtIds[array_rand($districtIds)]])
                ->sequence(fn($sequence) => ['division_id' => $divisionIds[array_rand($divisionIds)]])
                ->sequence(fn($sequence) => ['province_id' => $provinceIds[array_rand($provinceIds)]])
                ->sequence(fn($sequence) => ['lab_incharge_id' => $labInchargeIds[array_rand($labInchargeIds)]])
                ->sequence(fn($sequence) => ['research_officer_id' => $researchOfficerIds[array_rand($researchOfficerIds)]])
                ->sequence(function ($sequence) use ($userIds, $clientIds) {
                    $collectableType = fake()->randomElement([User::class, Client::class]);
                    return [
                        'collectable_type' => $collectableType,
                        'collectable_id' => $collectableType === User::class
                            ? $userIds[array_rand($userIds)]
                            : $clientIds[array_rand($clientIds)],
                    ];
                })
                ->create();
        }
    }
}
