<?php

namespace App\Http\Controllers\Imports;

use App\Http\Controllers\Controller;
use App\Imports\AssetImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ImportAssetController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        Excel::import(new AssetImport(), $request->file('assets'), null, \Maatwebsite\Excel\Excel::CSV);

    }
}
