<?php

namespace App\Enums;

use App\Traits\ArrayableEnum;

enum CollectableTypeEnum: string
{
    use ArrayableEnum;

    case PHE = 'PHE';
    case PRIVATE = 'Private';
    // PT = Proficiency Testing. External blind samples sent by PCRWR / PCSIR /
    // other QC providers. They are NOT bound to a WSS (no water_scheme_id)
    // and NOT bound to a Client either — the lab staff that received them
    // becomes the polymorphic collectable. The durable distinction lives in
    // water_samples.sample_kind (the polymorphic collectable_type column is
    // still User::class for PT, same as PHE).
    case PT = 'PT';
}
