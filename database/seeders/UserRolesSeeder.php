<?php

namespace Database\Seeders;

use App\Models\Person;
use App\Models\Club;
use App\Models\Club_member;
use App\Models\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;

class UserRolesSeeder extends Seeder
{
    public function run()
    {
        // ─────────────────────────────────────────
        // ADMINS
        // ─────────────────────────────────────────

        Person::firstOrCreate(
            ['email' => 'achraf@gmail.com'],
            [
                'first_name'  => 'Achraf',
                'last_name'   => 'Admin',
                'password'    => Hash::make('achraf123'),
                'member_code' => 'ADMIN001',
                'role'        => 'admin',
                'is_active'   => true,
            ]
        );

    }
}