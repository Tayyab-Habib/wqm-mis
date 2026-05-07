<?php

namespace App\Services;

use App\Enums\MaterialLogStatusEnum;
use App\Models\Material\Material;
use App\Models\User;
use App\Notifications\GenericNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

class NotifyExpiredMaterialService extends BaseExpiredMaterialService
{
    public function __construct(Carbon $startDate, Carbon $endDate)
    {
        parent::__construct($startDate, $endDate);
        $this->handle();
    }

    public function handle(): void
    {
        try {
            $systemAdministrators = $this->getSystemAdministrators();

            $this->getMaterials()
                ->map(function ($material) use ($systemAdministrators) {
                    $data = [
                        'name' => 'AutoNotify',
                        'content' => 'Expiration notice: The ' . $material->name . ' will expire tomorrow. Ensure timely disposal or replacement',
                        'status' => 'expiration',
                    ];
                    Notification::send($systemAdministrators, new GenericNotification($data));
                });
        } catch (\Exception $exception) {
            info($exception->getMessage());
        }
    }
}
