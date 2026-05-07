<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHandingTakingLogRequest;
use App\Http\Requests\UpdateHandingTakingLogRequest;
use App\Models\HandingTakingLog;

class HandingTakingLogController extends Controller
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
     * @param  \App\Http\Requests\StoreHandingTakingLogRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreHandingTakingLogRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\HandingTakingLog  $handingTakingLog
     * @return \Illuminate\Http\Response
     */
    public function show(HandingTakingLog $handingTakingLog)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateHandingTakingLogRequest  $request
     * @param  \App\Models\HandingTakingLog  $handingTakingLog
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateHandingTakingLogRequest $request, HandingTakingLog $handingTakingLog)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\HandingTakingLog  $handingTakingLog
     * @return \Illuminate\Http\Response
     */
    public function destroy(HandingTakingLog $handingTakingLog)
    {
        //
    }
}
