<?php

namespace App\Services;

use App\Enums\MaterialLogStatusEnum;
use App\Enums\MaterialStatusEnum;
use App\Models\Material\LaboratoryMaterial;
use App\Models\Material\LaboratoryMaterialLog;
use App\Models\Material\Material;
use App\Models\User;
use App\Notifications\GenericNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

class BaseExpiredLaboratoryMaterialService
{
    protected Carbon $startDate;
    protected Carbon $endDate;

    public function __construct(Carbon $startDate, Carbon $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function getMaterials(): Collection
    {
        return LaboratoryMaterial::query()
            ->whereIn('status', [
                MaterialStatusEnum::ACTIVE->value,
                MaterialStatusEnum::BELOW_THRESHOLD->value
            ])
            ->with([
                'material',
                'laboratory:id,name' => [
                    'focalPerson:id,name'
                ],
            ])
            ->whereHas('laboratoryMaterialLogs', fn(Builder $query) => $query->where('status', '=', MaterialLogStatusEnum::IN->value)
                ->whereBetween('date_of_expiry', [$this->startDate->format('Y-m-d'), $this->endDate->format('Y-m-d')])
            )
            ->get();
    }
}
