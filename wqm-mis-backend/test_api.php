<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('email', 'system.administrator@mis.com')->first();
$request = Illuminate\Http\Request::create('/api/finance/invoices', 'GET');
$request->setUserResolver(function() use ($user) { return $user; });

$controller = new App\Http\Controllers\Finance\FinanceInvoiceController();
$response = $controller->index($request);

echo $response->getContent();
