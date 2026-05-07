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

class MaterialExpirationService extends BaseExpiredMaterialService
{
    public function __construct(Carbon $startDate, Carbon $endDate)
    {
        parent::__construct($startDate, $endDate);
        $this->handle();
    }

    /**
     * Notify the expired materials to related laboratories focal persons.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return void
     */
    public function handle(): void
    {
        try {
            $systemAdministrators = $this->getSystemAdministrators();

            $this->getMaterials()
                ->map(function ($material) use ($systemAdministrators) {
                    $material->update([
                        'status' => MaterialStatusEnum::EXPIRED->value,
                    ]);
                    $data = [
                        'name' => 'AutoNotify',
                        'content' => 'Expired ' . $material->name . ' Alert: ' . $material->name . ' in inventory has expired. Take immediate action to replace or dispose of it for product quality and safety.',
                        'status' => 'expiration',
                    ];
                    Notification::send($systemAdministrators, new GenericNotification($data));
                });
        } catch (\Exception $exception) {
            info($exception->getMessage());
        }
    }
}
