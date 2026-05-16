<?php

namespace App\Http\Requests\DiaryDispatch;

use Illuminate\Foundation\Http\FormRequest;

class DeleteDiaryDispatchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $enum = $this->route('enum');
        $enumValue = is_object($enum) ? ($enum->value ?? (string) $enum) : (string) $enum;
        $ability = $enumValue === 'dispatch' ? 'delete_dispatches' : 'delete_diaries';
        return auth()->user()?->can($ability) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            //
        ];
    }
}
