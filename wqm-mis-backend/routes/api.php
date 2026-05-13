<?php

use App\Http\Controllers\AbbreviationController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Assets\AssetController;
use App\Http\Controllers\Assets\AssetLogController;
use App\Http\Controllers\Assets\AssetMaintenanceLogController;
use App\Http\Controllers\Assets\AssetMaintenanceScheduleController;
use App\Http\Controllers\Assets\EquipmentCalibrationLogController;
use App\Http\Controllers\Assets\EquipmentRepairLogController;
use App\Http\Controllers\Assets\LaboratoryAssetController;
use App\Http\Controllers\Assets\LaboratoryAssetLogController;
use App\Http\Controllers\Assets\ShowAssetMaintenanceScheduleController;
use App\Http\Controllers\Assets\UpdateAssetMaintenanceScheduleStatusController;
use App\Http\Controllers\Assets\UpdateAssetStatusController;
use App\Http\Controllers\AssignRolePermissionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientListController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\ComplaintLogController;
use App\Http\Controllers\Complaints\UpdateComplaintStatusController;
use App\Http\Controllers\ComplaintTypeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\DiaryDispatchController;
use App\Http\Controllers\DistrictController;
use App\Http\Controllers\DistrictWiseContaminantsController;
use App\Http\Controllers\DivisionController;
use App\Http\Controllers\Dropdowns\AssetLogStatusController;
use App\Http\Controllers\Dropdowns\PhysicalParameterController;
use App\Http\Controllers\Dropdowns\AssetMaintenanceStatusController;
use App\Http\Controllers\Dropdowns\AssetStatusController;
use App\Http\Controllers\Dropdowns\CollectableTypeController;
use App\Http\Controllers\Dropdowns\CollectedByController;
use App\Http\Controllers\Dropdowns\CollectedInController;
use App\Http\Controllers\Dropdowns\ComplaintStatusController;
use App\Http\Controllers\Dropdowns\DesiredTestController;
use App\Http\Controllers\Dropdowns\EmployementStatusController;
use App\Http\Controllers\Dropdowns\FocalPersonController;
use App\Http\Controllers\Dropdowns\GenderController;
use App\Http\Controllers\Dropdowns\InventoryDetailStatusController;
use App\Http\Controllers\Dropdowns\InvoiceableTypeController;
use App\Http\Controllers\Dropdowns\IssuableController;
use App\Http\Controllers\Dropdowns\IssueStatusController;
use App\Http\Controllers\Dropdowns\IssueTypeController;
use App\Http\Controllers\Dropdowns\LocalityController;
use App\Http\Controllers\Dropdowns\MaterialLogStatusController;
use App\Http\Controllers\Dropdowns\MaterialStatusController;
use App\Http\Controllers\Dropdowns\OnDemandTestController;
use App\Http\Controllers\Dropdowns\PaymentableTypeController;
use App\Http\Controllers\Dropdowns\PurchasableController;
use App\Http\Controllers\Dropdowns\PurchasableTypeController;
use App\Http\Controllers\Dropdowns\PurchaseOrderStatusController;
use App\Http\Controllers\Dropdowns\ReasonForTestingController;
use App\Http\Controllers\Dropdowns\SamplingPointController;
use App\Http\Controllers\Dropdowns\SourceTypeController;
use App\Http\Controllers\Dropdowns\StockableController;
use App\Http\Controllers\Dropdowns\TestFrequencyController;
use App\Http\Controllers\Dropdowns\TestParameterController;
use App\Http\Controllers\Dropdowns\SourceSubTypeController;
use App\Http\Controllers\Dropdowns\TestTypeController;
use App\Http\Controllers\Dropdowns\WaterSampleInvoiceStatusController;
use App\Http\Controllers\Dropdowns\WaterSampleReportController;
use App\Http\Controllers\Dropdowns\WaterSampleStatusController;
use App\Http\Controllers\Dropdowns\WaterSchemeDropdownController;
use App\Http\Controllers\Dropdowns\RegionController;
use App\Http\Controllers\Dropdowns\CircleController;
use App\Http\Controllers\Dropdowns\PhedDivisionController;
use App\Http\Controllers\Dropdowns\HubLabController;
use App\Http\Controllers\Dropdowns\SubDivisionController;
use App\Http\Controllers\Dropdowns\DivisionController as DropdownDivisionController;
use App\Http\Controllers\Dropdowns\DistrictController as DropdownDistrictController;
use App\Http\Controllers\Exports\ExportLaboratoryController;
use App\Http\Controllers\Exports\ExportUserController;
use App\Http\Controllers\Exports\ExportWaterSampleController;
use App\Http\Controllers\Exports\ExportWaterSampleInvoiceController;
use App\Http\Controllers\Exports\ExportWaterSchemeController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\HandingTakingController;
use App\Http\Controllers\Imports\ImportAssetController;
use App\Http\Controllers\Imports\ImportWaterSchemeController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Inventory\InventoryDetailController;
use App\Http\Controllers\Inventory\InventoryLogController;
use App\Http\Controllers\Inventory\InventoryReceivedController;
use App\Http\Controllers\Inventory\UpdateInventoryApproveStatusController;
use App\Http\Controllers\Inventory\UpdateInventoryIssueStatusController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\Issues\AssignIssueController;
use App\Http\Controllers\Issues\IssueController;
use App\Http\Controllers\Issues\IssueLogController;
use App\Http\Controllers\Issues\IssueResponsibleController;
use App\Http\Controllers\Laboratories\LaboratoryController;
use App\Http\Controllers\Laboratories\LaboratoryUserController;
use App\Http\Controllers\Laboratories\LaboratoryWaterQualityAnalysisReportController;
use App\Http\Controllers\Laboratories\UpdateLaboratoryStatusController;
use App\Http\Controllers\MarkAsReadNotificationController;
use App\Http\Controllers\Materials\LaboratoryMaterialController;
use App\Http\Controllers\Materials\MaterialController;
use App\Http\Controllers\Materials\MaterialLogController;
use App\Http\Controllers\Materials\StockOutController;
use App\Http\Controllers\Assets\InventoryOutController;
use App\Http\Controllers\Materials\UpdateMaterialStatusController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentDetailController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProvinceController;
use App\Http\Controllers\PurchaseOrders\PurchaseOrderController;
use App\Http\Controllers\PurchaseOrders\UpdatePurchaseOrderStatusController;
use App\Http\Controllers\Reports\CentralLaboratoryWaterQualityReportController;
use App\Http\Controllers\Reports\ContaminantWiseReportController;
use App\Http\Controllers\Reports\WaterQualityAnalysisReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RoleHasPermissionController;
use App\Http\Controllers\Search\SearchAssetController;
use App\Http\Controllers\Search\SearchClientController;
use App\Http\Controllers\Search\SearchComplaintController;
use App\Http\Controllers\Search\SearchIssueController;
use App\Http\Controllers\Search\SearchLaboratoryController;
use App\Http\Controllers\Search\SearchMaterialController;
use App\Http\Controllers\Search\SearchPaymentController;
use App\Http\Controllers\Search\SearchPurchaseOrderController;
use App\Http\Controllers\Search\SearchWaterSampleController;
use App\Http\Controllers\Search\SearchWaterSampleInvoiceController;
use App\Http\Controllers\Search\SearchWaterSampleResultController;
use App\Http\Controllers\Search\SearchWaterSchemeController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ShowWaterSchemeSchedule;
use App\Http\Controllers\SopWaterSampleController;
use App\Http\Controllers\TehsilController;
use App\Http\Controllers\TermAndConditionController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\TestReportController;
use App\Http\Controllers\UnionCouncilController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UpdateWaterSchemeScheduleStatusController;
use App\Http\Controllers\UpdateWaterSchemeStatusController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPasswordController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\WaterSamples\WaterSampleController;
use App\Http\Controllers\WaterSamples\WaterSampleDetailController;
use App\Http\Controllers\WaterSamples\WaterSampleInvoiceController;
use App\Http\Controllers\WaterSamples\WaterSampleListController;
use App\Http\Controllers\WaterSamples\WaterSampleQueueController;
use App\Http\Controllers\WaterSamples\WaterSampleResultController;
use App\Http\Controllers\WaterSamples\WaterSampleTestController;
use App\Http\Controllers\WaterSamples\WaterSchemeSampleController;
use App\Http\Controllers\WaterSchemeController;
use App\Http\Controllers\WaterSchemeScheduleController;
use App\Http\Controllers\WaterSchemeTestingStatusController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(callback: function () {
    Route::post('dashboard', DashboardController::class);
    Route::post('district-wise-contaminants', DistrictWiseContaminantsController::class);

    // XEN Dashboard Routes
    Route::prefix('xen')->group(function () {
        Route::get('dashboard', [\App\Http\Controllers\Xen\XenDashboardController::class, 'index']);
        Route::get('trail', [\App\Http\Controllers\Xen\XenDashboardController::class, 'trail']);
        Route::post('actions/request-retest', [\App\Http\Controllers\Xen\XenDashboardController::class, 'requestRetest']);
    });

    //start locality routes
    Route::apiResource('provinces', ProvinceController::class);
    Route::apiResource('divisions', DivisionController::class);
    Route::apiResource('districts', DistrictController::class);
    Route::apiResource('tehsils', TehsilController::class);
    Route::apiResource('union-councils', UnionCouncilController::class);
    //end locality routes

    //start dropdowns routes
    Route::get('employment-status', EmployementStatusController::class);
    Route::get('genders', GenderController::class);
    Route::get('source-sub-types', SourceSubTypeController::class);
    Route::get('physical-parameters', PhysicalParameterController::class);
    Route::get('locality', LocalityController::class);
    Route::get('test-frequencies', TestFrequencyController::class);
    Route::get('test-types', TestTypeController::class);
    Route::get('issue-types', IssueTypeController::class);
    Route::get('paymentable-types', PaymentableTypeController::class);
    Route::get('complaint-status', ComplaintStatusController::class);
    Route::get('collectable-types', CollectableTypeController::class);
    Route::get('collected-by-status', CollectedByController::class);
    Route::get('focal-persons', FocalPersonController::class);
    Route::get('all-laboratories', \App\Http\Controllers\Dropdowns\LaboratoryController::class);
    Route::get('all-diary-dispatches', \App\Http\Controllers\Dropdowns\DiaryDispatchController::class);
    Route::get('all-water-schemes', \App\Http\Controllers\Dropdowns\WaterSchemeController::class);
    Route::get('all-designations', \App\Http\Controllers\Dropdowns\DesignationController::class);
    Route::get('all-roles', \App\Http\Controllers\Dropdowns\RoleController::class);
    Route::get('purchase-order-status', PurchaseOrderStatusController::class);
    Route::get('test-parameters', TestParameterController::class);
    Route::get('asset-status', AssetStatusController::class);
    Route::get('asset-maintenance-status', AssetMaintenanceStatusController::class);
    Route::get('issue-status', IssueStatusController::class);
    Route::post('issuable', IssuableController::class);
    Route::post('purchasable', PurchasableController::class);
    Route::get('purchasable-type', PurchasableTypeController::class);
    Route::get('asset-logs-status', AssetLogStatusController::class);
    Route::get('source-types', SourceTypeController::class);
    Route::get('sampling-points', SamplingPointController::class);
    Route::get('water-sample-status', WaterSampleStatusController::class);
    Route::get('collected-in-status', CollectedInController::class);
    Route::get('reason-for-testing-status', ReasonForTestingController::class);
    Route::get('desired-testing-status', DesiredTestController::class);
    Route::get('inventory_detail-status', InventoryDetailStatusController::class);
    Route::get('material-status', MaterialStatusController::class);
    Route::get('material-log-status', MaterialLogStatusController::class);
    Route::get('invoiceable-types', InvoiceableTypeController::class);
    Route::get('on-demand-tests', OnDemandTestController::class);
    Route::get('water-sample-results', \App\Http\Controllers\Dropdowns\WaterSampleResultController::class);
    Route::get('water-sample-invoice-status', WaterSampleInvoiceStatusController::class);
    Route::get('laboratory-users', \App\Http\Controllers\Dropdowns\LaboratoryUserController::class);
    Route::post('stockables', StockableController::class);
    Route::get('units/{invoiceableTypeEnum}', \App\Http\Controllers\Dropdowns\UnitController::class);
    Route::get('water-schemes-dropdowns', WaterSchemeDropdownController::class);
    Route::get('regions', RegionController::class);
    Route::get('all-divisions', DropdownDivisionController::class);
    Route::get('hub-labs', HubLabController::class);
    Route::get('circles', CircleController::class);
    Route::get('all-districts', DropdownDistrictController::class);
    Route::get('phed-divisions', PhedDivisionController::class);
    Route::get('sub-divisions', SubDivisionController::class);
    //end dropdowns routes

    Route::apiResource('designations', DesignationController::class);

    Route::apiResource('abbreviations', AbbreviationController::class);

    Route::apiResource('laboratories', LaboratoryController::class)->middleware('update_modified_user');
    Route::get('laboratories/{laboratory}/status/{isActive}', UpdateLaboratoryStatusController::class);

    Route::post('laboratories/{laboratory}/users/{user}', [LaboratoryUserController::class, 'store']);
    Route::delete('laboratories/{laboratory}/users/{user}', [LaboratoryUserController::class, 'destroy']);

    Route::apiResource('tests', TestController::class)->middleware('update_modified_user');

    Route::apiResource('water-schemes', WaterSchemeController::class)->middleware(['update_modified_user']);
    Route::get('water-schemes-samples', WaterSchemeSampleController::class);
    Route::get('water-schemes/{waterScheme}/status/{isActive}', UpdateWaterSchemeStatusController::class);
    Route::apiResource('water-scheme-schedules', WaterSchemeScheduleController::class)->only(['index', 'store', 'update']);
    Route::get('water-schemes/{waterScheme}/schedules', ShowWaterSchemeSchedule::class);
    Route::get('water-schemes/{waterSchemeSchedule}/schedules/{status}', UpdateWaterSchemeScheduleStatusController::class);
    Route::get('water-schemes/testing/status', WaterSchemeTestingStatusController::class);

    //start complaint management routes
    Route::apiResource('complaints', ComplaintController::class);
    Route::post('complaints/{complaint}/status', UpdateComplaintStatusController::class);
    Route::apiResource('complaint-logs', ComplaintLogController::class);
    Route::apiResource('complaint-types', ComplaintTypeController::class)->except(['show', 'update']);
    //end complaint management routes

    //start material management routes
    Route::apiResource('materials', MaterialController::class)->middleware(['role:system-administrator']);
    Route::get('materials/{material}/status/{isActive}', UpdateMaterialStatusController::class);
    Route::apiResource('material-logs', MaterialLogController::class);
    Route::apiResource('laboratory-materials', LaboratoryMaterialController::class)->only(['index', 'show', 'update']);
    Route::get('laboratory/materials/all', [LaboratoryMaterialController::class, 'laboratoryMaterials']);
    Route::post('stock-out', [StockOutController::class, 'store']);
    //end material management routes

    //start asset management routes
    Route::apiResource('assets', AssetController::class)->middleware(['role:system-administrator']);
    Route::get('assets/{asset}/status/{isActive}', UpdateAssetStatusController::class);
    Route::apiResource('asset-logs', AssetLogController::class);
    Route::apiResource('asset-maintenance-schedules', AssetMaintenanceScheduleController::class);
    Route::get('assets/{laboratoryAsset}/maintenance-schedules', ShowAssetMaintenanceScheduleController::class);
    Route::get('assets/{maintenanceSchedule}/maintenance-schedules/{status}', UpdateAssetMaintenanceScheduleStatusController::class);
    Route::apiResource('asset-maintenance-logs', AssetMaintenanceLogController::class)->only('store');
    Route::get('laboratory/assets/all', [LaboratoryAssetController::class, 'laboratoryAssets']);
    Route::apiResource('laboratory-assets', LaboratoryAssetController::class)->only(['index', 'show', 'update']);
    Route::post('inventory-out', [InventoryOutController::class, 'store']);
    // Equipment calibration logs (nested index + standalone store)
    Route::get('laboratory-assets/{laboratoryAsset}/calibration-logs', [EquipmentCalibrationLogController::class, 'index']);
    Route::post('equipment-calibration-logs', [EquipmentCalibrationLogController::class, 'store']);
    // Equipment repair logs (nested index + standalone store)
    Route::get('laboratory-assets/{laboratoryAsset}/repair-logs', [EquipmentRepairLogController::class, 'index']);
    Route::post('equipment-repair-logs', [EquipmentRepairLogController::class, 'store']);
    //end asset management routes

    Route::apiResource('settings', SettingController::class)->only(['index', 'store', 'update']);

    //start issues management routes
    Route::apiResource('issues', IssueController::class);
    Route::apiResource('issue-logs', IssueLogController::class)->only('store');
    Route::apiResource('issue-responsibles', IssueResponsibleController::class)->except('index', 'show');
    Route::get('assign-issues', AssignIssueController::class);
    //end issues management routes

    //start water-sample management routes
    Route::apiResource('water-samples', WaterSampleController::class)->middleware('update_modified_user');
    Route::get('water-samples-queue/{isDraft?}', WaterSampleQueueController::class);
    Route::apiResource('water-sample-details', WaterSampleDetailController::class)->except(['index']);
    Route::put('water-sample-results/{water_sample}', [WaterSampleResultController::class, 'update']);
    Route::post('water-sample-tests/{water_sample}/retest', [WaterSampleTestController::class, 'retest']);
    Route::patch('water-samples/{water_sample}/fate', [WaterSampleTestController::class, 'recordFate']);
    Route::patch('water-sample-tests/{water_sample}/start', [WaterSampleTestController::class, 'startAnalysis']);
    Route::put('water-sample-tests/{water_sample}/analyze', [WaterSampleTestController::class, 'analyze']);
    Route::apiResource('clients', ClientController::class);
    Route::controller(TestReportController::class)->group(function () {
        Route::get('test-reports/{water_sample}', 'show');
        Route::put('test-reports/{water_sample}', 'update');
    });
    Route::get('water-samples/{water_sample}/report', [WaterSampleReportController::class, 'index']);
    Route::get('water-samples/{year}/{division:abbreviation}/{collectable_type}/{id}', [WaterSampleReportController::class, 'show']);
    Route::apiResource('water-sample-invoices', WaterSampleInvoiceController::class)->except(['delete', 'store']);
    Route::post('water-sample/export', [SearchWaterSampleResultController::class, 'export']);
    Route::post('water-sample/graph', [SearchWaterSampleResultController::class, 'generateGraph']);
    Route::get('get-clients', ClientListController::class);
    Route::get('water-schemes/{water_scheme}/water-samples', WaterSampleListController::class);
    //end water-sample management routes

    //Start SOP's
    Route::controller(SopWaterSampleController::class)->group(function () {
        Route::post('/sop-water-sample', 'store');
        Route::get('/sop-water-sample/{sopWaterSampleEnum}', 'show');
    });
    //End SOP's

    //Start Reports
    Route::prefix('reports')->group(function () {
        Route::post('water-quality-analysis', WaterQualityAnalysisReportController::class);
        Route::post('central-laboratory-water-quality', CentralLaboratoryWaterQualityReportController::class);
        Route::post('laboratory-water-quality-analysis', LaboratoryWaterQualityAnalysisReportController::class);
        Route::post('ce-wise', \App\Http\Controllers\Reports\CEWiseReportController::class);
        Route::controller(ContaminantWiseReportController::class)
            ->prefix('contaminant-wise')
            ->group(function () {
                Route::post('map', 'map');
            });

    });
    //End Reports
    Route::middleware(['update_modified_user'])->group(function () {
        Route::apiResource('invoices', InvoiceController::class);
    });
    //start payment management routes
    Route::apiResource('payments', PaymentController::class)->middleware('update_modified_user');
    Route::apiResource('payment-details', PaymentDetailController::class)->only(['update', 'destroy']);
    //end payment management routes

    Route::apiResource('purchase-orders', PurchaseOrderController::class);
    Route::put('purchase-order-status/{purchase_order}', [UpdatePurchaseOrderStatusController::class, 'update']);

    //start inventory management routes
    Route::apiResource('inventories', InventoryController::class)->except('update')->middleware('update_modified_user');
    Route::apiResource('inventory-details', InventoryDetailController::class)->except('store');
    Route::prefix('inventory-details/{inventory_detail}')->group(function () {
        Route::put('/statuses/approve', [UpdateInventoryApproveStatusController::class, 'update'])->middleware('update_modified_user');
        Route::put('/statuses/issue', [UpdateInventoryIssueStatusController::class, 'update'])->middleware('update_modified_user');
        Route::get('received/{isReceived}', InventoryReceivedController::class);
    });
    Route::apiResource('inventory-logs', InventoryLogController::class)->only('show');
    //end inventory management routes

    //start notification management
    Route::apiResource('notifications', NotificationController::class)->only(['index', 'show']);
    Route::post('mark-as-read-notifications', MarkAsReadNotificationController::class);
    //end notification management

    Route::apiResource('roles', RoleController::class);
    Route::apiResource('permissions', PermissionController::class);
    Route::get('role-has-permissions/{role}', RoleHasPermissionController::class);
    Route::post('assign/role/{role}/permission', AssignRolePermissionController::class);

    //start user management routes
    Route::apiResource('users', UserController::class)->middleware('update_modified_user');
    Route::put('user-password', UserPasswordController::class);
    Route::controller(UserProfileController::class)->group(function () {
        Route::get('profile', 'show');
        Route::put('profile', 'update');
    });

    //end user management routes

    //start search routes
    Route::post('search-material', SearchMaterialController::class);
    Route::post('search-asset', SearchAssetController::class);
    Route::post('search-purchase-order', SearchPurchaseOrderController::class);
    Route::post('search-complaint', SearchComplaintController::class);
    Route::post('search-issue', SearchIssueController::class);
    Route::post('search-laboratory', SearchLaboratoryController::class);
    Route::post('search-water-sample', SearchWaterSampleController::class);
    Route::post('search-payment', SearchPaymentController::class);
    Route::post('search-water-scheme', SearchWaterSchemeController::class);
    Route::post('search-water-sample-invoices', SearchWaterSampleInvoiceController::class);
    Route::post('search-clients', SearchClientController::class);
    Route::get('organizations', [SearchClientController::class, 'organizations']);
    Route::post('search-water-sample-results', [SearchWaterSampleResultController::class, 'show']);
    //end search routes

    Route::apiResource('term-and-conditions', TermAndConditionController::class);

    Route::apiResource('units', UnitController::class);
    Route::apiResource('handing-takings', HandingTakingController::class)
        ->middleware('update_modified_user');

    Route::get('acitivity-logs', ActivityLogController::class);

    Route::apiResource('folders', FolderController::class);

    Route::apiResource('diary-dispatch/{enum}/registers', DiaryDispatchController::class);

    //Imports
    Route::post('import-water-schemes', ImportWaterSchemeController::class);
    Route::post('import-assets', ImportAssetController::class);


    //Exports
    Route::post('export-water-sample-invoices', ExportWaterSampleInvoiceController::class);
    Route::post('export-water-samples', ExportWaterSampleController::class);
    Route::post('export-water-schemes', ExportWaterSchemeController::class);
    Route::post('export-users', ExportUserController::class);
    Route::post('export-laboratories', ExportLaboratoryController::class);

    Route::get('payment/{payment}/pdf-report', [PaymentController::class, 'generatePdf']);

});

include('newapis.php');

// ── Client Portal ─────────────────────────────────────────────────────
Route::prefix('client-portal')->group(function () {
    // Public: login & logout
    Route::post('login',  [\App\Http\Controllers\ClientPortal\ClientPortalAuthController::class, 'login']);
    Route::post('logout', [\App\Http\Controllers\ClientPortal\ClientPortalAuthController::class, 'logout']);

    // Protected: requires client portal token
    Route::middleware('client.portal')->group(function () {
        Route::get('me',             [\App\Http\Controllers\ClientPortal\ClientPortalController::class, 'me']);
        Route::get('samples',        [\App\Http\Controllers\ClientPortal\ClientPortalController::class, 'samples']);
        Route::get('invoices',       [\App\Http\Controllers\ClientPortal\ClientPortalController::class, 'invoices']);
        Route::get('email-reports',  [\App\Http\Controllers\ClientPortal\ClientPortalController::class, 'emailReports']);
        Route::put('change-password',[\App\Http\Controllers\ClientPortal\ClientPortalController::class, 'changePassword']);
    });
});
