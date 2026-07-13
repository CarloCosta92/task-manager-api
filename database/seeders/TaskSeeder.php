<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
{
    // 1. crea (o recupera) un utente di test
   $user = User::firstOrCreate(
    ['email' => 'test@test.com'],
    ['name' => 'Test User', 'password' => bcrypt('password123')]
);

    // 2. genera 10 task associati a quell'utente
    Task::factory()->count(10)->for($user)->create();
}
}
