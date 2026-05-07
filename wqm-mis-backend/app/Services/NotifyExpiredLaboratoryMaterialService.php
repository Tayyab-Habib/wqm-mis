<?php

namespace App\Services;

use App\Enums\MaterialLogStatusEnum;
use App\Models\Material\LaboratoryMaterial;
use App\Models\Material\LaboratoryMaterialLog;
use App\Models\Material\Material;
use App\Models\User;
use App\Notifications\GenericNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

class NotifyExpiredLaboratoryMaterialService extends BaseExpiredLaboratoryMaterialService
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
                    $data = [
                        'name' => 'AutoNotify',
                        'content' => 'Expiration notice: The ' . $material->material->name . ' will expire tomorrow. Ensure timely disposal or replacement',
                        'status' => 'expiration',
                    ];
                    Notification::send($material->laboratory->focalPerson, new GenericNotification($data));
                });
        } catch (\Exception $exception) {
            info($exception->getMessage());
        }
    }
}
