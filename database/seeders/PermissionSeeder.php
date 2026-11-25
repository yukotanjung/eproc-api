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
                "name" => "Manage Job Sub Field",
                "subject" => "job_sub_field",
                "action" => "read",
                "permission" => "job_sub_field_read"
            ],
            [
                "name" => "Manage Job Sub Field",
                "subject" => "job_sub_field",
                "action" => "create",
                "permission" => "job_sub_field_create"
            ],
            [
                "name" => "Manage Job Sub Field",
                "subject" => "job_sub_field",
                "action" => "update",
                "permission" => "job_sub_field_update"
            ],
            [
                "name" => "Manage Job Sub Field",
                "subject" => "job_sub_field",
                "action" => "delete",
                "permission" => "job_sub_field_delete"
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
            [
                "name" => "Manage Procurement",
                "subject" => "procurement",
                "action" => "read",
                "permission" => "procurement_read"
            ],
            [
                "name" => "Manage Procurement",
                "subject" => "procurement",
                "action" => "create",
                "permission" => "procurement_create"
            ],
            [
                "name" => "Manage Procurement",
                "subject" => "procurement",
                "action" => "update",
                "permission" => "procurement_update"
            ],
            [
                "name" => "Manage Procurement",
                "subject" => "procurement",
                "action" => "delete",
                "permission" => "procurement_delete"
            ],
            [
                "name" => "Manage Procurement",
                "subject" => "procurement",
                "action" => "cancel",
                "permission" => "procurement_cancel"
            ],
            [
                "name" => "Manage Procurement Document",
                "subject" => "procurement_document",
                "action" => "read",
                "permission" => "procurement_document_read"
            ],
            [
                "name" => "Manage Procurement Document",
                "subject" => "procurement_document",
                "action" => "create",
                "permission" => "procurement_document_create"
            ],
            [
                "name" => "Manage Procurement Document",
                "subject" => "procurement_document",
                "action" => "update",
                "permission" => "procurement_document_update"
            ],
            [
                "name" => "Manage Procurement Document",
                "subject" => "procurement_document",
                "action" => "delete",
                "permission" => "procurement_document_delete"
            ],
            [
                "name" => "Manage Procurement Checklist File",
                "subject" => "procurement_checklist_file",
                "action" => "read",
                "permission" => "procurement_checklist_file_read"
            ],
            [
                "name" => "Manage Procurement Checklist File",
                "subject" => "procurement_checklist_file",
                "action" => "create",
                "permission" => "procurement_checklist_file_create"
            ],
            [
                "name" => "Manage Procurement Checklist File",
                "subject" => "procurement_checklist_file",
                "action" => "update",
                "permission" => "procurement_checklist_file_update"
            ],
            [
                "name" => "Manage Procurement Checklist File",
                "subject" => "procurement_checklist_file",
                "action" => "delete",
                "permission" => "procurement_checklist_file_delete"
            ],
            [
                "name" => "Manage Holiday",
                "subject" => "holiday",
                "action" => "read",
                "permission" => "holiday_read"
            ],
            [
                "name" => "Manage Holiday",
                "subject" => "holiday",
                "action" => "create",
                "permission" => "holiday_create"
            ],
            [
                "name" => "Manage Holiday",
                "subject" => "holiday",
                "action" => "update",
                "permission" => "holiday_update"
            ],
            [
                "name" => "Manage Holiday",
                "subject" => "holiday",
                "action" => "delete",
                "permission" => "holiday_delete"
            ],
            [
                "name" => "Manage Unit",
                "subject" => "unit",
                "action" => "read",
                "permission" => "unit_read"
            ],
            [
                "name" => "Manage Unit",
                "subject" => "unit",
                "action" => "create",
                "permission" => "unit_create"
            ],
            [
                "name" => "Manage Unit",
                "subject" => "unit",
                "action" => "update",
                "permission" => "unit_update"
            ],
            [
                "name" => "Manage Unit",
                "subject" => "unit",
                "action" => "delete",
                "permission" => "unit_delete"
            ],
            [
                "name" => "Manage Procurement HPS",
                "subject" => "procurement_hps",
                "action" => "read",
                "permission" => "procurement_hps_read"
            ],
            [
                "name" => "Manage Procurement HPS",
                "subject" => "procurement_hps",
                "action" => "create",
                "permission" => "procurement_hps_create"
            ],
            [
                "name" => "Manage Procurement HPS",
                "subject" => "procurement_hps",
                "action" => "update",
                "permission" => "procurement_hps_update"
            ],
            [
                "name" => "Manage Procurement HPS",
                "subject" => "procurement_hps",
                "action" => "delete",
                "permission" => "procurement_hps_delete"
            ],
            [
                "name" => "Manage Province",
                "subject" => "province",
                "action" => "read",
                "permission" => "province_read"
            ],
            [
                "name" => "Manage Province",
                "subject" => "province",
                "action" => "create",
                "permission" => "province_create"
            ],
            [
                "name" => "Manage Province",
                "subject" => "province",
                "action" => "update",
                "permission" => "province_update"
            ],
            [
                "name" => "Manage Province",
                "subject" => "province",
                "action" => "delete",
                "permission" => "province_delete"
            ],
            [
                "name" => "Manage City",
                "subject" => "city",
                "action" => "read",
                "permission" => "city_read"
            ],
            [
                "name" => "Manage City",
                "subject" => "city",
                "action" => "create",
                "permission" => "city_create"
            ],
            [
                "name" => "Manage City",
                "subject" => "city",
                "action" => "update",
                "permission" => "city_update"
            ],
            [
                "name" => "Manage City",
                "subject" => "city",
                "action" => "delete",
                "permission" => "city_delete"
            ],
            [
                "name" => "Manage Vendor",
                "subject" => "vendor",
                "action" => "read",
                "permission" => "vendor_read"
            ],
            [
                "name" => "Manage Vendor",
                "subject" => "vendor",
                "action" => "create",
                "permission" => "vendor_create"
            ],
            [
                "name" => "Manage Vendor",
                "subject" => "vendor",
                "action" => "update",
                "permission" => "vendor_update"
            ],
            [
                "name" => "Manage Vendor",
                "subject" => "vendor",
                "action" => "delete",
                "permission" => "vendor_delete"
            ],
            [
                "name" => "Manage Vendor Owner",
                "subject" => "vendor_owner",
                "action" => "read",
                "permission" => "vendor_owner_read"
            ],
            [
                "name" => "Manage Vendor Owner",
                "subject" => "vendor_owner",
                "action" => "create",
                "permission" => "vendor_owner_create"
            ],
            [
                "name" => "Manage Vendor Owner",
                "subject" => "vendor_owner",
                "action" => "update",
                "permission" => "vendor_owner_update"
            ],
            [
                "name" => "Manage Vendor Owner",
                "subject" => "vendor_owner",
                "action" => "delete",
                "permission" => "vendor_owner_delete"
            ],
            [
                "name" => "Manage Vendor Commissioner",
                "subject" => "vendor_commissioner",
                "action" => "read",
                "permission" => "vendor_commissioner_read"
            ],
            [
                "name" => "Manage Vendor Commissioner",
                "subject" => "vendor_commissioner",
                "action" => "create",
                "permission" => "vendor_commissioner_create"
            ],
            [
                "name" => "Manage Vendor Commissioner",
                "subject" => "vendor_commissioner",
                "action" => "update",
                "permission" => "vendor_commissioner_update"
            ],
            [
                "name" => "Manage Vendor Commissioner",
                "subject" => "vendor_commissioner",
                "action" => "delete",
                "permission" => "vendor_commissioner_delete"
            ],
            [
                "name" => "Manage Vendor Director",
                "subject" => "vendor_director",
                "action" => "read",
                "permission" => "vendor_director_read"
            ],
            [
                "name" => "Manage Vendor Director",
                "subject" => "vendor_director",
                "action" => "create",
                "permission" => "vendor_director_create"
            ],
            [
                "name" => "Manage Vendor Director",
                "subject" => "vendor_director",
                "action" => "update",
                "permission" => "vendor_director_update"
            ],
            [
                "name" => "Manage Vendor Director",
                "subject" => "vendor_director",
                "action" => "delete",
                "permission" => "vendor_director_delete"
            ],
            [
                "name" => "Manage Vendor Expert",
                "subject" => "vendor_expert",
                "action" => "read",
                "permission" => "vendor_expert_read"
            ],
            [
                "name" => "Manage Vendor Expert",
                "subject" => "vendor_expert",
                "action" => "create",
                "permission" => "vendor_expert_create"
            ],
            [
                "name" => "Manage Vendor Expert",
                "subject" => "vendor_expert",
                "action" => "update",
                "permission" => "vendor_expert_update"
            ],
            [
                "name" => "Manage Vendor Expert",
                "subject" => "vendor_expert",
                "action" => "delete",
                "permission" => "vendor_expert_delete"
            ],
            [
                "name" => "Manage Vendor Financial",
                "subject" => "vendor_financial",
                "action" => "read",
                "permission" => "vendor_financial_read"
            ],
            [
                "name" => "Manage Vendor Financial",
                "subject" => "vendor_financial",
                "action" => "create",
                "permission" => "vendor_financial_create"
            ],
            [
                "name" => "Manage Vendor Financial",
                "subject" => "vendor_financial",
                "action" => "update",
                "permission" => "vendor_financial_update"
            ],
            [
                "name" => "Manage Vendor Financial",
                "subject" => "vendor_financial",
                "action" => "delete",
                "permission" => "vendor_financial_delete"
            ],
            [
                "name" => "Manage Vendor Tax",
                "subject" => "vendor_tax",
                "action" => "read",
                "permission" => "vendor_tax_read"
            ],
            [
                "name" => "Manage Vendor Tax",
                "subject" => "vendor_tax",
                "action" => "create",
                "permission" => "vendor_tax_create"
            ],
            [
                "name" => "Manage Vendor Tax",
                "subject" => "vendor_tax",
                "action" => "update",
                "permission" => "vendor_tax_update"
            ],
            [
                "name" => "Manage Vendor Tax",
                "subject" => "vendor_tax",
                "action" => "delete",
                "permission" => "vendor_tax_delete"
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
