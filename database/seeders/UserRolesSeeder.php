<?php

namespace Database\Seeders;

use App\Models\Person;
use App\Models\Club;
use App\Models\Club_member;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;

class UserRolesSeeder extends Seeder
{
    public function run()
    {
        // Create test club
        $club = Club::firstOrCreate(
            ['code' => 'test-club'],
            [
                'name' => 'Test Club',
                'description' => 'Club de test',
                'category' => 'Technology',
                'founding_year' => 2024,
                'is_public' => true,
                'total_members' => 3,
                'active_members' => 3,
            ]
        );

        // ─── ADMINS (global admins, no club membership) ───────────

        $admin = Person::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'password' => Hash::make('password123'),
                'member_code' => 'ADMIN001',
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        $admin2 = Person::firstOrCreate(
            ['email' => 'achrafwandich1@gmail.com'],
            [
                'first_name' => 'Achraf',
                'last_name' => 'Wandich',
                'password' => Hash::make('achraf123'),
                'member_code' => 'ADMIN002',
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        $admin3 = Person::firstOrCreate(
            ['email' => 'souhaylaelabboudy2@gmail.com'],
            [
                'first_name' => 'Souhayla',
                'last_name' => 'Elabboudy',
                'password' => Hash::make('zofy123'),
                'member_code' => 'ADMIN003',
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        // ─── PRESIDENTS ───────────────────────────────────────────

        $president = Person::firstOrCreate(
            ['email' => 'president@example.com'],
            [
                'first_name' => 'Ahmed',
                'last_name' => 'President',
                'password' => Hash::make('password123'),
                'member_code' => 'MBRPRES001',
                'role' => 'user',
                'is_active' => true,
            ]
        );
        Club_member::firstOrCreate(
            ['person_id' => $president->id, 'club_id' => $club->id],
            ['role' => 'president', 'position' => 'Président', 'status' => 'active', 'joined_at' => now()]
        );

        $president2 = Person::firstOrCreate(
            ['email' => 'said@gmail.com'],
            [
                'first_name' => 'Said',
                'last_name' => 'Bureau',
                'password' => Hash::make('said123'),
                'member_code' => 'MBRPRES002',
                'role' => 'user',
                'is_active' => true,
            ]
        );
        Club_member::firstOrCreate(
            ['person_id' => $president2->id, 'club_id' => $club->id],
            ['role' => 'president', 'position' => 'Président', 'status' => 'active', 'joined_at' => now()]
        );

        // ─── BUREAUX ──────────────────────────────────────────────

        $board = Person::firstOrCreate(
            ['email' => 'board@example.com'],
            [
                'first_name' => 'Fatima',
                'last_name' => 'Board',
                'password' => Hash::make('password123'),
                'member_code' => 'MBRBOARD001',
                'role' => 'user',
                'is_active' => true,
            ]
        );
        Club_member::firstOrCreate(
            ['person_id' => $board->id, 'club_id' => $club->id],
            ['role' => 'board', 'position' => 'Bureau', 'status' => 'active', 'joined_at' => now()]
        );

        $board2 = Person::firstOrCreate(
            ['email' => 'karim.bureau@example.com'],
            [
                'first_name' => 'Karim',
                'last_name' => 'Benali',
                'password' => Hash::make('karim123'),
                'member_code' => 'MBRBOARD002',
                'role' => 'user',
                'is_active' => true,
            ]
        );
        Club_member::firstOrCreate(
            ['person_id' => $board2->id, 'club_id' => $club->id],
            ['role' => 'board', 'position' => 'Bureau', 'status' => 'active', 'joined_at' => now()]
        );

        $board3 = Person::firstOrCreate(
            ['email' => 'nadia.bureau@example.com'],
            [
                'first_name' => 'Nadia',
                'last_name' => 'Chraibi',
                'password' => Hash::make('nadia123'),
                'member_code' => 'MBRBOARD003',
                'role' => 'user',
                'is_active' => true,
            ]
        );
        Club_member::firstOrCreate(
            ['person_id' => $board3->id, 'club_id' => $club->id],
            ['role' => 'board', 'position' => 'Bureau', 'status' => 'active', 'joined_at' => now()]
        );

        $board4 = Person::firstOrCreate(
            ['email' => 'omar.bureau@example.com'],
            [
                'first_name' => 'Omar',
                'last_name' => 'Idrissi',
                'password' => Hash::make('omar123'),
                'member_code' => 'MBRBOARD004',
                'role' => 'user',
                'is_active' => true,
            ]
        );
        Club_member::firstOrCreate(
            ['person_id' => $board4->id, 'club_id' => $club->id],
            ['role' => 'board', 'position' => 'Bureau', 'status' => 'active', 'joined_at' => now()]
        );

        // ─── MEMBERS ──────────────────────────────────────────────

        $member = Person::firstOrCreate(
            ['email' => 'member@example.com'],
            [
                'first_name' => 'Youssef',
                'last_name' => 'Member',
                'password' => Hash::make('password123'),
                'member_code' => 'MBRMEM001',
                'role' => 'user',
                'is_active' => true,
            ]
        );
        Club_member::firstOrCreate(
            ['person_id' => $member->id, 'club_id' => $club->id],
            ['role' => 'member', 'position' => 'Membre', 'status' => 'active', 'joined_at' => now()]
        );

        $member2 = Person::firstOrCreate(
            ['email' => 'malak@gmail.com'],
            [
                'first_name' => 'Malak',
                'last_name' => 'Member',
                'password' => Hash::make('malak123'),
                'member_code' => 'MBRMEM002',
                'role' => 'user',
                'is_active' => true,
            ]
        );
        Club_member::firstOrCreate(
            ['person_id' => $member2->id, 'club_id' => $club->id],
            ['role' => 'member', 'position' => 'Membre', 'status' => 'active', 'joined_at' => now()]
        );

        $member3 = Person::firstOrCreate(
            ['email' => 'ines.member@example.com'],
            [
                'first_name' => 'Ines',
                'last_name' => 'Tazi',
                'password' => Hash::make('ines123'),
                'member_code' => 'MBRMEM003',
                'role' => 'user',
                'is_active' => true,
            ]
        );
        Club_member::firstOrCreate(
            ['person_id' => $member3->id, 'club_id' => $club->id],
            ['role' => 'member', 'position' => 'Membre', 'status' => 'active', 'joined_at' => now()]
        );

        $member4 = Person::firstOrCreate(
            ['email' => 'hamza.member@example.com'],
            [
                'first_name' => 'Hamza',
                'last_name' => 'Alaoui',
                'password' => Hash::make('hamza123'),
                'member_code' => 'MBRMEM004',
                'role' => 'user',
                'is_active' => true,
            ]
        );
        Club_member::firstOrCreate(
            ['person_id' => $member4->id, 'club_id' => $club->id],
            ['role' => 'member', 'position' => 'Membre', 'status' => 'active', 'joined_at' => now()]
        );

        $member5 = Person::firstOrCreate(
            ['email' => 'sara.member@example.com'],
            [
                'first_name' => 'Sara',
                'last_name' => 'Moussaoui',
                'password' => Hash::make('sara123'),
                'member_code' => 'MBRMEM005',
                'role' => 'user',
                'is_active' => true,
            ]
        );
        Club_member::firstOrCreate(
            ['person_id' => $member5->id, 'club_id' => $club->id],
            ['role' => 'member', 'position' => 'Membre', 'status' => 'active', 'joined_at' => now()]
        );

        // ─── SUMMARY ──────────────────────────────────────────────

        $this->command->info('✅ Users created!');
        $this->command->info('');
        $this->command->info('─── ADMINS ───────────────────────────────────────────────');
        $this->command->info('👑 Admin:     admin@example.com                | password123');
        $this->command->info('👑 Admin:     achrafwandich1@gmail.com         | achraf123');
        $this->command->info('👑 Admin:     souhaylaelabboudy2@gmail.com     | zofy123');
        $this->command->info('');
        $this->command->info('─── PRESIDENTS ───────────────────────────────────────────');
        $this->command->info('🎖️  President: president@example.com            | password123');
        $this->command->info('🎖️  President: said@gmail.com                   | said123');
        $this->command->info('');
        $this->command->info('─── BUREAUX ──────────────────────────────────────────────');
        $this->command->info('📋 Bureau:    board@example.com                 | password123');
        $this->command->info('📋 Bureau:    karim.bureau@example.com          | karim123');
        $this->command->info('📋 Bureau:    nadia.bureau@example.com          | nadia123');
        $this->command->info('📋 Bureau:    omar.bureau@example.com           | omar123');
        $this->command->info('');
        $this->command->info('─── MEMBERS ──────────────────────────────────────────────');
        $this->command->info('👤 Member:    member@example.com                | password123');
        $this->command->info('👤 Member:    malak@gmail.com                   | malak123');
        $this->command->info('👤 Member:    ines.member@example.com           | ines123');
        $this->command->info('👤 Member:    hamza.member@example.com          | hamza123');
        $this->command->info('👤 Member:    sara.member@example.com           | sara123');
    }
}