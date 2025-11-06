<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Admin;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Adrianna Natasha',
            'email' => 'adrianna@example.com',
            'matriculation_number' => 'A24CS0992',
            'faculty' => 'Computing',
            'password' => bcrypt('123456'),
        ]);

        Admin::factory()->create([
            'name' => 'Ikhwan Hafizi',
            'email' => 'ikhwan@example.com',
            'password' => bcrypt('12345678'),
            'role' => 'admin',
        ]);
    }
}
