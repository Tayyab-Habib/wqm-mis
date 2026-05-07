<?php

namespace App\Http\Requests\Search;

use App\Enums\IssueStatusEnum;
use App\Enums\IssueTypeEnum;
use App\Models\Asset\Asset;
use App\Models\Complaint;
use App\Models\Laboratories\Laboratory;
use App\Models\Material\Material;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchIssueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return !!auth()?->id();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'issuable_type' => ['nullable', Rule::enum(IssueTypeEnum::class)],
            'status' => ['nullable', Rule::enum(IssueStatusEnum::class)],
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'issuable_class' => match ($this->issuable_type) {
                IssueTypeEnum::INVENTORY => Asset::class,
                IssueTypeEnum::COMPLAINT => Complaint::class,
                IssueTypeEnum::LABORATORY => Laboratory::class,
                IssueTypeEnum::STOCK => Material::class,
                default => null,
            },
        ]);
    }
}
