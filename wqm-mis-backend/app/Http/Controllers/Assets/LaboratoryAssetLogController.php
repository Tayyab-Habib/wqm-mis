<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Asset\StoreLaboratoryAssetLogRequest;
use App\Http\Requests\Asset\UpdateLaboratoryAssetLogRequest;
use App\Models\Asset\LaboratoryAssetLog;

class LaboratoryAssetLogController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\Asset\StoreLaboratoryAssetLogRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreLaboratoryAssetLogRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Asset\LaboratoryAssetLog  $laboratoryAssetLog
     * @return \Illuminate\Http\Response
     */
    public function show(LaboratoryAssetLog $laboratoryAssetLog)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\Asset\UpdateLaboratoryAssetLogRequest  $request
     * @param  \App\Models\Asset\LaboratoryAssetLog  $laboratoryAssetLog
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateLaboratoryAssetLogRequest $request, LaboratoryAssetLog $laboratoryAssetLog)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Asset\LaboratoryAssetLog  $laboratoryAssetLog
     * @return \Illuminate\Http\Response
     */
    public function destroy(LaboratoryAssetLog $laboratoryAssetLog)
    {
        //
    }
}
