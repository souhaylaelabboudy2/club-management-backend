<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// Delete all old tokens
DB::table('password_reset_tokens')->truncate();
echo "✓ Tokens supprimés\n";
