<?php

namespace Database\Seeders;

use App\Enums\InvoiceableTypeEnum;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if (0 === Unit::count()) {

            $units = [
                ['name'=> 'No.', 'type' => InvoiceableTypeEnum::STOCK->value],
                ['name'=> 'mg', 'type' => InvoiceableTypeEnum::STOCK->value],
                ['name'=> 'Kg', 'type' => InvoiceableTypeEnum::STOCK->value],
                ['name'=> 'ml', 'type' => InvoiceableTypeEnum::STOCK->value],
                ['name'=> 'L', 'type' => InvoiceableTypeEnum::STOCK->value],
                ['name'=> 'Bag', 'type' => InvoiceableTypeEnum::INVENTORY->value],
                ['name'=> 'Pack','type' => InvoiceableTypeEnum::INVENTORY->value],
                ['name'=> 'Bottle','type' => InvoiceableTypeEnum::INVENTORY->value],
                ['name'=> 'Cent','type' => InvoiceableTypeEnum::INVENTORY->value],
                ['name'=> 'Dozen','type' => InvoiceableTypeEnum::INVENTORY->value],
            ];

            Unit::query()->insert($units);
        }
    }
}
