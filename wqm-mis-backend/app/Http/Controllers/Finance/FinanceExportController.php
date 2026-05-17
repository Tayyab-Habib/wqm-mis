<?php

namespace App\Http\Controllers\Finance;

use App\Exports\FinanceRevenueExport;
use App\Http\Controllers\Controller;
use App\Services\AuthScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Maatwebsite\Excel\Facades\Excel;

/**
 * F-18 — xlsx download endpoint for the Revenue Register.
 *
 * Filename obeys the SRS naming convention: Finance_<LabOrAll>_<MonYY>.xlsx
 * e.g. Finance_Peshawar_May26.xlsx
 */
class FinanceExportController extends Controller
{
    public function invoicesXlsx(Request $request)
    {
        // Revenue export crosses labs — gate on view_invoices so a custom
        // role granted invoice-view via the admin UI can also export. Falls
        // back to isUnscoped() for admin-tier users.
        $user = auth()->user();
        if (!$user || (!$user->isUnscoped() && !$user->can('view_invoices'))) {
            return response()->json([
                'message' => 'Not authorized to export finance data',
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }

        $filters = $request->only(['lab_id', 'client_id', 'date_from', 'date_to', 'status']);

        // RBAC scope clamp — a scoped user (lab-incharge / CE / SE / XEN /
        // analyst / clerk) cannot override their lab scope by passing
        // ?lab_id=X for someone else's lab. If they did pass an out-of-scope
        // lab_id, replace it with their own visible-lab set so the export
        // silently produces zero cross-lab data instead of leaking it.
        if (!$user->isUnscoped()) {
            $visible = AuthScope::visibleLabIds($user);
            if (!empty($filters['lab_id'])) {
                $requested = (int) $filters['lab_id'];
                if (!in_array($requested, array_map('intval', $visible), true)) {
                    // Forbidden lab requested — fall back to user's scope.
                    $filters['lab_ids'] = $visible;
                    unset($filters['lab_id']);
                }
            } else {
                // No lab_id supplied — limit to the user's visible labs so
                // the export can't return cross-lab rows.
                $filters['lab_ids'] = $visible;
            }
        }

        $monYY = Carbon::parse($filters['date_from'] ?? now())->format('M') . Carbon::parse($filters['date_from'] ?? now())->format('y');
        $labTag = $request->filled('lab_id') ? ('Lab' . $request->lab_id) : 'AllLabs';
        $filename = sprintf('Finance_%s_%s.xlsx', $labTag, $monYY);

        return Excel::download(new FinanceRevenueExport($filters), $filename);
    }
}
