<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

/**
 * F-03 — Record Payment input validation.
 *
 * Enforces:
 *   • amount > 0 (gt:0)
 *   • payment_mode strictly in the SRS allow-list
 *   • payment_date (Date of Receipt) — defaults to today if omitted
 *   • receipt_no (Receipt/Cheque Number) — required when mode is Cheque
 *   • received_by — free-text name of the receiver, optional but stored
 */
class RecordPaymentRequest extends FormRequest
{
    public const ALLOWED_MODES = ['Cash', 'Cheque', 'Bank Transfer', 'Online'];

    public function authorize(): bool
    {
        // Recording a payment is a finance write — gate on add_payments so a
        // custom role granted that perm via the admin UI can record without
        // a code change. Without this check, any authenticated user could
        // record arbitrary payments.
        return $this->user()?->can('add_payments') ?? false;
    }

    public function rules(): array
    {
        return [
            'amount'       => ['required', 'numeric', 'gt:0'],
            'payment_mode' => ['required', 'string', 'in:' . implode(',', self::ALLOWED_MODES)],
            'payment_date' => ['nullable', 'date', 'before_or_equal:today'],
            'receipt_no'   => [
                'nullable',
                'string',
                'max:64',
                // F-03: Cheque payments must record the cheque number.
                'required_if:payment_mode,Cheque',
            ],
            'received_by'  => ['nullable', 'string', 'max:128'],
            'remarks'      => ['nullable', 'string', 'max:500'],
            // Backwards-compat alias: old callers used `reference` for what
            // SRS now names `receipt_no`. Both are accepted.
            'reference'    => ['nullable', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_mode.in' => 'Payment mode must be one of: ' . implode(', ', self::ALLOWED_MODES) . '.',
            'receipt_no.required_if' => 'A cheque number is required when payment mode is Cheque.',
        ];
    }
}
