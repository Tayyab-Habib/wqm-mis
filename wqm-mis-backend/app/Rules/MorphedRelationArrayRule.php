<?php

namespace App\Rules;

use App\Enums\IssueTypeEnum;
use App\Models\Asset\Asset;
use App\Models\Material\Material;
use Illuminate\Contracts\Validation\Rule;

class MorphedRelationArrayRule implements Rule
{
    private int $index;
    private string $attributableType;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {

    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $explodeAttribute = explode('.', $attribute);
        $this->index = $explodeAttribute[1];
        $this->attributableType = str_replace('id', 'type', $explodeAttribute[2]);

        switch (request()['details'][$this->index][$this->attributableType]) {
            case IssueTypeEnum::STOCK->value:
                $isValid = Material::query()
                    ->where('id', '=', $value)
                    ->exists();
                break;
            case IssueTypeEnum::INVENTORY->value:
                $isValid = Asset::query()
                    ->where('id', '=', $value)
                    ->exists();
                break;
        }

        return $isValid;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The selected ' . request()['details'][$this->index][$this->attributableType] . ' is invalid.';
    }
}
