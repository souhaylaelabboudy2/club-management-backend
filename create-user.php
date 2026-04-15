<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';

$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Models\Person;
use Illuminate\Support\Facades\Hash;

$person = new Person();
$person->email = 'souhaylaelabboudy2@gmail.com';
$person->first_name = 'Souhayla';
$person->last_name = 'Elabboudy';
$person->password = Hash::make('TestPassword123');
$person->is_active = true;
$person->role = 'admin';
$person->save();

echo "✅ User created with ID: " . $person->id . "\n";
echo "📧 Email: " . $person->email . "\n";
