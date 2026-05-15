<?php

namespace App\Http\Requests\DiaryDispatch;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDiaryDispatchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // Enum-aware: only require the permission for the resource type being
        // updated. The original code used can([a, b]) which Laravel evaluates
        // as "user must have BOTH abilities" — wrong for our case.
        $enum = $this->route('enum');
        $enumValue = is_object($enum) ? ($enum->value ?? (string) $enum) : (string) $enum;
        $ability = $enumValue === 'dispatch' ? 'edit_dispatches' : 'edit_diaries';
        return auth()->user()?->can($ability) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        // All fields nullable to allow partial PATCH-style updates from the UI
        // (e.g. the "Done" button only sends { action_status: 'Completed' }).
        // Mirrors StoreDiaryDispatchRequest plus the SRS workflow statuses.
        return [
            'subject'              => ['nullable', 'string', 'max:255'],
            'person_name'          => ['nullable', 'string', 'max:255'],
            'date_on_letter'       => ['nullable', 'date', 'date_format:Y-m-d'],
            'attachment_name'      => ['nullable', 'string', 'max:255'],
            'attachment'           => ['nullable', 'file', 'mimetypes:application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/msword,image/jpeg,image/png,image/jpg', 'max:2048'],
            'designation_id'       => ['nullable', 'integer', 'exists:designations,id'],
            'folder_id'            => ['nullable', 'integer', 'exists:folders,id'],
            // Shared SRS fields
            'reference_no'         => ['nullable', 'string', 'max:255'],
            'category'             => ['nullable', 'string', 'max:255'],
            'priority'             => ['nullable', 'string', 'in:Routine,Urgent,Immediate'],
            'remarks'              => ['nullable', 'string'],
            // Diary (Inward) fields
            'from_sender'          => ['nullable', 'string', 'max:255'],
            'addressed_to'         => ['nullable', 'string', 'max:255'],
            'action_required'      => ['nullable', 'boolean'],
            'action_due_date'      => ['nullable', 'date', 'date_format:Y-m-d'],
            'action_taken'         => ['nullable', 'string'],
            // Combined status values: Pending/In Progress/Completed cover Diary,
            // Draft/Sent cover Dispatch.
            'action_status'        => ['nullable', 'string', 'in:Pending,In Progress,Completed,Draft,Sent'],
            // Dispatch (Outward) fields
            'to_recipient'         => ['nullable', 'string', 'max:255'],
            'reference_diary_no'   => ['nullable', 'string', 'max:255'],
            'mode_of_dispatch'     => ['nullable', 'string', 'in:Hand Delivery,Post,Courier,Email,Fax'],
            'dispatch_reference_no'=> ['nullable', 'string', 'max:255'],
            'prepared_by'          => ['nullable', 'string', 'max:255'],
            'dispatched_by'        => ['nullable', 'string', 'max:255'],
        ];
    }
}
