<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::first();
echo "User: " . $user->name . " | is sys admin: " . ($user->hasRole('system-administrator') ? 'yes' : 'no') . "\n";

$query = App\Models\WaterSamples\WaterSampleInvoice::query()
    ->has('waterSample');

if (!$user->hasRole('system-administrator')) {
    $query->where('created_by', '=', $user->id);
}
echo "Invoices returned: " . $query->count() . "\n";
