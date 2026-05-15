<?php

namespace App\Http\Requests\Finance;

use App\Models\WaterSamples\WaterSampleInvoice;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * F-08 / F-13 / F-15 — Clubbed Invoice input validation.
 *
 * Field-level rules cover shape (min 2 invoice ids, valid client). Cross-row
 * business rules (same client + same lab, not-already-clubbed) are enforced
 * in withValidator() so we can short-circuit with a single 422 instead of
 * letting the controller crash later.
 */
class StoreClubbedInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Clubbed-invoice creation is an invoice write — gate on add_invoices
        // so an admin can grant the perm to custom finance roles via the UI.
        return $this->user()?->can('add_invoices') ?? false;
    }

    public function rules(): array
    {
        return [
            'invoice_ids'    => ['required', 'array', 'min:2'],
            'invoice_ids.*'  => [
                'required',
                'integer',
                Rule::exists('water_sample_invoices', 'id')->whereNull('deleted_at'),
            ],
            'client_id'      => ['required', 'integer'],
            'client_type'    => ['required', 'string', 'max:255'],
            'period_from'    => ['nullable', 'date'],
            'period_to'      => ['nullable', 'date', 'after_or_equal:period_from'],
            'description'    => ['nullable', 'string', 'max:500'],
            'invoice_date'   => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return; // shape failures first
            }

            $ids        = $this->input('invoice_ids', []);
            $clientId   = $this->input('client_id');
            $clientType = $this->input('client_type');

            // Pull all candidate invoices with the columns needed for the
            // cross-row business rules in one query.
            $rows = WaterSampleInvoice::query()
                ->whereIn('id', $ids)
                ->with(['waterSample:id,laboratory_id'])
                ->get([
                    'id', 'water_sample_id', 'invoiceable_id', 'invoiceable_type',
                    'is_clubbed', 'clubbed_invoice_id', 'status', 'deleted_at',
                ]);

            if ($rows->count() !== count($ids)) {
                $v->errors()->add('invoice_ids', 'One or more invoices could not be found.');
                return;
            }

            // F-13 — none of the constituents may already be clubbed.
            $alreadyClubbed = $rows->filter(fn ($r) => !is_null($r->clubbed_invoice_id) || $r->is_clubbed);
            if ($alreadyClubbed->isNotEmpty()) {
                $v->errors()->add(
                    'invoice_ids',
                    'These invoices are already part of a clubbed invoice: '
                    . $alreadyClubbed->pluck('id')->implode(', ')
                );
            }

            // F-15a — same client across all rows AND must match request client_id.
            $clients = $rows->map(fn ($r) => $r->invoiceable_type . ':' . $r->invoiceable_id)->unique();
            if ($clients->count() > 1) {
                $v->errors()->add('invoice_ids', 'All invoices must belong to the same client.');
            } else {
                $first = $rows->first();
                if ($first && ($first->invoiceable_id != $clientId || $first->invoiceable_type !== $clientType)) {
                    $v->errors()->add('client_id', 'invoice_ids do not belong to the supplied client_id/client_type.');
                }
            }

            // F-15b — same laboratory across all rows.
            $labs = $rows->map(fn ($r) => $r->waterSample?->laboratory_id)->filter()->unique();
            if ($labs->count() > 1) {
                $v->errors()->add('invoice_ids', 'All invoices must originate from the same laboratory.');
            }
            if ($labs->isEmpty()) {
                $v->errors()->add('invoice_ids', 'Could not resolve laboratory for any invoice — clubbing requires lab attribution.');
            }
        });
    }
}
