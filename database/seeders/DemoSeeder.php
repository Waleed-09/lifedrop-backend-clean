<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // System Admin Account
        User::firstOrCreate(
            ['email' => 'admin@lifedrop.pk'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'phone' => '03493657462',
                'blood_group' => 'B+',
                'address' => 'Abbottabad, PK',
                'status' => 'active',
            ]
        );

        // Abbottabad ka ek fake blood bank
        User::firstOrCreate(
            ['email' => 'lifedrop@gmail.com'],
            [
                'name' => 'random blood bank for testing',
                'password' => Hash::make('password123'),
                'role' => 'bloodbank',
                'latitude' => 34.1463,
                'longitude' => 73.2114,
                'address' => 'Buner, PK',
            ]
        );

        // Abbottabad ke 5 different available donors
        $groups = ['O+', 'O-', 'A+', 'B+', 'AB+'];
        foreach ($groups as $i => $group) {
            User::firstOrCreate(
                ['email' => "donor{$i}@lifedrop.test"],
                [
                    'name' => "Donor {$i}",
                    'password' => Hash::make('password123'),
                    'role' => 'donor',
                    'blood_group' => $group,
                    'availability' => true,
                    'latitude' => 34.15 + ($i * 0.01),
                    'longitude' => 73.21 + ($i * 0.01),
                    'address' => 'Buner, PK',
                ]
            );
        }
    }
}