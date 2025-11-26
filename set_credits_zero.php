<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$user = App\Models\User::first();
$user->credits_remaining = 0;
$user->save();
echo "Credits now: {$user->credits_remaining}\n";