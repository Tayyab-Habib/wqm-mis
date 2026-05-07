<?php

use App\Http\Controllers\Apis\IndexController;

Route::prefix('/v1')
    ->middleware(['api'])
    ->group(function () {
        Route::get('/abbreviations', [IndexController::class, 'index'])->name('abbreviations.index');
        Route::get('/dashboard', [IndexController::class, 'dashboard'])->name('dashboard.index');
        Route::get('/district-wise-contaminants-count', [IndexController::class, 'districtWiseContaminantsCount'])->name('dashboard.district-wise-contaminants-count');

        Route::get('listing/get-water-schemes-status-list', [IndexController::class, 'getWaterSchemesStatusList'])->name('dashboard.get-water-schemes-status-list');
        Route::get('listing/get-water-sample-list', [IndexController::class, 'getWaterSampleList'])->name('dashboard.get-water-sample-list');
        
        Route::get('listing/get-water-sample-count', [IndexController::class, 'getWaterSampleCount'])->name('dashboard.get-water-sample-count');
        Route::get('listing/get-locality-list', [IndexController::class, 'locality'])->name('dashboard.get-locality-list');

        Route::get('listing/water-samples/{water_sample}/reports', [IndexController::class, 'getWaterSampleDetail']);
    });

