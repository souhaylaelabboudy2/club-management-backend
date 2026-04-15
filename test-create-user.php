<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Person;
use Illuminate\Support\Facades\Hash;

$person = Person::create([
    'first_name' => 'Test',
    'last_name' => 'User',
    'email' => 'test@example.com',
    'password' => Hash::make('Password123'),
    'member_code' => uniqid('MC'),
    'is_active' => true,
]);

echo "✓ Created user with email: " . $person->email . "\n";
echo "  Password: Password123\n";
