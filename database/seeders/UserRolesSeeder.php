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

        // Admin (global admin, no club membership)
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

        // President (regular user, club role: president)
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
            [
                'role' => 'president',
                'position' => 'Président',
                'status' => 'active',
                'joined_at' => now(),
            ]
        );

        // Board (regular user, club role: board)
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
            [
                'role' => 'board',
                'position' => 'Bureau',
                'status' => 'active',
                'joined_at' => now(),
            ]
        );

        // Member (regular user, club role: member)
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
            [
                'role' => 'member',
                'position' => 'Membre',
                'status' => 'active',
                'joined_at' => now(),
            ]
        );

        $this->command->info('✅ Users created!');
        $this->command->info('👑 Admin: admin@example.com | password123');
        $this->command->info('📧 President: president@example.com | password123');
        $this->command->info('📧 Board: board@example.com | password123');
        $this->command->info('📧 Member: member@example.com | password123');
    }
}