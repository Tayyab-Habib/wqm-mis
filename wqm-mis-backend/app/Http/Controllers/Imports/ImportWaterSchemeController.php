<?php

namespace App\Http\Controllers\Imports;

use App\Http\Controllers\Controller;
use App\Imports\WaterScehemeImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ImportWaterSchemeController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        Excel::import(new WaterScehemeImport(), $request->file('water-schemes'),  null, \Maatwebsite\Excel\Excel::CSV);
    }
}
