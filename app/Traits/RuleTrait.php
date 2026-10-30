<?php

namespace App\Traits;

use Illuminate\Support\Facades\Validator;
use App\Rules\IsUniqueOtherRule;

trait RuleTrait
{
    public function check_input_validation($custom_var = [])
    {
        $controller = get_called_class();
        $method = debug_backtrace()[1]['function'];
        $path = explode('\\', $controller);
        $controller = array_pop($path);
        $datas = request()->all();
        $validator = Validator::make($datas, $this->rules_lists($controller, $method, $custom_var), $this->custom_messages());
        if ($validator->fails()) return $validator->getMessageBag();
        else return null;
    }

    private function custom_messages()
    {
        $customMessages = [
            'password.regex' => 'The password must contain at least one uppercase letter, one lowercase letter, and one of the following symbols: @$!%*?&',
            'new_password.regex' => 'The password must contain at least one uppercase letter, one lowercase letter, and one of the following symbols: @$!%*?&',
        ];
        return $customMessages;
    }

    private function rules_lists($controller, $method, $params = [])
    {
        switch ($controller) {
            case 'PermissionController':
                if ($method == "index") {
                    return [
                        'page' => 'integer|min:1|nullable',
                        'page_size' => 'integer|min:1|nullable',
                        'all' => 'boolean|nullable',
                    ];
                }
                break;

            case 'RoleController':
                if ($method == "index") {
                    return [
                        'page' => 'integer|min:1|nullable',
                        'page_size' => 'integer|min:1|nullable',
                        'all' => 'boolean|nullable',
                    ];
                } elseif ($method == "store") {
                    return [
                        'name' => 'required|unique:roles,name,NULL,id,deleted_at,NULL|string|max:100',
                        'description' => 'string|max:255',
                        'permission_ids' => 'required|array|min:1'
                    ];
                } elseif ($method == "update") {
                    return [
                        'name' => ['required', 'max:100', new IsUniqueOtherRule("roles", "name", $params["own_id"])],
                        'description' => 'string|max:255',
                        'permission_ids' => 'required|array|min:1'
                    ];
                }
                break;

            case 'UserController':
                if ($method == 'store') {
                    return [
                        'name'          => 'required|string|max:100',
                        'username'      => 'required|unique:users,username,NULL,id,deleted_at,NULL|string|max:100',
                        'employee_code' => 'nullable|string|max:50',
                        'email'         => 'required|unique:users,email,NULL,id,deleted_at,NULL|string|email|max:100',
                        'role_id'       => 'required|string',
                        'photo'         => 'nullable|mimes:jpeg,jpg,png,gif',
                    ];
                } else if ($method == 'update') {
                    return [
                        'name'          => 'required|string|max:100',
                        'username'      => ['required', 'string', 'max:100', new IsUniqueOtherRule("users", "username", $params["own_id"])],
                        'employee_code' => 'nullable|string|max:50',
                        'email'         => ['required', 'string', 'email', 'max:100', new IsUniqueOtherRule("users", "email", $params["own_id"])],
                        'role_id'       => 'required|string',
                        'photo'         => 'nullable|mimes:jpeg,jpg,png,gif',
                    ];
                } else if ($method == 'updatePassword') {
                    return [
                        'old_password' => 'required',
                        'new_password' => 'required',
                    ];
                } else if ($method == 'updateProfile') {
                    return [
                        'name'          => 'required|string|max:100',
                        'username'      => ['nullable', 'string', 'max:100', new IsUniqueOtherRule("users", "username", $params["own_id"])],
                        'employee_code' => 'nullable|string|max:50',
                        'email'         => ['required', 'string', 'email', 'max:100', new IsUniqueOtherRule("users", "email", $params["own_id"])],
                        'photo'         => 'nullable|mimes:jpeg,jpg,png,gif',
                        'callback_url'  => 'required',
                    ];
                } else if ($method == 'updateNotificationDevice') {
                    return [
                        'notification_device' => 'required',
                    ];
                }
                break;

            case 'PasswordForgotController':

                if ($method == "sendLink") {
                    return [
                        'email' => 'required',
                    ];
                } elseif ($method == "updatePassword") {
                    return [
                        'email' => 'required',
                        'token' => 'required',
                        'password' => 'required|min:6',

                    ];
                }

                break;


            case 'FundingSourceController':
                if ($method == 'index') {
                    return [
                        'page'      => 'integer|min:1|nullable',
                        'page_size' => 'integer|min:1|nullable',
                        'all'       => 'boolean|nullable',
                    ];
                } elseif ($method == 'store') {
                    return [
                        'name'        => 'required|unique:funding_sources,name,NULL,id,deleted_at,NULL|string|max:100',
                        'description' => 'nullable|string|max:500',
                    ];
                } elseif ($method == 'update') {
                    return [
                        'name'        => ['required', 'max:100', new IsUniqueOtherRule('funding_sources', 'name', $params['own_id'])],
                        'description' => 'nullable|string|max:500',
                        'is_active'   => 'nullable|boolean',
                    ];
                }
                break;

            case 'TaxController':
                if ($method == 'index') {
                    return [
                        'page'      => 'integer|min:1|nullable',
                        'page_size' => 'integer|min:1|nullable',
                        'all'       => 'boolean|nullable',
                    ];
                } elseif ($method == 'store') {
                    return [
                        'name'   => 'required|unique:taxes,name,NULL,id,deleted_at,NULL|string|max:100',
                        'is_spt' => 'nullable|boolean',
                    ];
                } elseif ($method == 'update') {
                    return [
                        'name'      => ['required', 'max:100', new IsUniqueOtherRule('taxes', 'name', $params['own_id'])],
                        'is_spt'    => 'nullable|boolean',
                        'is_active' => 'nullable|boolean',
                    ];
                }
                break;

            case 'BusinessTypeController':
                if ($method == 'index') {
                    return [
                        'page'      => 'integer|min:1|nullable',
                        'page_size' => 'integer|min:1|nullable',
                        'all'       => 'boolean|nullable',
                    ];
                } elseif ($method == 'store') {
                    return [
                        'name'        => 'required|unique:business_types,name,NULL,id,deleted_at,NULL|string|max:100',
                        'description' => 'nullable|string|max:500',
                    ];
                } elseif ($method == 'update') {
                    return [
                        'name'        => ['required', 'max:100', new IsUniqueOtherRule('business_types', 'name', $params['own_id'])],
                        'description' => 'nullable|string|max:500',
                        'is_active'   => 'nullable|boolean',
                    ];
                }
                break;

            case 'WorkUnitController':
                if ($method == 'index') {
                    return [
                        'page'      => 'integer|min:1|nullable',
                        'page_size' => 'integer|min:1|nullable',
                        'all'       => 'boolean|nullable',
                    ];
                } elseif ($method == 'store') {
                    return [
                        'name'        => 'required|unique:work_units,name,NULL,id,deleted_at,NULL|string|max:100',
                        'code'        => 'required|unique:work_units,code,NULL,id,deleted_at,NULL|string|max:50',
                        'description' => 'nullable|string|max:500',
                    ];
                } elseif ($method == 'update') {
                    return [
                        'name'        => ['required', 'max:100', new IsUniqueOtherRule('work_units', 'name', $params['own_id'])],
                        'code'        => ['required', 'max:50', new IsUniqueOtherRule('work_units', 'code', $params['own_id'])],
                        'description' => 'nullable|string|max:500',
                        'is_active'   => 'nullable|boolean',
                    ];
                }
                break;

            case 'QualificationController':
                if ($method == 'index') {
                    return [
                        'page'      => 'integer|min:1|nullable',
                        'page_size' => 'integer|min:1|nullable',
                        'all'       => 'boolean|nullable',
                    ];
                } elseif ($method == 'store') {
                    return [
                        'name'        => 'required|unique:qualifications,name,NULL,id,deleted_at,NULL|string|max:100',
                        'description' => 'nullable|string|max:500',
                    ];
                } elseif ($method == 'update') {
                    return [
                        'name'        => ['required', 'max:100', new IsUniqueOtherRule('qualifications', 'name', $params['own_id'])],
                        'description' => 'nullable|string|max:500',
                        'is_active'   => 'nullable|boolean',
                    ];
                }
                break;

            case 'EducationLevelController':
                if ($method == 'index') {
                    return [
                        'page'      => 'integer|min:1|nullable',
                        'page_size' => 'integer|min:1|nullable',
                        'all'       => 'boolean|nullable',
                    ];
                } elseif ($method == 'store') {
                    return [
                        'name' => 'required|unique:education_levels,name,NULL,id,deleted_at,NULL|string|max:100',
                    ];
                } elseif ($method == 'update') {
                    return [
                        'name'      => ['required', 'max:100', new IsUniqueOtherRule('education_levels', 'name', $params['own_id'])],
                        'is_active' => 'nullable|boolean',
                    ];
                }
                break;

            case 'MajorController':
                if ($method == 'index') {
                    return [
                        'page'      => 'integer|min:1|nullable',
                        'page_size' => 'integer|min:1|nullable',
                        'all'       => 'boolean|nullable',
                    ];
                } elseif ($method == 'store') {
                    return [
                        'name' => 'required|unique:majors,name,NULL,id,deleted_at,NULL|string|max:100',
                    ];
                } elseif ($method == 'update') {
                    return [
                        'name'      => ['required', 'max:100', new IsUniqueOtherRule('majors', 'name', $params['own_id'])],
                        'is_active' => 'nullable|boolean',
                    ];
                }
                break;

            case 'JobTypeController':
                if ($method == 'index') {
                    return [
                        'page'      => 'integer|min:1|nullable',
                        'page_size' => 'integer|min:1|nullable',
                        'all'       => 'boolean|nullable',
                    ];
                } elseif ($method == 'store') {
                    return [
                        'name' => 'required|unique:job_types,name,NULL,id,deleted_at,NULL|string|max:100',
                    ];
                } elseif ($method == 'update') {
                    return [
                        'name'      => ['required', 'max:100', new IsUniqueOtherRule('job_types', 'name', $params['own_id'])],
                        'is_active' => 'nullable|boolean',
                    ];
                }
                break;

            case 'JobFieldController':
                if ($method == 'index') {
                    return [
                        'page'      => 'integer|min:1|nullable',
                        'page_size' => 'integer|min:1|nullable',
                        'all'       => 'boolean|nullable',
                    ];
                } elseif ($method == 'store') {
                    return [
                        'job_type_id' => 'required|integer|exists:job_types,id,deleted_at,NULL',
                        'name'        => 'required|unique:job_fields,name,NULL,id,deleted_at,NULL|string|max:100',
                    ];
                } elseif ($method == 'update') {
                    return [
                        'job_type_id' => 'required|integer|exists:job_types,id,deleted_at,NULL',
                        'name'        => ['required', 'max:100', new IsUniqueOtherRule('job_fields', 'name', $params['own_id'])],
                        'is_active'   => 'nullable|boolean',
                    ];
                }
                break;

            case 'ClassificationController':
                if ($method == 'index') {
                    return [
                        'page'      => 'integer|min:1|nullable',
                        'page_size' => 'integer|min:1|nullable',
                        'all'       => 'boolean|nullable',
                    ];
                } elseif ($method == 'store') {
                    return [
                        'job_type_id'  => 'required|integer|exists:job_types,id,deleted_at,NULL',
                        'job_field_id' => 'required|integer|exists:job_fields,id,deleted_at,NULL',
                        'name'         => 'required|unique:classifications,name,NULL,id,deleted_at,NULL|string|max:100',
                    ];
                } elseif ($method == 'update') {
                    return [
                        'job_type_id'  => 'required|integer|exists:job_types,id,deleted_at,NULL',
                        'job_field_id' => 'required|integer|exists:job_fields,id,deleted_at,NULL',
                        'name'         => ['required', 'max:100', new IsUniqueOtherRule('classifications', 'name', $params['own_id'])],
                        'is_active'    => 'nullable|boolean',
                    ];
                }
                break;

            case 'ProcurementMethodController':
                if ($method == 'index') {
                    return [
                        'page'      => 'integer|min:1|nullable',
                        'page_size' => 'integer|min:1|nullable',
                        'all'       => 'nullable',
                    ];
                } elseif ($method == 'store') {
                    return [
                        'name'        => 'required|unique:procurement_methods,name,NULL,id,deleted_at,NULL|string|max:100',
                        'description' => 'nullable|string|max:500',
                    ];
                } elseif ($method == 'update') {
                    return [
                        'name'        => ['required', 'max:100', new IsUniqueOtherRule('procurement_methods', 'name', $params['own_id'])],
                        'description' => 'nullable|string|max:500',
                        'is_active'   => 'nullable|boolean',
                    ];
                }
                break;

            case 'ProcurementMethodStageController':
                if ($method == 'store') {
                    return [
                        'name'          => 'required|string|max:150',
                        'duration_days' => 'required|integer|min:1|max:3650',
                        'day_type'      => 'required|in:calendar,working_day',
                        'requires_file' => 'nullable|boolean',
                        'page_key'      => 'required|string|max:100',
                        'process_key'   => 'required|string|max:100',
                        'description'   => 'nullable|string|max:500',
                    ];
                } elseif ($method == 'update') {
                    return [
                        'name'          => 'required|string|max:150',
                        'duration_days' => 'required|integer|min:1|max:3650',
                        'day_type'      => 'required|in:calendar,working_day',
                        'requires_file' => 'nullable|boolean',
                        'page_key'      => 'required|string|max:100',
                        'process_key'   => 'required|string|max:100',
                        'description'   => 'nullable|string|max:500',
                        'is_active'     => 'nullable|boolean',
                    ];
                } elseif ($method == 'reorder') {
                    return [
                        'stage_ids'   => 'required|array|min:1',
                        'stage_ids.*' => 'required|integer',
                    ];
                } elseif ($method == 'previewSchedule') {
                    return [
                        'start_date' => 'required|date_format:Y-m-d',
                    ];
                }
                break;

            case 'ProcurementController':
                if ($method == 'index') {
                    return [
                        'page'      => 'integer|min:1|nullable',
                        'page_size' => 'integer|min:1|nullable',
                        'all'       => 'nullable',
                    ];
                } elseif ($method == 'store') {
                    return [
                        'job_number'            => 'required|unique:procurements,job_number,NULL,id,deleted_at,NULL|string|max:100',
                        'name'                  => 'required|string|max:255',
                        'budget_ceiling'        => 'required|numeric|min:0',
                        'estimated_price'       => 'required|numeric|min:0',
                        'planned_date'          => 'required|date_format:Y-m-d',
                        'job_type_id'           => 'required|integer|exists:job_types,id,deleted_at,NULL',
                        'qualification_id'      => 'required|integer|exists:qualifications,id,deleted_at,NULL',
                        'funding_source_id'     => 'required|integer|exists:funding_sources,id,deleted_at,NULL',
                        'budget_year'           => 'required|integer|min:1900|max:2100',
                        'project_location'      => 'required|string|max:255',
                        'procurement_method_id' => 'required|integer|exists:procurement_methods,id,deleted_at,NULL',
                        'announcement_location' => 'required|string|max:255',
                    ];
                } elseif ($method == 'update') {
                    return [
                        'job_number'            => ['required', 'string', 'max:100', new IsUniqueOtherRule('procurements', 'job_number', $params['own_id'])],
                        'name'                  => 'required|string|max:255',
                        'budget_ceiling'        => 'required|numeric|min:0',
                        'estimated_price'       => 'required|numeric|min:0',
                        'planned_date'          => 'required|date_format:Y-m-d',
                        'job_type_id'           => 'required|integer|exists:job_types,id,deleted_at,NULL',
                        'qualification_id'      => 'required|integer|exists:qualifications,id,deleted_at,NULL',
                        'funding_source_id'     => 'required|integer|exists:funding_sources,id,deleted_at,NULL',
                        'budget_year'           => 'required|integer|min:1900|max:2100',
                        'project_location'      => 'required|string|max:255',
                        'procurement_method_id' => 'required|integer|exists:procurement_methods,id,deleted_at,NULL',
                        'announcement_location' => 'required|string|max:255',
                        'is_active'             => 'nullable|boolean',
                    ];
                } elseif ($method == 'cancel') {
                    return [
                        'reason'              => 'nullable|string|max:500',
                        'cancellation_reason' => 'nullable|string|max:500',
                    ];
                }
                break;

            case 'PasswordActivateController':
                if ($method == "activateAccount") {
                    return [
                        'email' => 'required',
                        'token' => 'required',
                        'password' => 'required|min:6',

                    ];
                } else if ($method == "verifyEmail") {
                    return [
                        'email' => 'required',
                        'token' => 'required',
                    ];
                }
                break;
        }
    }
}
