<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'ecolor',
            'email' => 'ecolor@gmail.com',
            'password' => Hash::make('Ecolor@2030@123'), // Hash the password
            'role' => 'superuser', // Example role
        ]);
    }
}
