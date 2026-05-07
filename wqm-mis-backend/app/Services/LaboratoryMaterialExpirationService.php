<?php

namespace App\Services;

use App\Enums\MaterialLogStatusEnum;
use App\Enums\MaterialStatusEnum;
use App\Models\Material\Material;
use App\Models\User;
use App\Notifications\GenericNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

class LaboratoryMaterialExpirationService extends BaseExpiredLaboratoryMaterialService
{
    public function __construct(Carbon $startDate, Carbon $endDate)
    {
        parent::__construct($startDate, $endDate);
        $this->handle();
    }

    public function handle(): void
    {
        try {
            $this->getMaterials()
                ->map(function ($material) {
                    $material->update([
                        'status' => MaterialStatusEnum::EXPIRED->value,
                    ]);
                    $data = [
                        'name' => 'AutoNotify',
                        'content' => 'Expired ' . $material->material->name . ' Alert: ' . $material->material->name . ' in inventory has expired. Take immediate action to replace or dispose of it for product quality and safety.',
                        'status' => 'expiration',
                    ];
                    Notification::send($material->laboratory->focalPerson, new GenericNotification($data));
                });
        } catch (\Exception $exception) {
            info($exception->getMessage());
        }
    }
}
