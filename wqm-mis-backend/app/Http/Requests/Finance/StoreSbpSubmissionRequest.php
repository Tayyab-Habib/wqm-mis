<?php

namespace App\Http\Requests\Finance;

use App\Models\WaterSamples\WaterSampleInvoiceLog;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * F-05 / D-04 — SBP Submission input validation.
 *
 * Beyond field-shape, ensures every log_id is:
 *   • a real, undeleted payment log,
 *   • not already linked to another SBP submission,
 *   • a cash/cheque mode (Online/Bank Transfer settle into SBP differently
 *     and don't need a physical challan).
 */
class StoreSbpSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'log_ids'           => ['required', 'array', 'min:1'],
            'log_ids.*'         => ['required', 'integer'],
            'challan_no'        => ['required', 'string', 'max:64'],
            'deposit_date'      => ['required', 'date'],
            'period_from'       => ['nullable', 'date'],
            'period_to'         => ['nullable', 'date', 'after_or_equal:period_from'],
            'lab_id'            => ['required', 'integer', 'exists:laboratories,id'],
            'submitted_by_name' => ['nullable', 'string', 'max:128'],
            'remarks'           => ['nullable', 'string', 'max:500'],
            'attachment_path'   => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $ids = $this->input('log_ids', []);
            $logs = WaterSampleInvoiceLog::whereIn('id', $ids)->get([
                'id', 'sbp_submission_id', 'payment_mode',
            ]);
            if ($logs->count() !== count($ids)) {
                $v->errors()->add('log_ids', 'One or more payment logs could not be found.');
                return;
            }
            $alreadyBanked = $logs->filter(fn ($l) => !is_null($l->sbp_submission_id));
            if ($alreadyBanked->isNotEmpty()) {
                $v->errors()->add(
                    'log_ids',
                    'These logs have already been submitted to SBP: '
                    . $alreadyBanked->pluck('id')->implode(', ')
                );
            }
        });
    }
}
