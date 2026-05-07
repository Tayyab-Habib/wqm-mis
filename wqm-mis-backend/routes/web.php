<?php

use App\Enums\TestTypeEnum;
use App\Http\Controllers\Dropdowns\WaterSampleReportController;
use App\Http\Controllers\PurchaseOrders\PurchaseOrderController;
use App\Http\Controllers\WaterSamples\WaterSampleInvoiceController;
use App\Models\Test;
use App\Models\WaterSamples\WaterSampleInvoice;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return App::isProduction()
        ? redirect(config('app.frontend_url'))
        : view('welcome');

});

Route::get('water-samples/generate-pdf/{water_sample}', [WaterSampleReportController::class, 'generatePdf']);
Route::get('water-sample-invoices/{water_sample_invoice}/pdf-report', [WaterSampleInvoiceController::class, 'generatePdf']);
Route::get('purchase-orders/{purchase_order}/pdf-report', [PurchaseOrderController::class, 'generatePdf']);
Route::get('payment/{payment}/pdf-report', [\App\Http\Controllers\PaymentController::class, 'generatePdf']);


Route::get('test/{waterSampleInvoice}', function(WaterSampleInvoice $waterSampleInvoice)
{
    $waterSampleInvoice->load([
        'waterSample' => [
            'collectable:id,name,phone,email',
            'laboratory:id,name,address,email,fax,phone,logo',
            'province:id,logo',
            'district:id,name',
            'waterScheme:id,name',
        ],
        'waterSampleInvoiceLogs.user:id,name'
    ]);

    $desiredTests = Test::query()
        ->select('water_quality_parameter')
        ->whereHas('waterSampleDetails', fn($query) => $query->where('water_sample_id', '=', $waterSampleInvoice->water_sample_id)
            ->where('type', '=', TestTypeEnum::ON_DEMAND->value))
        ->pluck('water_quality_parameter')->toArray();

    $desiredTests = implode(', ', $desiredTests);

    return view('waterSample.invoice', compact('waterSampleInvoice', 'desiredTests'));
});
