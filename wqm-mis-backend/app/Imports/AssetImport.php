<?php

namespace App\Imports;

use App\Enums\AssetLogStatusEnum;
use App\Enums\AssetStatusEnum;
use App\Models\Asset\Asset;
use App\Models\Asset\AssetLog;
use App\Models\Laboratories\Laboratory;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class AssetImport implements ToModel, WithHeadingRow, WithValidation
{
    /**
     * @param array $row
     *
     * @return Model|null
     */
    public function model(array $row)
    {
        $specification = 'Make:' . $row['make']
            . ' \nModel: ' . $row['model']
            . ' \nSerial No: ' . $row['serial_numbers']
            . ' \nCalibrations: ' . $row['calibration']
            . ' \nService: ' . $row['service'];

        $asset = Asset::firstOrCreate(
            [
                'name' => $row['name'] ?? 'N/A'
            ],
            [
                'quantity' => $row['quantity'],
                'unit' => $row['unit'],
                'status' => trim($row['status']),
                'specification' => $specification,
                'country' => $row['country'] ?? 'N/A',
                'agency' => $row['agency'] ?? 'N/A',
            ]
        );
        $authUser = auth()->user();
        $assetLog = AssetLog::query()
            ->create([
                'asset_id' => $asset->id,
                'user_id' => $authUser->id,
                'quantity' => $asset->quantity,
                'unit' => $asset->unit,
                'date_of_entry' => now()->format('Y-m-d'),
                'status' => AssetLogStatusEnum::IN->value,
            ]);

        $districtName = trim($row['laboratory']);
        $laboratory = Laboratory::query()
            ->whereHas('district', function ($query) use ($districtName) {
                $query->where('name', '=', $districtName);
            })
            ->first();

        if ($laboratory) {
            // insert a new record with 'out' status in asset Log.
            AssetLog::query()->create([
                'asset_id' => $asset->id,
                'user_id' => $authUser->id,
                'quantity' => -($asset->quantity),
                'unit' => $asset->unit,
                'date_of_entry' => now()->format('Y-m-d'),
                'status' => AssetLogStatusEnum::OUT->value,
            ]);

            //add asset to laboratory
            $laboratoryAsset = $laboratory->laboratoryAssets()
                ->create([
                    'asset_id' => $assetLog->asset_id,
                    'quantity' => $assetLog->quantity,
                    'unit' => $assetLog->unit,
                    'date_of_expiry' => $assetLog->date_of_expiry,
                    'status' => AssetStatusEnum::ACTIVE,
                ]);

            //add laboratory asset log
            $laboratoryAsset->laboratoryAssetLogs()
                ->create([
                    'asset_log_id' => $assetLog->id,
                    'quantity' => $assetLog->quantity,
                    'unit' => $assetLog->unit,
                    'status' => AssetLogStatusEnum::IN,
                ]);
        }

        return $asset;
    }

    public function rules(): array
    {
        return [
            'name' => ['required'],
            'quantity' => ['required'],
            'status' => ['required']
        ];
    }
}
