<?php

namespace App\Http\Controllers\Finance;

use App\Exports\FinanceRevenueExport;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
        $filters = $request->only(['lab_id', 'client_id', 'date_from', 'date_to', 'status']);

        $monYY = Carbon::parse($filters['date_from'] ?? now())->format('M') . Carbon::parse($filters['date_from'] ?? now())->format('y');
        $labTag = $request->filled('lab_id') ? ('Lab' . $request->lab_id) : 'AllLabs';
        $filename = sprintf('Finance_%s_%s.xlsx', $labTag, $monYY);

        return Excel::download(new FinanceRevenueExport($filters), $filename);
    }
}
