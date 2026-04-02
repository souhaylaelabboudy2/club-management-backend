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

        Person::firstOrCreate(
            ['email' => 'achrafwandich1@gmail.com'],
            [
                'first_name'  => 'Achraf',
                'last_name'   => 'Wandich',
                'password'    => Hash::make('achraf123'),
                'member_code' => 'ADMIN002',
                'role'        => 'admin',
                'is_active'   => true,
            ]
        );

        Person::firstOrCreate(
            ['email' => 'souhaylaelabboudy2@gmail.com'],
            [
                'first_name'  => 'Souhayla',
                'last_name'   => 'Elabboudy',
                'password'    => Hash::make('zofy123'),
                'member_code' => 'ADMIN003',
                'role'        => 'admin',
                'is_active'   => true,
            ]
        );

        $this->command->info('✅ Admins created!');

        // ─────────────────────────────────────────
        // CLUB 1 — Tech Club
        // ─────────────────────────────────────────

        $club1 = Club::firstOrCreate(
            ['code' => 'TECH001'],
            [
                'name'         => 'Tech Club',
                'description'  => 'A club for technology enthusiasts.',
                'mission'      => 'Promote innovation and tech culture on campus.',
                'category'     => 'Technology',
                'founding_year'=> 2020,
                'is_public'    => true,
                'total_members'=> 0,
                'active_members'=> 0,
            ]
        );

        // President
        $president1 = Person::firstOrCreate(
            ['email' => 'president.tech@gmail.com'],
            [
                'first_name'  => 'Youssef',
                'last_name'   => 'Alami',
                'password'    => Hash::make('password123'),
                'member_code' => 'TC-PRES01',
                'role'        => 'user',
                'is_active'   => true,
            ]
        );
        Club_member::firstOrCreate(
            ['person_id' => $president1->id, 'club_id' => $club1->id],
            ['role' => 'president', 'status' => 'active', 'position' => 'President']
        );

        // Board members
        $tc_boards = [
            ['email' => 'board1.tech@gmail.com', 'first_name' => 'Sara',   'last_name' => 'Benali',  'code' => 'TC-BRD01', 'position' => 'Vice President'],
            ['email' => 'board2.tech@gmail.com', 'first_name' => 'Karim',  'last_name' => 'Tahiri',  'code' => 'TC-BRD02', 'position' => 'Secretary'],
            ['email' => 'board3.tech@gmail.com', 'first_name' => 'Nadia',  'last_name' => 'Ouali',   'code' => 'TC-BRD03', 'position' => 'Treasurer'],
        ];
        foreach ($tc_boards as $b) {
            $person = Person::firstOrCreate(
                ['email' => $b['email']],
                [
                    'first_name'  => $b['first_name'],
                    'last_name'   => $b['last_name'],
                    'password'    => Hash::make('password123'),
                    'member_code' => $b['code'],
                    'role'        => 'user',
                    'is_active'   => true,
                ]
            );
            Club_member::firstOrCreate(
                ['person_id' => $person->id, 'club_id' => $club1->id],
                ['role' => 'board', 'status' => 'active', 'position' => $b['position']]
            );
        }

        // Regular members
        $tc_members = [
            ['email' => 'member1.tech@gmail.com', 'first_name' => 'Amine',   'last_name' => 'Khalil',   'code' => 'TC-MBR01'],
            ['email' => 'member2.tech@gmail.com', 'first_name' => 'Fatima',  'last_name' => 'Zahrae',   'code' => 'TC-MBR02'],
            ['email' => 'member3.tech@gmail.com', 'first_name' => 'Omar',    'last_name' => 'Idrissi',  'code' => 'TC-MBR03'],
            ['email' => 'member4.tech@gmail.com', 'first_name' => 'Hasnaa',  'last_name' => 'Moussaoui','code' => 'TC-MBR04'],
        ];
        foreach ($tc_members as $m) {
            $person = Person::firstOrCreate(
                ['email' => $m['email']],
                [
                    'first_name'  => $m['first_name'],
                    'last_name'   => $m['last_name'],
                    'password'    => Hash::make('password123'),
                    'member_code' => $m['code'],
                    'role'        => 'user',
                    'is_active'   => true,
                ]
            );
            Club_member::firstOrCreate(
                ['person_id' => $person->id, 'club_id' => $club1->id],
                ['role' => 'member', 'status' => 'active']
            );
        }

        // Events for Club 1
        Event::firstOrCreate(
            ['title' => 'Hackathon 2026', 'club_id' => $club1->id],
            [
                'created_by'          => $president1->id,
                'description'         => 'A 24-hour hackathon open to all students.',
                'category'            => 'Competition',
                'event_date'          => now()->addDays(15),
                'registration_deadline'=> now()->addDays(10),
                'location'            => 'Building A, Room 101',
                'capacity'            => 100,
                'status'              => 'approved',
                'is_public'           => true,
                'requires_ticket'     => true,
                'tickets_for_all'     => true,
                'price'               => 0.00,
            ]
        );

        Event::firstOrCreate(
            ['title' => 'AI Workshop', 'club_id' => $club1->id],
            [
                'created_by'          => $president1->id,
                'description'         => 'Introduction to Machine Learning and AI tools.',
                'category'            => 'Workshop',
                'event_date'          => now()->addDays(30),
                'registration_deadline'=> now()->addDays(25),
                'location'            => 'Tech Lab, Floor 2',
                'capacity'            => 50,
                'status'              => 'approved',
                'is_public'           => true,
                'requires_ticket'     => false,
                'tickets_for_all'     => false,
                'price'               => 0.00,
            ]
        );

        $this->command->info('✅ Tech Club + members + events created!');

        // ─────────────────────────────────────────
        // CLUB 2 — Arts & Culture Club
        // ─────────────────────────────────────────

        $club2 = Club::firstOrCreate(
            ['code' => 'ARTS001'],
            [
                'name'          => 'Arts & Culture Club',
                'description'   => 'A club celebrating creativity and cultural diversity.',
                'mission'       => 'Foster artistic expression among students.',
                'category'      => 'Arts',
                'founding_year' => 2019,
                'is_public'     => true,
                'total_members' => 0,
                'active_members'=> 0,
            ]
        );

        // President
        $president2 = Person::firstOrCreate(
            ['email' => 'president.arts@gmail.com'],
            [
                'first_name'  => 'Imane',
                'last_name'   => 'Chraibi',
                'password'    => Hash::make('password123'),
                'member_code' => 'AC-PRES01',
                'role'        => 'user',
                'is_active'   => true,
            ]
        );
        Club_member::firstOrCreate(
            ['person_id' => $president2->id, 'club_id' => $club2->id],
            ['role' => 'president', 'status' => 'active', 'position' => 'President']
        );

        // Board members
        $ac_boards = [
            ['email' => 'board1.arts@gmail.com', 'first_name' => 'Mehdi',   'last_name' => 'Tazi',     'code' => 'AC-BRD01', 'position' => 'Vice President'],
            ['email' => 'board2.arts@gmail.com', 'first_name' => 'Layla',   'last_name' => 'Berrada',  'code' => 'AC-BRD02', 'position' => 'Secretary'],
            ['email' => 'board3.arts@gmail.com', 'first_name' => 'Hamza',   'last_name' => 'Essaidi',  'code' => 'AC-BRD03', 'position' => 'Treasurer'],
        ];
        foreach ($ac_boards as $b) {
            $person = Person::firstOrCreate(
                ['email' => $b['email']],
                [
                    'first_name'  => $b['first_name'],
                    'last_name'   => $b['last_name'],
                    'password'    => Hash::make('password123'),
                    'member_code' => $b['code'],
                    'role'        => 'user',
                    'is_active'   => true,
                ]
            );
            Club_member::firstOrCreate(
                ['person_id' => $person->id, 'club_id' => $club2->id],
                ['role' => 'board', 'status' => 'active', 'position' => $b['position']]
            );
        }

        // Regular members
        $ac_members = [
            ['email' => 'member1.arts@gmail.com', 'first_name' => 'Salma',   'last_name' => 'Filali',   'code' => 'AC-MBR01'],
            ['email' => 'member2.arts@gmail.com', 'first_name' => 'Tariq',   'last_name' => 'Benjelloun','code' => 'AC-MBR02'],
            ['email' => 'member3.arts@gmail.com', 'first_name' => 'Zineb',   'last_name' => 'Houssaini','code' => 'AC-MBR03'],
            ['email' => 'member4.arts@gmail.com', 'first_name' => 'Ilyas',   'last_name' => 'Amrani',   'code' => 'AC-MBR04'],
        ];
        foreach ($ac_members as $m) {
            $person = Person::firstOrCreate(
                ['email' => $m['email']],
                [
                    'first_name'  => $m['first_name'],
                    'last_name'   => $m['last_name'],
                    'password'    => Hash::make('password123'),
                    'member_code' => $m['code'],
                    'role'        => 'user',
                    'is_active'   => true,
                ]
            );
            Club_member::firstOrCreate(
                ['person_id' => $person->id, 'club_id' => $club2->id],
                ['role' => 'member', 'status' => 'active']
            );
        }

        // Events for Club 2
        Event::firstOrCreate(
            ['title' => 'Cultural Evening 2026', 'club_id' => $club2->id],
            [
                'created_by'           => $president2->id,
                'description'          => 'A night of music, dance and cultural performances.',
                'category'             => 'Performance',
                'event_date'           => now()->addDays(20),
                'registration_deadline'=> now()->addDays(15),
                'location'             => 'Main Auditorium',
                'capacity'             => 200,
                'status'               => 'approved',
                'is_public'            => true,
                'requires_ticket'      => true,
                'tickets_for_all'      => true,
                'price'                => 5.00,
            ]
        );

        Event::firstOrCreate(
            ['title' => 'Photography Exhibition', 'club_id' => $club2->id],
            [
                'created_by'           => $president2->id,
                'description'          => 'Showcasing student photography from across the campus.',
                'category'             => 'Exhibition',
                'event_date'           => now()->addDays(45),
                'registration_deadline'=> now()->addDays(40),
                'location'             => 'Gallery Hall, Building C',
                'capacity'             => 150,
                'status'               => 'approved',
                'is_public'            => true,
                'requires_ticket'      => false,
                'tickets_for_all'      => false,
                'price'                => 0.00,
            ]
        );

        $this->command->info('✅ Arts & Culture Club + members + events created!');
        $this->command->info('');
        $this->command->info('─────────────────────────────────────');
        $this->command->info('👑 ADMINS');
        $this->command->info('─────────────────────────────────────');
        $this->command->info('  achraf@gmail.com               | achraf123');
        $this->command->info('  achrafwandich1@gmail.com       | achraf123');
        $this->command->info('  souhaylaelabboudy2@gmail.com   | zofy123');
        $this->command->info('');
        $this->command->info('🏛️  TECH CLUB');
        $this->command->info('  President : president.tech@gmail.com   | password123');
        $this->command->info('  Board     : board1.tech@gmail.com      | password123');
        $this->command->info('  Board     : board2.tech@gmail.com      | password123');
        $this->command->info('  Board     : board3.tech@gmail.com      | password123');
        $this->command->info('  Members   : member1-4.tech@gmail.com   | password123');
        $this->command->info('');
        $this->command->info('🎨 ARTS & CULTURE CLUB');
        $this->command->info('  President : president.arts@gmail.com   | password123');
        $this->command->info('  Board     : board1.arts@gmail.com      | password123');
        $this->command->info('  Board     : board2.arts@gmail.com      | password123');
        $this->command->info('  Board     : board3.arts@gmail.com      | password123');
        $this->command->info('  Members   : member1-4.arts@gmail.com   | password123');
        $this->command->info('─────────────────────────────────────');
    }
}