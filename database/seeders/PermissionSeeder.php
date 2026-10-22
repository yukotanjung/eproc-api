<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            [
                "name" => "Manage User",
                "subject" => "user",
                "action" => "delete",
                "permission" => "user_delete"
            ],
            [
                "name" => "Manage User",
                "subject" => "user",
                "action" => "update",
                "permission" => "user_update"
            ],
            [
                "name" => "Manage User",
                "subject" => "user",
                "action" => "read",
                "permission" => "user_read"
            ],
            [
                "name" => "Manage User",
                "subject" => "user",
                "action" => "create",
                "permission" => "user_create"
            ],
            [
                "name" => "Manage Role",
                "subject" => "role",
                "action" => "read",
                "permission" => "role_read"
            ],
            [
                "name" => "Manage Role",
                "subject" => "role",
                "action" => "create",
                "permission" => "role_create"
            ],
            [
                "name" => "Manage Role",
                "subject" => "role",
                "action" => "update",
                "permission" => "role_update"
            ],
            [
                "name" => "Manage Role",
                "subject" => "role",
                "action" => "delete",
                "permission" => "role_delete"
            ],
            [
                "name" => "Manage Permission",
                "subject" => "permission",
                "action" => "read",
                "permission" => "permission_read"
            ],
            [
                "name" => "Manage Funding Source",
                "subject" => "funding_source",
                "action" => "read",
                "permission" => "funding_source_read"
            ],
            [
                "name" => "Manage Funding Source",
                "subject" => "funding_source",
                "action" => "create",
                "permission" => "funding_source_create"
            ],
            [
                "name" => "Manage Funding Source",
                "subject" => "funding_source",
                "action" => "update",
                "permission" => "funding_source_update"
            ],
            [
                "name" => "Manage Funding Source",
                "subject" => "funding_source",
                "action" => "delete",
                "permission" => "funding_source_delete"
            ],
            [
                "name" => "Manage Tax",
                "subject" => "tax",
                "action" => "read",
                "permission" => "tax_read"
            ],
            [
                "name" => "Manage Tax",
                "subject" => "tax",
                "action" => "create",
                "permission" => "tax_create"
            ],
            [
                "name" => "Manage Tax",
                "subject" => "tax",
                "action" => "update",
                "permission" => "tax_update"
            ],
            [
                "name" => "Manage Tax",
                "subject" => "tax",
                "action" => "delete",
                "permission" => "tax_delete"
            ],
            [
                "name" => "Manage Business Type",
                "subject" => "business_type",
                "action" => "read",
                "permission" => "business_type_read"
            ],
            [
                "name" => "Manage Business Type",
                "subject" => "business_type",
                "action" => "create",
                "permission" => "business_type_create"
            ],
            [
                "name" => "Manage Business Type",
                "subject" => "business_type",
                "action" => "update",
                "permission" => "business_type_update"
            ],
            [
                "name" => "Manage Business Type",
                "subject" => "business_type",
                "action" => "delete",
                "permission" => "business_type_delete"
            ],
            [
                "name" => "Manage Work Unit",
                "subject" => "work_unit",
                "action" => "read",
                "permission" => "work_unit_read"
            ],
            [
                "name" => "Manage Work Unit",
                "subject" => "work_unit",
                "action" => "create",
                "permission" => "work_unit_create"
            ],
            [
                "name" => "Manage Work Unit",
                "subject" => "work_unit",
                "action" => "update",
                "permission" => "work_unit_update"
            ],
            [
                "name" => "Manage Work Unit",
                "subject" => "work_unit",
                "action" => "delete",
                "permission" => "work_unit_delete"
            ],
            [
                "name" => "Manage Qualification",
                "subject" => "qualification",
                "action" => "read",
                "permission" => "qualification_read"
            ],
            [
                "name" => "Manage Qualification",
                "subject" => "qualification",
                "action" => "create",
                "permission" => "qualification_create"
            ],
            [
                "name" => "Manage Qualification",
                "subject" => "qualification",
                "action" => "update",
                "permission" => "qualification_update"
            ],
            [
                "name" => "Manage Qualification",
                "subject" => "qualification",
                "action" => "delete",
                "permission" => "qualification_delete"
            ],
            [
                "name" => "Manage Education Level",
                "subject" => "education_level",
                "action" => "read",
                "permission" => "education_level_read"
            ],
            [
                "name" => "Manage Education Level",
                "subject" => "education_level",
                "action" => "create",
                "permission" => "education_level_create"
            ],
            [
                "name" => "Manage Education Level",
                "subject" => "education_level",
                "action" => "update",
                "permission" => "education_level_update"
            ],
            [
                "name" => "Manage Education Level",
                "subject" => "education_level",
                "action" => "delete",
                "permission" => "education_level_delete"
            ],
            [
                "name" => "Manage Major",
                "subject" => "major",
                "action" => "read",
                "permission" => "major_read"
            ],
            [
                "name" => "Manage Major",
                "subject" => "major",
                "action" => "create",
                "permission" => "major_create"
            ],
            [
                "name" => "Manage Major",
                "subject" => "major",
                "action" => "update",
                "permission" => "major_update"
            ],
            [
                "name" => "Manage Major",
                "subject" => "major",
                "action" => "delete",
                "permission" => "major_delete"
            ],
            [
                "name" => "Manage Job Type",
                "subject" => "job_type",
                "action" => "read",
                "permission" => "job_type_read"
            ],
            [
                "name" => "Manage Job Type",
                "subject" => "job_type",
                "action" => "create",
                "permission" => "job_type_create"
            ],
            [
                "name" => "Manage Job Type",
                "subject" => "job_type",
                "action" => "update",
                "permission" => "job_type_update"
            ],
            [
                "name" => "Manage Job Type",
                "subject" => "job_type",
                "action" => "delete",
                "permission" => "job_type_delete"
            ],
            [
                "name" => "Manage Job Field",
                "subject" => "job_field",
                "action" => "read",
                "permission" => "job_field_read"
            ],
            [
                "name" => "Manage Job Field",
                "subject" => "job_field",
                "action" => "create",
                "permission" => "job_field_create"
            ],
            [
                "name" => "Manage Job Field",
                "subject" => "job_field",
                "action" => "update",
                "permission" => "job_field_update"
            ],
            [
                "name" => "Manage Job Field",
                "subject" => "job_field",
                "action" => "delete",
                "permission" => "job_field_delete"
            ],
            [
                "name" => "Manage Classification",
                "subject" => "classification",
                "action" => "read",
                "permission" => "classification_read"
            ],
            [
                "name" => "Manage Classification",
                "subject" => "classification",
                "action" => "create",
                "permission" => "classification_create"
            ],
            [
                "name" => "Manage Classification",
                "subject" => "classification",
                "action" => "update",
                "permission" => "classification_update"
            ],
            [
                "name" => "Manage Classification",
                "subject" => "classification",
                "action" => "delete",
                "permission" => "classification_delete"
            ],
            [
                "name" => "Manage Procurement Method",
                "subject" => "procurement_method",
                "action" => "read",
                "permission" => "procurement_method_read"
            ],
            [
                "name" => "Manage Procurement Method",
                "subject" => "procurement_method",
                "action" => "create",
                "permission" => "procurement_method_create"
            ],
            [
                "name" => "Manage Procurement Method",
                "subject" => "procurement_method",
                "action" => "update",
                "permission" => "procurement_method_update"
            ],
            [
                "name" => "Manage Procurement Method",
                "subject" => "procurement_method",
                "action" => "delete",
                "permission" => "procurement_method_delete"
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                ["subject" => $permission["subject"], "action" => $permission["action"],],
                [
                    "name" => $permission["name"],
                    "key" => "_",
                    "subject" => $permission["subject"],
                    "action" => $permission["action"],
                ]
            );
        }
    }
}
