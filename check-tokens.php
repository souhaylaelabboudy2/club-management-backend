<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$tokens = DB::table('password_reset_tokens')->orderBy('created_at', 'desc')->limit(5)->get();

echo "=== Tokens dans la BD ===\n";
foreach ($tokens as $token) {
    $age = now()->diffInSeconds($token->created_at);
    echo "Email: " . $token->email . "\n";
    echo "Token: " . substr($token->token, 0, 20) . "...\n";
    echo "Age: " . $age . " secondes\n";
    echo "---\n";
}
