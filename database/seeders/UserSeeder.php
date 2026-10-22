<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $role = Role::query()->where("name", "=", "admin_super")->first();
        if ($role) {
            $name = "ADMIN";
            $email = "admin@gmail.com";
            $password = Hash::make("12345678");

            $user = User::query()->updateOrcreate(["email" => $email], [
                'name' => $name,
                'username' => 'admin',
                'employee_code' => 'EMP001',
                'email' => $email,
                'password' => $password,
                'email_verified_at' => date("Y-m-d H:i:s")
            ]);
            $user->roles()->sync([$role->id]);
        }
    }
}
