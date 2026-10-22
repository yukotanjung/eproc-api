<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $name = "admin_super";
        $description = "Super Admin";

        $role = Role::query()->updateOrCreate(["name" => $name], [
            "name" => $name,
            "description" => $description,
        ]);
        $permission_ids = Permission::query()->get()->pluck("id");
        if(empty($permission_ids)) $permission_ids = [];
        $role->permissions()->sync($permission_ids);
    }
}
