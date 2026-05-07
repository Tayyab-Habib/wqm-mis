<?php

namespace App\Services;

use App\Enums\MaterialLogStatusEnum;
use App\Enums\MaterialStatusEnum;
use App\Models\Material\Material;
use App\Models\User;
use App\Notifications\GenericNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

class BaseExpiredMaterialService
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
        return Material::query()
            ->whereIn('status', [
                MaterialStatusEnum::ACTIVE->value,
                MaterialStatusEnum::BELOW_THRESHOLD->value
            ])
            ->select(['id', 'name'])
            ->whereHas('materialLogs', fn(Builder $query) => $query->where('status', '=', MaterialLogStatusEnum::IN->value)
                ->whereBetween('date_of_expiry', [$this->startDate->format('Y-m-d'), $this->endDate->format('Y-m-d')])
            )
            ->get();
    }

    public function getSystemAdministrators(): Collection
    {
        return User::query()
            ->whereHas('roles', fn(Builder $query) => $query->where('name', '=', 'system-administrator'))
            ->get();
    }
}
