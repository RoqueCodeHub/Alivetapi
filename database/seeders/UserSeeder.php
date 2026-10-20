<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Administrador Uno', 'email' => 'admin@alivet.com', 'password' => 'admin123', 'role_id' => 1],
            ['name' => 'Administrador Dos', 'email' => 'admin2@alivet.com', 'password' => 'admin123', 'role_id' => 1],
            ['name' => 'Operador Uno', 'email' => 'operador1@alivet.com', 'password' => 'operador123', 'role_id' => 1],
            ['name' => 'Operador Dos', 'email' => 'operador2@alivet.com', 'password' => 'operador123', 'role_id' => 1],
            ['name' => 'Cajera Uno', 'email' => 'cajera1@alivet.com', 'password' => 'cajera123', 'role_id' => 1],
            ['name' => 'Cajera Dos', 'email' => 'cajera2@alivet.com', 'password' => 'cajera123', 'role_id' => 1],
        ];

        foreach ($users as $row) {
            User::updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'password' => Hash::make($row['password']),
                    'role_id' => $row['role_id'],
                    'is_active' => true,
                ]
            );
        }
    }
}
