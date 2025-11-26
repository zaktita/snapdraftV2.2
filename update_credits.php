<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::first();
$user->credits_remaining = 1250;
$user->save();

echo "User credits updated to: {$user->credits_remaining}\n";
echo "User ID: {$user->id}\n";
echo "User email: {$user->email}\n";
