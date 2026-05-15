<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = Illuminate\Http\Request::create('/api/finance/invoices', 'GET');
$user = App\Models\User::first();
$request->setUserResolver(function() use ($user) { return $user; });
$controller = new App\Http\Controllers\Finance\FinanceInvoiceController();
$response = $controller->index($request);

echo $response->getContent();
